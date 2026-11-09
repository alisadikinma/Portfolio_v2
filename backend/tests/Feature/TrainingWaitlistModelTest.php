<?php

namespace Tests\Feature;

use App\Models\TrainingWaitlist;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingWaitlistModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_defaults_to_new(): void
    {
        $row = TrainingWaitlist::factory()->create();

        $this->assertSame('new', $row->fresh()->status);
    }

    public function test_duplicate_client_key_is_rejected_at_db_level(): void
    {
        $key = '3f2b8c1e-0d4a-4b7e-9c11-5a6d7e8f9a0b';
        TrainingWaitlist::factory()->create(['client_key' => $key]);

        $this->expectException(QueryException::class);
        TrainingWaitlist::factory()->create(['client_key' => $key]);
    }

    public function test_interest_accepts_valid_values(): void
    {
        foreach (['inhouse', 'workshop', 'public_class'] as $interest) {
            $row = TrainingWaitlist::factory()->create(['interest' => $interest]);
            $this->assertSame($interest, $row->fresh()->interest);
        }
    }

    public function test_newsletter_opt_in_defaults_false_and_casts_bool(): void
    {
        $row = TrainingWaitlist::factory()->create();

        $this->assertFalse($row->fresh()->newsletter_opt_in);
    }
}
