<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortLinkRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_serves_301_to_target_url(): void
    {
        $row = ShortLink::create([
            'code' => 'tEsT123',
            'target_url' => 'https://alisadikinma.com/blog/test?utm_source=linkedin',
            'post_id' => null,
            'source_platform' => 'linkedin',
        ]);

        $response = $this->get('/r/tEsT123');

        $response->assertStatus(301);
        $response->assertRedirect('https://alisadikinma.com/blog/test?utm_source=linkedin');
    }

    public function test_redirect_404s_for_unknown_code(): void
    {
        $response = $this->get('/r/notexist');
        $response->assertStatus(404);
    }

    public function test_redirect_increments_hit_counter(): void
    {
        $row = ShortLink::create([
            'code' => 'cnt1234',
            'target_url' => 'https://alisadikinma.com/blog/x',
            'post_id' => null,
            'source_platform' => null,
        ]);

        $this->get('/r/cnt1234');
        $this->get('/r/cnt1234');
        $this->get('/r/cnt1234');

        $row->refresh();
        $this->assertSame(3, $row->hits);
        $this->assertNotNull($row->last_hit_at);
    }

    public function test_redirect_rejects_codes_outside_pattern(): void
    {
        // Code regex `[A-Za-z0-9]{4,16}` rejects under 4 chars or special chars.
        $this->get('/r/ab')->assertStatus(404);   // too short
        $this->get('/r/abc-def')->assertStatus(404); // hyphen not allowed
    }
}
