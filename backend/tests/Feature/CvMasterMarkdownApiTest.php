<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for the CV Master Markdown API.
 *
 * Verifies:
 *   - Auth gate (no token -> 401)
 *   - Ability gate (token without cv:read -> 403)
 *   - Successful 200 response with text/markdown content type
 */
class CvMasterMarkdownApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Default APP_URL contains the XAMPP subpath which trips relative
        // route resolution under sqlite -- same workaround other Feature
        // tests use (mirrors CvExportApiTest).
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    /** @test */
    public function returns_401_without_token(): void
    {
        $this->getJson('/api/cv/master.md')->assertStatus(401);
    }

    /** @test */
    public function returns_403_when_token_missing_cv_read_ability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['some:other:ability']);

        $this->getJson('/api/cv/master.md')->assertStatus(403);
    }

    /** @test */
    public function returns_200_with_markdown_content_type_when_authorized(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->get('/api/cv/master.md');

        $response->assertOk();
        $this->assertStringContainsString(
            'text/markdown',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    /** @test */
    public function renders_identity_and_summary_from_settings(): void
    {
        Setting::create(['group' => 'about', 'key' => 'name', 'value' => 'Ali Sadikin Ma']);
        Setting::create(['group' => 'about', 'key' => 'title', 'value' => 'AI Generalist Expert']);
        Setting::create(['group' => 'about', 'key' => 'bio', 'value' => 'Bio summary text for the elevator pitch.']);
        Setting::create([
            'group' => 'about',
            'key' => 'social_links',
            'value' => json_encode([
                ['platform' => 'LinkedIn', 'url' => 'https://linkedin.com/in/x', 'icon' => 'fab fa-linkedin'],
                ['platform' => 'NoUrl', 'icon' => 'fab fa-x'],
            ]),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        $this->assertStringContainsString('# Ali Sadikin Ma', $body);
        $this->assertStringContainsString('AI Generalist Expert', $body);
        $this->assertStringContainsString('## Summary', $body);
        $this->assertStringContainsString('Bio summary text for the elevator pitch.', $body);
        $this->assertStringContainsString('linkedin.com/in/x', $body);
    }

    /** @test */
    public function renders_skills_matrix_with_domain_headers_and_project_counts(): void
    {
        // 2 projects matching ai_automation/ai_agents heuristic ("AI" keyword)
        Project::create([
            'title' => 'AI Agent Platform',
            'slug' => 'ai-agent-platform',
            'description' => 'Multi-agent orchestration toolkit',
            'category' => 'AI',
            'published' => true,
            'sort_order' => 10,
        ]);
        Project::create([
            'title' => 'Automation Pipeline',
            'slug' => 'automation-pipeline',
            'description' => 'RPA workflow engine',
            'category' => 'Automation',
            'published' => true,
            'sort_order' => 20,
        ]);
        // 1 project matching manufacturing/enterprise heuristic
        Project::create([
            'title' => 'Manufacturing QA System',
            'slug' => 'mfg-qa',
            'description' => 'Vision inspection for factory QA',
            'category' => 'Manufacturing',
            'published' => true,
            'sort_order' => 30,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        $this->assertStringContainsString('## Skills Matrix', $body);
        // ai_automation domain — 2 projects matched
        $this->assertMatchesRegularExpression(
            '/### AI Automation \(~7 yrs · 2 projects\)/',
            $body
        );
        // manufacturing domain — 1 project matched
        $this->assertMatchesRegularExpression(
            '/### Industrial Automation & Manufacturing \(~12 yrs · 1 projects?\)/',
            $body
        );
        // Bullets present under each domain
        $this->assertStringContainsString('LLM orchestration, RAG pipelines', $body);
        $this->assertStringContainsString('PLC programming', $body);
    }

    /** @test */
    public function renders_all_projects_in_sort_order_with_en_translation(): void
    {
        Project::create([
            'title' => 'Z Project',
            'slug' => 'z-project',
            'description' => 'Z desc',
            'category' => 'web',
            'role' => 'Engineer',
            'published' => true,
            'sort_order' => 30,
        ]);
        $a = Project::create([
            'title' => 'A Project (raw)',
            'slug' => 'a-project',
            'description' => 'A desc',
            'category' => 'web',
            'role' => 'Tech Lead',
            'published' => true,
            'sort_order' => 10,
        ]);
        ProjectTranslation::create([
            'project_id' => $a->id,
            'language' => 'en',
            'title' => 'A Project EN',
            'slug' => 'a-project',
            'description' => 'English description for A.',
        ]);
        Project::create([
            'title' => 'M Project',
            'slug' => 'm-project',
            'description' => 'M desc',
            'category' => 'web',
            'published' => true,
            'sort_order' => 20,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        $this->assertStringContainsString('## Selected Projects (3)', $body);
        // EN translation wins for project A
        $this->assertStringContainsString('A Project EN', $body);
        $this->assertStringNotContainsString('A Project (raw)', $body);
        // Sort order honored: A (10), M (20), Z (30)
        $posA = strpos($body, 'A Project EN');
        $posM = strpos($body, 'M Project');
        $posZ = strpos($body, 'Z Project');
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posM);
        $this->assertNotFalse($posZ);
        $this->assertLessThan($posM, $posA);
        $this->assertLessThan($posZ, $posM);
        // Role surfaces for A
        $this->assertStringContainsString('Tech Lead', $body);
    }

    /** @test */
    public function falls_back_to_primary_translation_when_en_missing(): void
    {
        $p = Project::create([
            'title' => 'Direct Title',
            'slug' => 'only-id',
            'description' => 'Direct desc',
            'category' => 'web',
            'published' => true,
            'sort_order' => 10,
        ]);
        ProjectTranslation::create([
            'project_id' => $p->id,
            'language' => 'id',
            'title' => 'Judul Indonesia',
            'slug' => 'only-id',
            'description' => 'Deskripsi Indonesia.',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        // Indonesian fallback rendered when EN missing — no "[ID]" prefix tag
        $this->assertStringContainsString('Judul Indonesia', $body);
        $this->assertStringNotContainsString('[ID]', $body);
    }

    /** @test */
    public function renders_awards_section_ordered_by_is_featured_then_id_desc(): void
    {
        Award::create([
            'title' => 'Old Unfeatured',
            'organization' => 'Foo',
            'received_at' => '2018',
            'is_featured' => false,
        ]);
        Award::create([
            'title' => 'Recent Unfeatured',
            'organization' => 'Bar',
            'received_at' => '2024',
            'is_featured' => false,
        ]);
        Award::create([
            'title' => 'Featured Outskill',
            'organization' => 'Outskill',
            'received_at' => '2025',
            'description' => 'Demo Day Champion #1',
            'is_featured' => true,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        $this->assertStringContainsString('## Awards & Recognition', $body);

        // Featured row appears first; remaining ordered by id desc.
        $posFeatured = strpos($body, 'Featured Outskill');
        $posRecent = strpos($body, 'Recent Unfeatured');
        $posOld = strpos($body, 'Old Unfeatured');
        $this->assertNotFalse($posFeatured);
        $this->assertNotFalse($posRecent);
        $this->assertNotFalse($posOld);
        $this->assertLessThan($posRecent, $posFeatured);
        $this->assertLessThan($posOld, $posRecent);
        $this->assertStringContainsString('Demo Day Champion', $body);
    }

    /** @test */
    public function renders_thought_leadership_with_top_5_posts_by_published_at(): void
    {
        $cat = Category::create(['name' => 'AI', 'slug' => 'ai']);

        // 7 published posts — only the 5 most recent should land in the section.
        for ($i = 1; $i <= 7; $i++) {
            $post = Post::create([
                'category_id' => $cat->id,
                'slug' => "post-$i",
                'title' => "Stub Title $i",
                'excerpt' => "Stub excerpt $i",
                'content' => "<p>Body $i</p>",
                'published' => true,
                'published_at' => now()->subDays(8 - $i), // post-7 is most recent
            ]);
            PostTranslation::create([
                'post_id' => $post->id,
                'language' => 'en',
                'title' => "EN Title $i",
                'slug' => "post-$i",
                'excerpt' => "EN excerpt $i",
                'content' => "<p>EN body $i</p>",
            ]);
        }

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        $this->assertStringContainsString('## Thought Leadership', $body);
        // Most recent (post-7) shows up; oldest 2 (post-1, post-2) trimmed.
        $this->assertStringContainsString('EN Title 7', $body);
        $this->assertStringContainsString('EN Title 3', $body);
        $this->assertStringNotContainsString('EN Title 2', $body);
        $this->assertStringNotContainsString('EN Title 1', $body);
    }

    /** @test */
    public function renders_footer_with_generated_timestamp_and_self_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        // Footer separator + "Generated YYYY-MM-DD" line.
        $this->assertStringContainsString('---', $body);
        $this->assertMatchesRegularExpression(
            '/Generated \d{4}-\d{2}-\d{2}/',
            $body
        );
        $this->assertStringContainsString('cv/master.md', $body);
    }

    /** @test */
    public function omits_optional_contact_fields_when_settings_absent(): void
    {
        Setting::create(['group' => 'about', 'key' => 'name', 'value' => 'Ali Sadikin']);
        Setting::create(['group' => 'about', 'key' => 'title', 'value' => 'AI Generalist']);
        Setting::create(['group' => 'about', 'key' => 'bio', 'value' => 'Short bio.']);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $body = $this->get('/api/cv/master.md')->assertOk()->getContent();

        // No literal "null" leaking into the rendered output.
        $this->assertStringNotContainsString('null', $body);
        // No "email:" / "phone:" prefix when their settings aren't set.
        $this->assertStringNotContainsString('email:', $body);
        $this->assertStringNotContainsString('phone:', $body);
    }
}
