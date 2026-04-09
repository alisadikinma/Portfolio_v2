<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\Post;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $apiKey = config('ai.api_key');
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CONFIG_ERROR', 'message' => 'AI service not configured'],
            ], 503);
        }

        // Assemble portfolio context
        $context = $this->buildPortfolioContext();

        $systemPrompt = <<<EOT
You are Ali Sadikin's portfolio assistant. You answer questions about Ali's work, skills, and experience.
Be helpful, concise, and professional. If you don't know something, say so honestly.

Here is Ali's portfolio context:
{$context}

Rules:
- Only answer questions related to Ali's portfolio, skills, projects, and professional work.
- Keep responses under 300 words.
- Be friendly and professional.
EOT;

        try {
            $baseUrl = config('ai.base_url', 'https://openrouter.ai/api/v1');

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://alisadikinma.com',
                'X-Title' => 'Ali Sadikin Portfolio',
            ])->timeout(30)->post("{$baseUrl}/chat/completions", [
                'model' => config('ai.model'),
                'max_tokens' => config('ai.max_tokens'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $request->message],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $answer = $data['choices'][0]['message']['content']
                    ?? 'Sorry, I could not generate a response.';

                return response()->json([
                    'success' => true,
                    'data' => ['answer' => $answer],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => 'Failed to get AI response'],
            ], 502);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => 'AI service unavailable'],
            ], 503);
        }
    }

    private function buildPortfolioContext(): string
    {
        $parts = [];

        // About info
        $aboutSettings = Setting::where('group', 'about')->pluck('value', 'key')->toArray();
        if (!empty($aboutSettings)) {
            $name = $aboutSettings['name'] ?? 'Ali Sadikin';
            $title = $aboutSettings['title'] ?? 'AI Generalist Expert';
            $bio = $aboutSettings['bio'] ?? '';
            $parts[] = "Name: {$name}\nTitle: {$title}\nBio: {$bio}";
        }

        // Projects
        $projects = Project::select('title', 'description')->limit(20)->get();
        if ($projects->count()) {
            $projectList = $projects->map(fn($p) => "- {$p->title}: {$p->description}")->implode("\n");
            $parts[] = "Projects ({$projects->count()}):\n{$projectList}";
        }

        // Awards
        $awards = Award::select('title', 'description')->get();
        if ($awards->count()) {
            $awardList = $awards->map(fn($a) => "- {$a->title}: {$a->description}")->implode("\n");
            $parts[] = "Awards:\n{$awardList}";
        }

        // Recent posts
        $posts = Post::select('title')->latest()->limit(10)->get();
        if ($posts->count()) {
            $postList = $posts->map(fn($p) => "- {$p->title}")->implode("\n");
            $parts[] = "Recent Blog Posts:\n{$postList}";
        }

        return implode("\n\n", $parts);
    }
}
