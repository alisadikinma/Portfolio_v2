<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use App\Models\TrainingWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'source_page' => '/id/training',
            'locale' => 'id',
            'client_key' => (string) Str::uuid(),
        ], $over);
    }

    public function test_valid_submit_returns_201_with_id(): void
    {
        $res = $this->postJson('/api/training-waitlist', $this->payload());

        $res->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame(TrainingWaitlist::first()->id, $res->json('data.id'));
        $this->assertSame(1, TrainingWaitlist::count());
    }

    public function test_same_client_key_returns_200_same_id_no_second_row(): void
    {
        $p = $this->payload();
        $first = $this->postJson('/api/training-waitlist', $p)->assertStatus(201);
        $second = $this->postJson('/api/training-waitlist', $p)->assertStatus(200);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
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

        $res = $this->postJson('/api/training-waitlist', $this->payload(['website' => 'http://spam.test']));

        $res->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame(0, TrainingWaitlist::count());
        $this->assertSame(0, Newsletter::count());
        Log::shouldHaveReceived('info')->once();
    }

    public function test_sixth_request_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/training-waitlist', $this->payload(['email' => "u{$i}@example.com"]))->assertStatus(201);
        }
        $this->postJson('/api/training-waitlist', $this->payload(['email' => 'u6@example.com']))->assertStatus(429);
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
