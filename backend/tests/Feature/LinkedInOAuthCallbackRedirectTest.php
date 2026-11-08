<?php

namespace Tests\Feature;

use Tests\TestCase;

class LinkedInOAuthCallbackRedirectTest extends TestCase
{
    public function test_callback_redirect_uses_configured_frontend_url(): void
    {
        // Simulates config:cache — env() returns null, only config() is trusted.
        config(['app.frontend_url' => 'https://alisadikinma.com/']);

        $response = $this->get('/api/admin/linkedin/oauth/callback?error=access_denied');

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://alisadikinma.com/admin/settings/about?linkedin_oauth=error',
            $response->headers->get('Location')
        );
    }

    public function test_callback_redirect_falls_back_to_app_url_when_frontend_url_unset(): void
    {
        config(['app.frontend_url' => null, 'app.url' => 'https://example.test']);

        $response = $this->get('/api/admin/linkedin/oauth/callback?error=access_denied');

        $this->assertStringStartsWith(
            'https://example.test/admin/settings/about',
            $response->headers->get('Location')
        );
    }
}
