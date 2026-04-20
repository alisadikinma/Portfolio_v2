<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntityReferencesMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function entity_references_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('entity_references'),
            'Expected entity_references table to exist after migrations run'
        );
    }

    /** @test */
    public function entity_references_has_all_required_columns(): void
    {
        $expected = [
            'id',
            'qid',
            'name',
            'entity_type',
            'local_path',
            'local_url',
            'wikimedia_source_url',
            'license',
            'attribution',
            'source',
            'fetched_at',
            'last_used_at',
            'use_count',
            'refresh_after',
            'created_at',
            'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('entity_references', $column),
                "Expected entity_references.{$column} column to exist"
            );
        }
    }

    /** @test */
    public function content_ideas_has_pending_manifest_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('content_ideas', 'pending_manifest'),
            'Expected content_ideas.pending_manifest JSON column to exist after migration'
        );
    }
}
