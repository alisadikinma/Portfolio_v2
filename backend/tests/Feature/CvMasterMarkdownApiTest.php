<?php

namespace Tests\Feature;

use App\Models\Project;
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
