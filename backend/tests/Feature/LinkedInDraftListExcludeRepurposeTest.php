<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\RepurposeJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Social Studio Phase A — `exclude_repurpose=1` on GET /admin/linkedin-drafts.
 *
 * The Social Studio union list draws blog-origin drafts from this endpoint and
 * IG-repurpose jobs from RepurposeJobController. To keep the two sources
 * DISJOINT (a finalized repurpose carousel IS a LinkedInPost, but it's reached
 * through its repurpose card), the draft source must EXCLUDE repurpose-origin
 * drafts. The filter mirrors LinkedInGenerationService::isRepurposeDraft() at
 * QUERY level (no per-row predicate / N+1):
 *   - draft.id referenced by RepurposeJob.linkedin_post_id, OR
 *   - draft.post_id = some RepurposeJob.anchor_post_id, OR
 *   - draft.post_id links a ContentIdea{source:'instagram'} via result_post_id.
 *
 * NOTE on the documented "(d) null post_id KEPT" case: `linkedin_posts.post_id`
 * is NOT NULL in schema (foreignId->constrained, never altered nullable), so a
 * null-post_id draft is un-insertable. The defensive `whereNull('post_id')`
 * guard in the query stays (harmless, future-proofs the NULL-NOT-IN trap), but
 * the testable regression is "a normal blog draft with a real post_id is KEPT".
 */
class LinkedInDraftListExcludeRepurposeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** Seed a posts row (+ a linked ContentIdea when $igSource). Returns posts.id. */
    private function seedPost(bool $igSource = false): int
    {
        $categoryId = DB::table('categories')->value('id')
            ?? DB::table('categories')->insertGetId([
                'name' => 'General', 'slug' => 'general',
                'created_at' => now(), 'updated_at' => now(),
            ]);

        $postId = DB::table('posts')->insertGetId([
            'category_id' => $categoryId,
            'title' => 'Studio test post',
            'slug' => 'studio-' . uniqid(),
            'content' => 'Body.',
            'published' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($igSource) {
            DB::table('content_ideas')->insert([
                'title' => 'IG idea',
                'pillar' => 'ai_agents',
                'priority' => 'medium',
                'niche' => 'AI & Tech',
                'source' => 'instagram',
                'result_post_id' => $postId,
                'virality_score' => 70,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $postId;
    }

    /** Seed a queue-status LinkedInPost draft for $postId. Returns linkedin_posts.id. */
    private function seedDraft(int $postId): int
    {
        return DB::table('linkedin_posts')->insertGetId([
            'post_id' => $postId,
            'format' => 'carousel',
            'content' => 'caption',
            'hashtags' => json_encode(['#a', '#b', '#c']),
            'status' => LinkedInPostStatus::ManualReview->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function listIds(string $query): array
    {
        $res = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/linkedin-drafts?{$query}");
        $res->assertOk();
        return array_column($res->json('data'), 'id');
    }

    public function test_excludes_draft_referenced_by_repurpose_linkedin_post_id(): void
    {
        $draftId = $this->seedDraft($this->seedPost());
        RepurposeJob::factory()->create(['linkedin_post_id' => $draftId, 'status' => 'drafted']);

        $this->assertNotContains($draftId, $this->listIds('scope=queue&exclude_repurpose=1'));
    }

    public function test_excludes_draft_whose_post_is_repurpose_anchor(): void
    {
        $postId = $this->seedPost();
        $draftId = $this->seedDraft($postId);
        RepurposeJob::factory()->create(['anchor_post_id' => $postId, 'status' => 'drafted']);

        $this->assertNotContains($draftId, $this->listIds('scope=queue&exclude_repurpose=1'));
    }

    public function test_excludes_draft_whose_post_links_instagram_content_idea(): void
    {
        $draftId = $this->seedDraft($this->seedPost(igSource: true));

        $this->assertNotContains($draftId, $this->listIds('scope=queue&exclude_repurpose=1'));
    }

    public function test_keeps_normal_blog_draft(): void
    {
        // Real post_id, no repurpose linkage anywhere → must survive the filter.
        $draftId = $this->seedDraft($this->seedPost());

        $this->assertContains($draftId, $this->listIds('scope=queue&exclude_repurpose=1'));
    }

    public function test_without_flag_includes_all(): void
    {
        $repurposeDraft = $this->seedDraft($this->seedPost());
        RepurposeJob::factory()->create(['linkedin_post_id' => $repurposeDraft, 'status' => 'drafted']);
        $normalDraft = $this->seedDraft($this->seedPost());

        $ids = $this->listIds('scope=queue');
        $this->assertContains($repurposeDraft, $ids);
        $this->assertContains($normalDraft, $ids);
    }
}
