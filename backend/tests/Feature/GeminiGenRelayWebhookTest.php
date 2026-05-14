<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeminiGenRelayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $tokenString;
    private string $tmpPubKeyPath;
    private $privKey;
    private array $opensslConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Normalize APP_URL for the test runner. Local dev `.env` has
        //   APP_URL=http://localhost/Portfolio_v2/backend/public
        // which causes Laravel's test client to prepend the `/Portfolio_v2/backend/public`
        // path segment to every URL — so `/api/automation/geminigen/webhook` becomes
        // `/Portfolio_v2/backend/public/api/automation/geminigen/webhook` which doesn't
        // match the registered route. Production VPS has APP_URL=https://alisadikinma.com
        // so there's no clash there. Reset the URL generator to ensure tests resolve.
        config(['app.url' => 'http://localhost']);
        \URL::forceRootUrl('http://localhost');

        // On Windows XAMPP, openssl.cnf must be passed explicitly to openssl_pkey_new / openssl_pkey_export.
        // Mirror the pattern proven in tests/Unit/GeminiGenSignatureVerifierTest.php.
        $candidates = [
            'D:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            'D:\\xampp\\apache\\conf\\openssl.cnf',
            '/etc/ssl/openssl.cnf',
        ];
        foreach ($candidates as $cnf) {
            if (is_readable($cnf)) {
                $this->opensslConfig = ['config' => $cnf];
                break;
            }
        }

        $keygenOpts = array_merge($this->opensslConfig, [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $res = openssl_pkey_new($keygenOpts);
        $this->assertNotFalse($res, 'openssl_pkey_new failed: '.openssl_error_string());
        openssl_pkey_export($res, $this->privKey, null, $this->opensslConfig);
        $details = openssl_pkey_get_details($res);
        $this->tmpPubKeyPath = tempnam(sys_get_temp_dir(), 'gemgen_pub_').'.pem';
        file_put_contents($this->tmpPubKeyPath, $details['key']);

        config(['geminigen-relay.public_key_path' => $this->tmpPubKeyPath]);

        $user = User::factory()->create();
        $this->tokenString = $user->createToken('test-relay', ['geminigen:relay'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPubKeyPath);
        parent::tearDown();
    }

    private function signBody(string $body): string
    {
        $md5Raw = md5($body, true);
        openssl_sign($md5Raw, $sig, $this->privKey, OPENSSL_ALGO_SHA256);
        return bin2hex($sig);
    }

    private function validCbParam(string $url = 'https://example.com/cb'): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    public function test_missing_token_returns_401(): void
    {
        $r = $this->postJson(
            '/api/automation/geminigen/webhook?cb='.$this->validCbParam(),
            ['event' => 'X', 'uuid' => 'y', 'data' => []]
        );
        $r->assertStatus(401)->assertJsonPath('error.code', 'MISSING_TOKEN');
    }

    public function test_invalid_token_returns_401(): void
    {
        $r = $this->postJson(
            '/api/automation/geminigen/webhook?cb='.$this->validCbParam().'&token=not-a-real-token',
            ['event' => 'X', 'uuid' => 'y', 'data' => []]
        );
        $r->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_token_without_ability_returns_401(): void
    {
        $user = User::factory()->create();
        $wrongAbilityToken = $user->createToken('wrong', ['post:read'])->plainTextToken;

        $r = $this->postJson(
            '/api/automation/geminigen/webhook?cb='.$this->validCbParam().'&token='.$wrongAbilityToken,
            ['event' => 'X', 'uuid' => 'y', 'data' => []]
        );
        $r->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_missing_cb_returns_422(): void
    {
        $r = $this->postJson(
            '/api/automation/geminigen/webhook?token='.$this->tokenString,
            ['event' => 'X', 'uuid' => 'y', 'data' => []]
        );
        $r->assertStatus(422)->assertJsonPath('error.code', 'MISSING_CB');
    }

    public function test_invalid_cb_base64_returns_422(): void
    {
        $r = $this->postJson(
            '/api/automation/geminigen/webhook?cb=!!!not-base64!!!&token='.$this->tokenString,
            ['event' => 'X', 'uuid' => 'y', 'data' => []]
        );
        $r->assertStatus(422)->assertJsonPath('error.code', 'INVALID_CB');
    }

    public function test_missing_signature_header_returns_403(): void
    {
        $body = ['event' => 'IMAGE_GENERATION_COMPLETED', 'uuid' => 'test-uuid', 'data' => []];
        $r = $this->postJson(
            '/api/automation/geminigen/webhook?cb='.$this->validCbParam().'&token='.$this->tokenString,
            $body
        );
        $r->assertStatus(403)->assertJsonPath('error.code', 'MISSING_SIGNATURE');
    }

    public function test_invalid_signature_returns_403(): void
    {
        $body = ['event' => 'IMAGE_GENERATION_COMPLETED', 'uuid' => 'test-uuid', 'data' => []];
        $r = $this->postJson(
            '/api/automation/geminigen/webhook?cb='.$this->validCbParam().'&token='.$this->tokenString,
            $body,
            ['X-Signature' => 'deadbeef00']
        );
        $r->assertStatus(403)->assertJsonPath('error.code', 'INVALID_SIGNATURE');
    }
}
