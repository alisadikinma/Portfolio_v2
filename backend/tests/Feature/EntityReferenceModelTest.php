<?php

namespace Tests\Feature;

use App\Models\EntityReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityReferenceModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_create_entity_reference_record(): void
    {
        $ref = EntityReference::create([
            'qid' => 'Q115468560',
            'name' => 'Dario Amodei',
            'entity_type' => 'person',
            'local_path' => 'entity-refs/person/Q115468560_dario-amodei.jpg',
            'local_url' => 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario-amodei.jpg',
            'wikimedia_source_url' => 'https://commons.wikimedia.org/wiki/File:Dario.jpg',
            'license' => 'CC-BY-4.0',
            'attribution' => '© TechCrunch via Wikimedia Commons',
            'source' => 'wikimedia',
            'fetched_at' => now(),
        ]);

        $this->assertNotNull($ref->id);
        $this->assertSame('Q115468560', $ref->qid);
        $this->assertSame('person', $ref->entity_type);
        $this->assertSame(1, $ref->use_count);
    }

    /** @test */
    public function increment_use_count_bumps_counter_and_touches_last_used_at(): void
    {
        $ref = EntityReference::create([
            'qid' => 'Q35525',
            'name' => 'White House',
            'entity_type' => 'landmark',
            'local_path' => 'entity-refs/landmark/Q35525_white-house.jpg',
            'local_url' => 'https://alisadikinma.com/storage/entity-refs/landmark/Q35525_white-house.jpg',
            'license' => 'PD-USGov',
            'source' => 'wikimedia',
            'fetched_at' => now()->subDay(),
        ]);

        $this->assertNull($ref->last_used_at);
        $this->assertSame(1, $ref->use_count);

        $ref->incrementUseCount();
        $ref->refresh();

        $this->assertSame(2, $ref->use_count);
        $this->assertNotNull($ref->last_used_at);
        $this->assertTrue($ref->last_used_at->isToday());
    }

    /** @test */
    public function qid_is_unique(): void
    {
        EntityReference::create([
            'qid' => 'Q317521',
            'name' => 'Elon Musk',
            'entity_type' => 'person',
            'local_path' => 'entity-refs/person/Q317521_elon.jpg',
            'local_url' => 'https://example.com/elon.jpg',
            'license' => 'CC-BY-4.0',
            'source' => 'wikimedia',
            'fetched_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EntityReference::create([
            'qid' => 'Q317521',
            'name' => 'Elon Musk (duplicate)',
            'entity_type' => 'person',
            'local_path' => 'other/path.jpg',
            'local_url' => 'https://example.com/elon2.jpg',
            'license' => 'CC-BY-4.0',
            'source' => 'wikimedia',
            'fetched_at' => now(),
        ]);
    }

    /** @test */
    public function user_upload_source_allows_null_qid(): void
    {
        $ref = EntityReference::create([
            'qid' => null,
            'name' => 'Dario Amodei',
            'entity_type' => 'person',
            'local_path' => 'entity-refs/person/user_upload.jpg',
            'local_url' => 'https://example.com/upload.jpg',
            'license' => 'USER-UPLOADED',
            'source' => 'user_upload',
            'fetched_at' => now(),
        ]);

        $this->assertNotNull($ref->id);
        $this->assertNull($ref->qid);
        $this->assertSame('user_upload', $ref->source);
    }
}
