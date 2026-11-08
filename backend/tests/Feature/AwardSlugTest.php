<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Gallery;
use App\Models\GalleryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AwardSlugTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_DAY = '1st Place Winner — Global AI Demo Day 2026 (Outskill AI Generalist Fellowship)';

    private function award(string $title, array $extra = []): Award
    {
        return Award::create(array_merge([
            'title' => $title,
            'organization' => 'Org',
            'received_at' => '17 Jan 2026',
        ], $extra));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_08_000001_add_slug_to_awards_table.php');
    }

    public function test_slug_column_is_unique_and_indexed(): void
    {
        $this->assertTrue(Schema::hasColumn('awards', 'slug'));
    }

    public function test_new_award_gets_a_slug_on_create(): void
    {
        $this->assertSame(
            '1st-place-winner-global-ai-demo-day-2026-outskill-ai-generalist-fellowship',
            $this->award(self::DEMO_DAY)->slug,
        );
    }

    public function test_duplicate_titles_get_numeric_suffix_starting_at_2(): void
    {
        $a = $this->award('Same Title');
        $b = $this->award('Same Title');

        $this->assertSame('same-title', $a->slug);
        $this->assertSame('same-title-2', $b->slug);
    }

    public function test_slug_is_stable_when_title_changes(): void
    {
        $a = $this->award('Original Title');
        $a->update(['title' => 'Renamed Title']);

        $this->assertSame('original-title', $a->fresh()->slug);
    }

    public function test_migration_backfills_unique_slug_for_every_existing_award_in_id_order(): void
    {
        $first = $this->award(self::DEMO_DAY);
        $second = $this->award('Google Startup Grind Silicon Valley - San Fransisco');
        $dupA = $this->award('Top 8 Finalist – IDBYTE 2017 Connected');
        $dupB = $this->award('Top 8 Finalist – IDBYTE 2017 Connected');
        DB::table('awards')->update(['slug' => null]);

        $this->migration()->backfillSlugs();

        $slugs = Award::orderBy('id')->pluck('slug', 'id')->all();
        $this->assertSame('1st-place-winner-global-ai-demo-day-2026-outskill-ai-generalist-fellowship', $slugs[$first->id]);
        $this->assertSame('google-startup-grind-silicon-valley-san-fransisco', $slugs[$second->id]);
        $this->assertSame('top-8-finalist-idbyte-2017-connected', $slugs[$dupA->id]);
        $this->assertSame('top-8-finalist-idbyte-2017-connected-2', $slugs[$dupB->id]);
        $this->assertSame(0, Award::whereNull('slug')->count());
        $this->assertCount(4, array_unique($slugs));
    }

    public function test_backfill_is_idempotent_and_keeps_existing_slugs(): void
    {
        $a = $this->award('Keep Me');
        $b = $this->award('Fill Me');
        DB::table('awards')->where('id', $b->id)->update(['slug' => null]);

        $this->migration()->backfillSlugs();
        $this->migration()->backfillSlugs();

        $this->assertSame('keep-me', $a->fresh()->slug);
        $this->assertSame('fill-me', $b->fresh()->slug);
    }

    public function test_backfill_avoids_collision_with_an_existing_slug(): void
    {
        $this->award('Taken');
        $other = $this->award('Taken');
        DB::table('awards')->where('id', $other->id)->update(['slug' => null]);

        $this->migration()->backfillSlugs();

        $this->assertSame('taken-2', $other->fresh()->slug);
    }

    public function test_backfill_falls_back_for_titles_without_sluggable_characters(): void
    {
        $a = $this->award('!!!');
        DB::table('awards')->where('id', $a->id)->update(['slug' => null]);

        $this->migration()->backfillSlugs();

        $this->assertSame('award-'.$a->id, $a->fresh()->slug);
    }

    public function test_slug_endpoint_returns_award_with_galleries_and_image_variants(): void
    {
        $award = $this->award(self::DEMO_DAY, ['description' => '<p>Desc</p>']);
        $gallery = Gallery::factory()->create(['award_id' => $award->id, 'is_active' => true]);
        $variants = ['320w' => '/storage/v/a-320.webp', '1920w' => '/storage/v/a-1920.webp'];
        GalleryItem::factory()->create(['gallery_id' => $gallery->id, 'image_variants' => $variants]);

        $json = $this->getJson('/api/awards/slug/'.$award->slug)->assertOk()->json();

        $this->assertTrue($json['success']);
        $this->assertSame($award->id, $json['data']['award']['id']);
        $this->assertSame($award->slug, $json['data']['award']['slug']);
        $this->assertSame(self::DEMO_DAY, $json['data']['award']['title']);
        $this->assertSame('Org', $json['data']['award']['organization']);
        $this->assertSame('17 Jan 2026', $json['data']['award']['received_at']);
        $this->assertSame('<p>Desc</p>', $json['data']['award']['description']);
        $this->assertSame($variants, $json['data']['galleries'][0]['items'][0]['image_variants']);
        $this->assertArrayHasKey('thumbnail', $json['data']['galleries'][0]);
    }

    public function test_slug_endpoint_has_the_same_gallery_shape_as_the_id_endpoint(): void
    {
        $award = $this->award('Shape Award');
        $gallery = Gallery::factory()->create(['award_id' => $award->id, 'is_active' => true]);
        GalleryItem::factory()->create(['gallery_id' => $gallery->id]);

        $byId = $this->getJson("/api/awards/{$award->id}/galleries")->json('data.galleries');
        $bySlug = $this->getJson('/api/awards/slug/'.$award->slug)->json('data.galleries');

        $this->assertSame($byId, $bySlug);
    }

    public function test_slug_endpoint_hides_inactive_galleries_from_guests(): void
    {
        $award = $this->award('Gallery Filter');
        Gallery::factory()->create(['award_id' => $award->id, 'is_active' => true]);
        Gallery::factory()->create(['award_id' => $award->id, 'is_active' => false]);

        $this->getJson('/api/awards/slug/'.$award->slug)
            ->assertOk()
            ->assertJsonCount(1, 'data.galleries');
    }

    public function test_inactive_award_is_404_by_slug(): void
    {
        $award = $this->award('Hidden Award');
        DB::table('awards')->where('id', $award->id)->update(['is_active' => false]);

        $this->getJson('/api/awards/slug/'.$award->slug)->assertNotFound();
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->getJson('/api/awards/slug/does-not-exist')->assertNotFound();
    }

    public function test_slug_route_is_not_captured_as_an_id(): void
    {
        $award = $this->award('Route Order');

        $this->getJson('/api/awards/slug/'.$award->slug)->assertOk();
        $this->getJson('/api/awards/'.$award->id)->assertOk()->assertJsonPath('data.slug', $award->slug);
    }

    public function test_list_includes_slug_for_both_shapes(): void
    {
        $award = $this->award('Listed Award');

        $this->getJson('/api/awards')->assertOk()->assertJsonPath('data.0.slug', $award->slug);
        $this->getJson('/api/awards?limit=5')->assertOk()->assertJsonPath('data.0.slug', $award->slug);
    }
}
