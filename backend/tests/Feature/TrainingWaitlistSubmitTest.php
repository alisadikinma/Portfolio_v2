<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use App\Models\TrainingWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingWaitlistSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'interest' => 'workshop',
            'source_page' => '/id/pelatihan-ai',
            'locale' => 'id',
            'client_key' => (string) Str::uuid(),
        ], $over);
    }

    public function test_valid_submit_returns_201_with_id(): void
    {
        $p = $this->payload();
        $res = $this->postJson('/api/training-waitlist', $p);

        $res->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame($p['client_key'], $res->json('data.client_key'));
        $this->assertArrayNotHasKey('id', $res->json('data'));
        $this->assertSame(1, TrainingWaitlist::count());
    }

    public function test_same_client_key_returns_200_same_id_no_second_row(): void
    {
        $p = $this->payload();
        $first = $this->postJson('/api/training-waitlist', $p)->assertStatus(201);
        $second = $this->postJson('/api/training-waitlist', $p)->assertStatus(200);

        $this->assertSame($first->json('data.client_key'), $second->json('data.client_key'));
        $this->assertSame($p['client_key'], $second->json('data.client_key'));
        $this->assertArrayNotHasKey('id', $second->json('data'));
        $this->assertSame(1, TrainingWaitlist::count());
    }

    public function test_missing_name_or_email_is_422(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['name' => '']))->assertStatus(422);
        $this->postJson('/api/training-waitlist', $this->payload(['email' => '']))->assertStatus(422);
    }

    public function test_bad_email_is_422(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['email' => 'nope']))->assertStatus(422);
    }

    public function test_bad_interest_and_locale_are_422(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['interest' => 'x']))->assertStatus(422);
        $this->postJson('/api/training-waitlist', $this->payload(['locale' => 'fr']))->assertStatus(422);
    }

    public function test_non_e164_whatsapp_is_422(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['whatsapp_number' => '0812345']))->assertStatus(422);
    }

    public function test_valid_whatsapp_accepted(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['whatsapp_number' => '+6281234567890']))->assertStatus(201);
        $this->assertSame('+6281234567890', TrainingWaitlist::first()->whatsapp_number);
    }

    public function test_opt_in_without_whatsapp_is_422(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['newsletter_opt_in' => true]))->assertStatus(422);
        $this->assertSame(0, Newsletter::count());
        $this->assertSame(0, TrainingWaitlist::count());
    }

    public function test_opt_in_with_whatsapp_creates_exactly_one_newsletter_row(): void
    {
        $p = $this->payload(['newsletter_opt_in' => true, 'whatsapp_number' => '+6281234567890']);
        $this->postJson('/api/training-waitlist', $p)->assertStatus(201);
        $this->postJson('/api/training-waitlist', $p)->assertStatus(200);

        $this->assertSame(1, Newsletter::count());
        $n = Newsletter::first();
        $this->assertSame('training_waitlist', $n->source);
        $this->assertSame('budi@example.com', $n->email);
        $this->assertSame(32, strlen($n->unsubscribe_token));
        $this->assertTrue((bool) $n->is_subscribed);
    }

    public function test_opt_in_with_existing_newsletter_email_creates_no_duplicate(): void
    {
        Newsletter::create([
            'name' => 'Old', 'email' => 'budi@example.com', 'whatsapp_number' => '+6280000000001',
            'is_subscribed' => true, 'subscribed_at' => now(),
        ]);

        $this->postJson('/api/training-waitlist', $this->payload([
            'newsletter_opt_in' => true, 'whatsapp_number' => '+6281234567890',
        ]))->assertStatus(201);

        $this->assertSame(1, Newsletter::count());
    }

    public function test_no_opt_in_means_no_newsletter_row(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['whatsapp_number' => '+6281234567890']))->assertStatus(201);
        $this->assertSame(0, Newsletter::count());
    }

    public function test_honeypot_returns_fake_success_and_stores_nothing(): void
    {
        Log::spy();

        $p = $this->payload(['website' => 'http://spam.test']);
        $res = $this->postJson('/api/training-waitlist', $p);

        $res->assertStatus(201)->assertExactJson(['success' => true, 'data' => ['client_key' => $p['client_key']]]);
        $this->assertSame(0, TrainingWaitlist::count());
        $this->assertSame(0, Newsletter::count());
        Log::shouldHaveReceived('info')->once();
    }

    public function test_sixth_request_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/training-waitlist', $this->payload(['email' => "u{$i}@example.com"]))->assertStatus(201);
        }
        $this->postJson('/api/training-waitlist', $this->payload(['email' => 'u6@example.com']))
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_idempotent_replay_does_not_consume_the_limit(): void
    {
        $first = $this->payload();
        $this->postJson('/api/training-waitlist', $first)->assertStatus(201);

        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/training-waitlist', $first)->assertStatus(200);
        }
        // Only 1 real submit so far: 4 more distinct submits must still pass.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/training-waitlist', $this->payload(['email' => "n{$i}@example.com"]))->assertStatus(201);
        }
    }

    public function test_source_page_must_be_whitelisted(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['source_page' => '/id/training']))->assertStatus(422);
        $this->postJson('/api/training-waitlist', $this->payload(['source_page' => 'https://evil.test']))->assertStatus(422);
        $this->postJson('/api/training-waitlist', $this->payload(['source_page' => '/en/ai-training']))->assertStatus(201);
    }

    public function test_email_is_lowercased_and_trimmed(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['email' => '  Budi@Example.COM ']))->assertStatus(201);

        $this->assertSame('budi@example.com', TrainingWaitlist::first()->email);
    }

    public function test_honeypot_with_otherwise_invalid_fields_still_returns_201(): void
    {
        $p = $this->payload(['website' => 'x', 'email' => 'nope', 'name' => '', 'interest' => 'zzz']);
        $res = $this->postJson('/api/training-waitlist', $p);

        $res->assertStatus(201)->assertExactJson(['success' => true, 'data' => ['client_key' => $p['client_key']]]);
        $this->assertSame(0, TrainingWaitlist::count());
    }

    public function test_unique_client_key_race_returns_winner_200_without_logging_pii(): void
    {
        Log::spy();
        $p = $this->payload(['email' => 'race@example.com']);
        $fired = false;
        TrainingWaitlist::creating(function () use ($p, &$fired) {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('training_waitlist')->insert([
                'name' => 'Winner', 'email' => 'winner@example.com', 'interest' => 'workshop',
                'source_page' => '/id/pelatihan-ai', 'locale' => 'id', 'client_key' => $p['client_key'],
                'status' => 'new', 'newsletter_opt_in' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $res = $this->postJson('/api/training-waitlist', $p);

        $res->assertStatus(200)->assertExactJson(['success' => true, 'data' => ['client_key' => $p['client_key']]]);
        $this->assertSame(1, TrainingWaitlist::count());
        Log::shouldNotHaveReceived('error');
    }

    public function test_whatsapp_clash_skips_newsletter_and_logs_id_only(): void
    {
        Log::spy();
        Newsletter::create([
            'name' => 'Other', 'email' => 'other@example.com', 'whatsapp_number' => '+6281234567890',
            'is_subscribed' => true, 'subscribed_at' => now(),
        ]);

        $this->postJson('/api/training-waitlist', $this->payload([
            'newsletter_opt_in' => true, 'whatsapp_number' => '+6281234567890',
        ]))->assertStatus(201);

        $this->assertSame(1, Newsletter::count());
        $id = TrainingWaitlist::first()->id;
        Log::shouldHaveReceived('info')->withArgs(
            fn ($msg, $ctx = []) => $ctx === ['waitlist_id' => $id]
        )->once();
    }

    public function test_newsletter_unique_violation_does_not_fail_submit(): void
    {
        Log::spy();
        $fired = false;
        Newsletter::creating(function () use (&$fired) {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('newsletters')->insert([
                'name' => 'Racer', 'email' => 'budi@example.com', 'whatsapp_number' => '+6280000000009',
                'unsubscribe_token' => str_repeat('a', 32), 'is_subscribed' => true,
                'subscribed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->postJson('/api/training-waitlist', $this->payload([
            'newsletter_opt_in' => true, 'whatsapp_number' => '+6281234567890',
        ]))->assertStatus(201);

        $id = TrainingWaitlist::first()->id;
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($msg, $ctx = []) => $ctx === ['waitlist_id' => $id]
        )->once();
    }

    public function test_name_and_note_trimmed_and_capped(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload([
            'name' => '  Budi  ', 'note' => '  '.str_repeat('a', 1000).'  ',
        ]))->assertStatus(201);

        $row = TrainingWaitlist::first();
        $this->assertSame('Budi', $row->name);
        $this->assertSame(1000, strlen($row->note));

        $this->postJson('/api/training-waitlist', $this->payload(['note' => str_repeat('a', 1001)]))->assertStatus(422);
        $this->postJson('/api/training-waitlist', $this->payload(['name' => str_repeat('a', 121)]))->assertStatus(422);
    }

    public function test_invalid_client_key_is_422(): void
    {
        $this->postJson('/api/training-waitlist', $this->payload(['client_key' => 'not-a-uuid']))->assertStatus(422);
    }
}
