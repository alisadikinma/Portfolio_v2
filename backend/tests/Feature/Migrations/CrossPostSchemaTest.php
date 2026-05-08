<?php

namespace Tests\Feature\Migrations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase A schema verification — cross-post pipeline tables.
 *
 * Asserts shape of instagram_posts + tiktok_posts after migrations apply.
 * Does NOT use RefreshDatabase trait — relies on the standard test DB having
 * migrations already applied (CLAUDE.md May 5 entry notes RefreshDatabase is
 * flaky on this dev env due to MySQL tablespace issues).
 *
 * Run: php artisan test --filter=CrossPostSchemaTest
 */
class CrossPostSchemaTest extends TestCase
{
    public function test_instagram_posts_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('instagram_posts'),
            'instagram_posts table missing — run php artisan migrate'
        );
    }

    public function test_tiktok_posts_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('tiktok_posts'),
            'tiktok_posts table missing — run php artisan migrate'
        );
    }

    public function test_instagram_posts_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('instagram_posts', [
            'id',
            'linkedin_post_id',
            'post_id',
            'status',
            'title',
            'caption',
            'hashtags',
            'scheduled_at',
            'published_at',
            'external_url',
            'last_error',
            'pipeline_state_log',
            'created_by_user_id',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_tiktok_posts_has_required_columns(): void
    {
        // Same columns as instagram_posts PLUS music_suggestion
        $this->assertTrue(Schema::hasColumns('tiktok_posts', [
            'id',
            'linkedin_post_id',
            'post_id',
            'status',
            'title',
            'caption',
            'hashtags',
            'music_suggestion',
            'scheduled_at',
            'published_at',
            'external_url',
            'last_error',
            'pipeline_state_log',
            'created_by_user_id',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_instagram_posts_status_default_is_pending_generation(): void
    {
        // Insert minimum-required-columns row, read back default status
        $userId = DB::table('users')->insertGetId([
            'name' => 'Schema Test User',
            'email' => 'schema-test-ig-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $postId = DB::table('posts')->insertGetId([
            'slug' => 'schema-test-ig-' . uniqid(),
            'category_id' => DB::table('blog_categories')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('instagram_posts')->insertGetId([
            'post_id' => $postId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('instagram_posts')->find($id);
        $this->assertSame('pending_generation', $row->status);

        // Cleanup (no RefreshDatabase trait)
        DB::table('instagram_posts')->where('id', $id)->delete();
        DB::table('posts')->where('id', $postId)->delete();
        DB::table('users')->where('id', $userId)->delete();
    }

    public function test_tiktok_posts_status_default_is_pending_generation(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Schema Test User',
            'email' => 'schema-test-tt-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $postId = DB::table('posts')->insertGetId([
            'slug' => 'schema-test-tt-' . uniqid(),
            'category_id' => DB::table('blog_categories')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('tiktok_posts')->insertGetId([
            'post_id' => $postId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('tiktok_posts')->find($id);
        $this->assertSame('pending_generation', $row->status);

        DB::table('tiktok_posts')->where('id', $id)->delete();
        DB::table('posts')->where('id', $postId)->delete();
        DB::table('users')->where('id', $userId)->delete();
    }

    public function test_instagram_posts_indexes_present(): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM instagram_posts"))
            ->pluck('Key_name')
            ->unique()
            ->values();

        $this->assertTrue($indexes->contains('idx_instagram_post_status'));
        $this->assertTrue($indexes->contains('idx_instagram_post_scheduled'));
    }

    public function test_tiktok_posts_indexes_present(): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM tiktok_posts"))
            ->pluck('Key_name')
            ->unique()
            ->values();

        $this->assertTrue($indexes->contains('idx_tiktok_post_status'));
        $this->assertTrue($indexes->contains('idx_tiktok_post_scheduled'));
    }

    public function test_foreign_keys_cascade_correctly(): void
    {
        // post_id ON DELETE CASCADE — deleting a post should remove instagram_posts row
        $userId = DB::table('users')->insertGetId([
            'name' => 'FK Test',
            'email' => 'fk-test-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $postId = DB::table('posts')->insertGetId([
            'slug' => 'fk-test-' . uniqid(),
            'category_id' => DB::table('blog_categories')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $igId = DB::table('instagram_posts')->insertGetId([
            'post_id' => $postId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tkId = DB::table('tiktok_posts')->insertGetId([
            'post_id' => $postId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Hard-delete the post — cross-post rows should cascade away
        DB::table('posts')->where('id', $postId)->delete();

        $this->assertNull(DB::table('instagram_posts')->find($igId), 'instagram_posts row should cascade-delete with post');
        $this->assertNull(DB::table('tiktok_posts')->find($tkId), 'tiktok_posts row should cascade-delete with post');

        DB::table('users')->where('id', $userId)->delete();
    }
}
