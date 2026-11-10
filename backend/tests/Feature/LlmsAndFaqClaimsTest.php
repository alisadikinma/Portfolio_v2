<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PF-2 Phase 4G: llms.txt carries the corrected identity and no unsupported
 * claims; the FAQ sets (EN + ID) drop "AI Generalist" and "16 countries".
 */
class LlmsAndFaqClaimsTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN = '/\bAI Generalist|16 countries|16 negara|56 AI products|Startup Grind|IDBYTE/i';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
        Setting::create(['group' => 'about', 'key' => 'title', 'value' => 'AI Generalist Expert']);
    }

    public function test_llms_txt_has_new_intro_and_no_forbidden_claims(): void
    {
        $body = $this->get('/api/llms.txt')->assertStatus(200)->getContent();

        $this->assertStringContainsString(
            '# Ali Sadikin Ma — pakar AI Indonesia / AI consultant, founder INDUSIA.ai (https://indusia.ai)',
            $body,
        );
        $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $body);
        // Ali 2026-11-09: the count is Ali-wide (59 = 56 Satnusa + PCI, IRN, MONI), not Satnusa-only.
        $this->assertStringContainsString('59+ digital products (AI, IoT, mobile)', $body);
        $this->assertStringNotContainsString('56+', $body);
    }

    public function test_llms_txt_lists_new_pages_language_prefixed(): void
    {
        $body = $this->get('/api/llms.txt')->getContent();
        foreach (['/id/pelatihan-ai', '/en/ai-training', '/id/pembicara-ai', '/en/ai-speaker', '/id/about', '/en/about', '/id/faq', '/en/faq'] as $p) {
            $this->assertStringContainsString('https://alisadikinma.com'.$p, $body, $p);
        }
        $this->assertStringContainsString('/en/work?tab=awards', $body);
        $this->assertDoesNotMatchRegularExpression('#https://alisadikinma\.com/(about|faq|blog|projects|contact)\b#', $body);
    }

    public function test_llms_full_txt_has_new_intro_and_no_forbidden_claims(): void
    {
        $body = $this->get('/api/llms-full.txt')->assertStatus(200)->getContent();
        $this->assertStringContainsString('pakar AI Indonesia / AI consultant, founder INDUSIA.ai (https://indusia.ai)', $body);
        $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $body);
    }

    public function test_llms_full_txt_about_is_plain_text_not_html(): void
    {
        Setting::create(['group' => 'about', 'key' => 'bio', 'value' => '<p>17 years in enterprise.</p><p>I run <strong>INDUSIA.ai</strong> &amp; ship AI.</p>']);
        $body = $this->get('/api/llms-full.txt')->assertStatus(200)->getContent();
        $this->assertStringContainsString("17 years in enterprise.\n\nI run INDUSIA.ai & ship AI.", $body);
        $this->assertStringNotContainsString('<p>', $body);
        $this->assertStringNotContainsString('<strong>', $body);
    }

    public function test_faq_sets_drop_unsupported_claims_and_stay_one_to_one(): void
    {
        $en = config('faq.items');
        $id = config('faq.items_id');
        $this->assertCount(count($en), $id);
        $this->assertDoesNotMatchRegularExpression('/\bAI Generalist|16 countries|16 negara/i', json_encode($en, JSON_UNESCAPED_UNICODE));
        $this->assertDoesNotMatchRegularExpression('/\bAI Generalist|16 countries|16 negara/i', json_encode($id, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('17 tahun', $id[0]['answer']);
        $this->assertStringContainsString('17 years', $en[0]['answer']);
    }
}
