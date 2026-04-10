<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use App\Services\TrendingTopicService;
use App\Services\ImageGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BlogPipelineController
 *
 * Two endpoints for the automated blog pipeline:
 * 1. GET  /trending-topic  — Backend finds & returns the best AI/tech topic
 * 2. POST /save-draft      — Backend generates images + saves the article as draft
 *
 * Claude Scheduled Task only handles article generation (copywriting + image prompts).
 */
class BlogPipelineController extends Controller
{
    /**
     * GET /api/automation/blog/trending-topic
     *
     * Fetches Google Trends RSS, filters for AI/tech topics,
     * deduplicates against existing posts, returns the best topic.
     */
    public function trendingTopic(TrendingTopicService $trendService): JsonResponse
    {
        $topic = $trendService->getBestTopic();

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'No unique trending AI/tech topic found today.',
                'data' => null,
            ]);
        }

        // Suggest a category
        $categoryId = $trendService->suggestCategory($topic['title']);
        $category = Category::find($categoryId);

        return response()->json([
            'success' => true,
            'data' => [
                'topic' => $topic['title'],
                'description' => $topic['description'] ?? '',
                'source' => $topic['source'] ?? 'Google Trends',
                'matched_keyword' => $topic['matched_keyword'] ?? '',
                'suggested_category' => [
                    'id' => $categoryId,
                    'name' => $category?->name ?? 'Technology',
                    'slug' => $category?->slug ?? 'technology',
                ],
            ],
        ]);
    }

    /**
     * POST /api/automation/blog/save-draft
     *
     * Receives complete article from Claude (EN + ID content + image prompts),
     * generates all images via GeminiGen, inserts images into content,
     * and saves as draft post.
     *
     * Expected payload:
     * {
     *   "title": "EN title",
     *   "slug": "en-slug" (optional, auto-generated),
     *   "category_id": 3,
     *   "tags": ["AI", "Technology"],
     *   "hero_image_prompt": "A cinematic wide shot of...",
     *   "translations": {
     *     "en": {
     *       "title": "...", "slug": "...", "excerpt": "...", "content": "<HTML>",
     *       "meta_title": "...", "meta_description": "...", "meta_keywords": "...",
     *       "ai_summary": "..."
     *     },
     *     "id": { ... same fields ... }
     *   },
     *   "inline_image_prompts": [
     *     { "prompt": "...", "insert_after_heading": "H2 heading text" },
     *     { "prompt": "...", "insert_after_heading": "Another H2" }
     *   ]
     * }
     */
    public function saveDraft(Request $request, ImageGenerationService $imageService): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'translations' => 'required|array',
            'translations.en' => 'required|array',
            'translations.en.title' => 'required|string|max:255',
            'translations.en.content' => 'required|string|min:100',
        ]);

        try {
            DB::beginTransaction();

            // 1. Generate hero image
            $heroImageUrl = null;
            $heroPrompt = $request->input('hero_image_prompt');
            if ($heroPrompt) {
                $heroImageUrl = $imageService->generate($heroPrompt, 'imagen-pro', '16:9', 'Photorealistic');
            }

            // 2. Generate inline images and insert into content
            $translations = $request->input('translations');
            $inlinePrompts = $request->input('inline_image_prompts', []);

            foreach ($inlinePrompts as $imgSpec) {
                $prompt = $imgSpec['prompt'] ?? '';
                $afterHeading = $imgSpec['insert_after_heading'] ?? '';
                if (empty($prompt)) continue;

                $imgUrl = $imageService->generate($prompt, 'imagen-pro', '4:3', 'Photorealistic');
                if (!$imgUrl) continue;

                $imgTag = '<figure class="my-8"><img src="' . e($imgUrl) . '" alt="' . e(Str::limit($prompt, 100)) . '" class="w-full rounded-xl" loading="lazy" /><figcaption class="text-sm text-gray-500 mt-2 text-center">' . e(Str::limit($afterHeading, 80)) . '</figcaption></figure>';

                // Insert image after the matching H2 heading in both EN and ID content
                foreach (['en', 'id'] as $lang) {
                    if (!isset($translations[$lang]['content'])) continue;
                    $content = $translations[$lang]['content'];

                    if (!empty($afterHeading)) {
                        // Insert after the H2 that contains this text
                        $pattern = '/(<\/h2>)/i';
                        $headingPos = stripos($content, $afterHeading);
                        if ($headingPos !== false) {
                            $closeTagPos = strpos($content, '</h2>', $headingPos);
                            if ($closeTagPos !== false) {
                                $insertPos = $closeTagPos + 5; // after </h2>
                                $translations[$lang]['content'] = substr($content, 0, $insertPos) . "\n" . $imgTag . "\n" . substr($content, $insertPos);
                            }
                        }
                    }
                }
            }

            // 3. Create the post
            $enTitle = $translations['en']['title'];
            $slug = $request->input('slug') ?: Str::slug($enTitle);

            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (Post::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $enData = $translations['en'];

            $post = Post::create([
                'category_id' => $request->input('category_id'),
                'slug' => $slug,
                'featured_image' => $heroImageUrl,
                'tags' => $request->input('tags', []),
                'published' => false, // ALWAYS DRAFT
                'published_at' => null,
                'views' => 0,
                'index_follow' => true,
            ]);

            // 4. Create translations
            foreach ($translations as $lang => $transData) {
                if (empty($transData['title']) || empty($transData['content'])) continue;

                $transSlug = $transData['slug'] ?? ($lang === 'en' ? $slug : Str::slug($transData['title']));

                $post->translations()->create([
                    'language' => $lang,
                    'title' => $transData['title'],
                    'slug' => $transSlug,
                    'excerpt' => $transData['excerpt'] ?? Str::limit(strip_tags($transData['content']), 150),
                    'content' => $transData['content'],
                    'meta_title' => $transData['meta_title'] ?? null,
                    'meta_description' => $transData['meta_description'] ?? null,
                    'meta_keywords' => $transData['meta_keywords'] ?? null,
                    'og_title' => $transData['meta_title'] ?? $transData['title'],
                    'og_description' => $transData['meta_description'] ?? null,
                    'canonical_url' => $transData['canonical_url'] ?? null,
                    'ai_summary' => $transData['ai_summary'] ?? null,
                ]);
            }

            DB::commit();

            $post->load(['category', 'translations']);

            return response()->json([
                'success' => true,
                'message' => 'Draft article saved successfully.',
                'data' => [
                    'post_id' => $post->id,
                    'slug' => $post->slug,
                    'title' => $post->title,
                    'featured_image' => $heroImageUrl,
                    'inline_images_generated' => count(array_filter($inlinePrompts, fn($p) => !empty($p['prompt']))),
                    'translations' => $post->translations->pluck('language')->toArray(),
                    'status' => 'draft',
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save draft article.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
