<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    private const TRAEFIK = '172.19.0.3';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Route::get('/__ip-probe', fn (Request $r) => response()->json(['ip' => $r->ip()]));
    }

    public function test_real_client_ip_resolved_behind_traefik_and_cloudflare(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::TRAEFIK])
            ->getJson('/__ip-probe', ['X-Forwarded-For' => '203.0.113.7, 173.245.48.1'])
            ->assertJsonPath('ip', '203.0.113.7');
    }

    public function test_real_client_ip_resolved_behind_cloudflare_ipv6(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::TRAEFIK])
            ->getJson('/__ip-probe', ['X-Forwarded-For' => '203.0.113.9, 2606:4700::1'])
            ->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_untrusted_remote_addr_cannot_spoof_via_xff(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->getJson('/__ip-probe', ['X-Forwarded-For' => '1.2.3.4'])
            ->assertJsonPath('ip', '198.51.100.20');
    }

    public function test_spoofed_leftmost_xff_entry_is_ignored(): void
    {
        // Attacker prepends 1.2.3.4; first untrusted hop from the right is the real client.
        $this->withServerVariables(['REMOTE_ADDR' => self::TRAEFIK])
            ->getJson('/__ip-probe', ['X-Forwarded-For' => '1.2.3.4, 203.0.113.7, 173.245.48.1'])
            ->assertJsonPath('ip', '203.0.113.7');
    }

    public function test_contact_throttle_is_per_real_client(): void
    {
        $post = fn (string $client) => $this
            ->withServerVariables(['REMOTE_ADDR' => self::TRAEFIK])
            ->postJson('/api/contact', [], ['X-Forwarded-For' => "{$client}, 173.245.48.1"]);

        for ($i = 0; $i < 3; $i++) {
            $this->assertNotSame(429, $post('203.0.113.7')->getStatusCode());
        }
        $post('203.0.113.7')->assertStatus(429);

        $this->assertNotSame(429, $post('203.0.113.8')->getStatusCode());
    }
}
