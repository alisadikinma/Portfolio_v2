<?php

namespace Tests\Unit;

use App\Services\GeminiGenSignatureVerifier;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GeminiGenSignatureVerifierTest extends TestCase
{
    private string $tmpPubKeyPath;
    private string $tmpPubKeyPathWrong;
    private $privKey;
    private $privKeyWrong;
    private GeminiGenSignatureVerifier $verifier;

    private array $opensslConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        // On Windows XAMPP, openssl.cnf must be passed explicitly to openssl_pkey_new / openssl_pkey_export.
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

        // Generate RSA keypair #1 (the "real" one)
        $res = openssl_pkey_new($keygenOpts);
        $this->assertNotFalse($res, 'openssl_pkey_new #1 failed: '.openssl_error_string());
        openssl_pkey_export($res, $this->privKey, null, $this->opensslConfig);
        $details = openssl_pkey_get_details($res);
        $this->tmpPubKeyPath = tempnam(sys_get_temp_dir(), 'gemgen_pub_').'.pem';
        file_put_contents($this->tmpPubKeyPath, $details['key']);

        // Generate RSA keypair #2 (a different one, used to prove wrong-key fails)
        $resWrong = openssl_pkey_new($keygenOpts);
        $this->assertNotFalse($resWrong, 'openssl_pkey_new #2 failed: '.openssl_error_string());
        openssl_pkey_export($resWrong, $this->privKeyWrong, null, $this->opensslConfig);
        $detailsWrong = openssl_pkey_get_details($resWrong);
        $this->tmpPubKeyPathWrong = tempnam(sys_get_temp_dir(), 'gemgen_pub_wrong_').'.pem';
        file_put_contents($this->tmpPubKeyPathWrong, $detailsWrong['key']);

        $this->verifier = new GeminiGenSignatureVerifier();
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPubKeyPath);
        @unlink($this->tmpPubKeyPathWrong);
        parent::tearDown();
    }

    private function signBody(string $body, $privKey): string
    {
        $md5Raw = md5($body, true);
        openssl_sign($md5Raw, $sig, $privKey, OPENSSL_ALGO_SHA256);
        return bin2hex($sig);
    }

    public function test_valid_signature_returns_true(): void
    {
        $body = '{"event":"IMAGE_GENERATION_COMPLETED","uuid":"abc","data":{}}';
        $sigHex = $this->signBody($body, $this->privKey);

        $this->assertTrue($this->verifier->verify($body, $sigHex, $this->tmpPubKeyPath));
    }

    public function test_tampered_body_returns_false(): void
    {
        $body = '{"event":"IMAGE_GENERATION_COMPLETED","uuid":"abc","data":{}}';
        $sigHex = $this->signBody($body, $this->privKey);

        $tamperedBody = '{"event":"IMAGE_GENERATION_FAILED","uuid":"abc","data":{}}';
        $this->assertFalse($this->verifier->verify($tamperedBody, $sigHex, $this->tmpPubKeyPath));
    }

    public function test_wrong_public_key_returns_false(): void
    {
        $body = '{"event":"IMAGE_GENERATION_COMPLETED","uuid":"abc","data":{}}';
        $sigHex = $this->signBody($body, $this->privKey);  // signed with key #1

        // verify with key #2 → should fail
        $this->assertFalse($this->verifier->verify($body, $sigHex, $this->tmpPubKeyPathWrong));
    }

    public function test_missing_public_key_file_returns_false_and_logs(): void
    {
        Log::spy();

        $body = '{"event":"X","uuid":"y","data":{}}';
        $sigHex = $this->signBody($body, $this->privKey);

        $this->assertFalse($this->verifier->verify($body, $sigHex, '/nonexistent/path/key.pem'));

        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_malformed_hex_signature_returns_false(): void
    {
        $body = '{"event":"X","uuid":"y","data":{}}';

        $this->assertFalse($this->verifier->verify($body, 'NOT-VALID-HEX-!@#$', $this->tmpPubKeyPath));
    }
}
