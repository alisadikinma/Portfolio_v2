<?php

namespace Tests\Feature;

use App\Models\TrainingWaitlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingWaitlistAdminTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_guest_gets_401_on_every_endpoint(): void
    {
        $row = TrainingWaitlist::factory()->create();

        $this->getJson('/api/admin/training-waitlist')->assertStatus(401);
        $this->getJson('/api/admin/training-waitlist/export')->assertStatus(401);
        $this->patchJson("/api/admin/training-waitlist/{$row->id}", ['status' => 'contacted'])->assertStatus(401);
        $this->assertSame('new', $row->fresh()->status);
    }

    public function test_index_paginates_with_data_meta_links(): void
    {
        $this->asAdmin();
        TrainingWaitlist::factory()->count(3)->create();

        $res = $this->getJson('/api/admin/training-waitlist?per_page=2')->assertOk();
        $res->assertJsonPath('success', true);
        $res->assertJsonCount(2, 'data');
        $res->assertJsonPath('meta.total', 3);
        $res->assertJsonPath('meta.last_page', 2);
        $this->assertArrayHasKey('links', $res->json());
        $this->assertArrayHasKey('next', $res->json('links'));
    }

    public function test_index_filters(): void
    {
        $this->asAdmin();
        TrainingWaitlist::factory()->create(['name' => 'Budi', 'interest' => 'inhouse', 'locale' => 'id', 'status' => 'new']);
        TrainingWaitlist::factory()->create(['name' => 'Cara', 'interest' => 'workshop', 'locale' => 'en', 'status' => 'contacted', 'company' => 'Acme Corp']);

        $this->getJson('/api/admin/training-waitlist?interest=workshop')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/training-waitlist?locale=id')->assertJsonPath('data.0.name', 'Budi');
        $this->getJson('/api/admin/training-waitlist?status=contacted')->assertJsonPath('data.0.name', 'Cara');
        $this->getJson('/api/admin/training-waitlist?search=acme')->assertJsonPath('data.0.name', 'Cara');
        $this->getJson('/api/admin/training-waitlist?search=Budi')->assertJsonPath('meta.total', 1);
    }

    public function test_index_hides_client_key(): void
    {
        $this->asAdmin();
        TrainingWaitlist::factory()->create();
        $row = $this->getJson('/api/admin/training-waitlist')->json('data.0');
        $this->assertArrayNotHasKey('client_key', $row);
    }

    public function test_update_changes_status_and_note_only(): void
    {
        $this->asAdmin();
        $row = TrainingWaitlist::factory()->create(['name' => 'Orig']);

        $this->patchJson("/api/admin/training-waitlist/{$row->id}", [
            'status' => 'contacted',
            'admin_note' => 'called',
            'name' => 'Hacked',
            'email' => 'x@y.z',
        ])->assertOk()->assertJsonPath('data.status', 'contacted');

        $row->refresh();
        $this->assertSame('contacted', $row->status);
        $this->assertSame('called', $row->admin_note);
        $this->assertSame('Orig', $row->name);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $this->asAdmin();
        $row = TrainingWaitlist::factory()->create();

        $this->patchJson("/api/admin/training-waitlist/{$row->id}", ['status' => 'bogus'])
            ->assertStatus(422);
        $this->assertSame('new', $row->fresh()->status);
    }

    public function test_update_missing_row_404_envelope(): void
    {
        $this->asAdmin();
        $this->patchJson('/api/admin/training-waitlist/99999', ['status' => 'new'])
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_export_csv_escapes_formula_injection_and_uses_wib(): void
    {
        $this->asAdmin();
        TrainingWaitlist::factory()->create([
            'name' => '=HYPERLINK("http://evil","x")',
            'company' => '+cmd',
            'email' => 'a@b.co',
            'whatsapp_number' => '@x',
            'created_at' => '2026-11-08 03:00:00',
        ]);
        TrainingWaitlist::factory()->create(['name' => "\tTab", 'company' => '-1', 'interest' => 'workshop']);
        TrainingWaitlist::factory()->create(['name' => "\rCR"]);

        $res = $this->get('/api/admin/training-waitlist/export');
        $res->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringStartsWith('name,company,email,whatsapp,interest,locale,status,created_at', $csv);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\",\"\"x\"\")\"", $csv);
        $this->assertStringContainsString("'+cmd", $csv);
        $this->assertStringContainsString("'@x", $csv);
        $this->assertStringContainsString("'-1", $csv);
        $this->assertStringContainsString("\"'\tTab\"", $csv);
        $this->assertStringContainsString("\"'\rCR\"", $csv);
        $this->assertStringContainsString('2026-11-08 10:00:00', $csv);
    }

    public function test_export_applies_filters(): void
    {
        $this->asAdmin();
        TrainingWaitlist::factory()->create(['name' => 'Keep', 'interest' => 'workshop']);
        TrainingWaitlist::factory()->create(['name' => 'Drop', 'interest' => 'inhouse']);

        $csv = $this->get('/api/admin/training-waitlist/export?interest=workshop')->streamedContent();
        $this->assertStringContainsString('Keep', $csv);
        $this->assertStringNotContainsString('Drop', $csv);
    }
}
