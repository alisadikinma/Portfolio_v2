<?php

namespace Tests\Unit;

use App\Models\EntityReference;
use App\Services\EntityReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntityReferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function find_or_fetch_returns_cached_row_when_qid_exists_without_http_calls(): void
    {
        EntityReference::create([
            'qid' => 'Q115468560',
            'name' => 'Dario Amodei',
            'entity_type' => 'person',
            'local_path' => 'entity-refs/person/Q115468560_dario-amodei.jpg',
            'local_url' => 'https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario-amodei.jpg',
            'license' => 'CC-BY-4.0',
            'source' => 'wikimedia',
            'fetched_at' => now(),
        ]);

        // Fake Http so we can assert zero outbound calls on cache hit.
        Http::fake();

        // Seed the Wikidata name→QID cache directly so we skip SPARQL.
        cache()->put('wikidata_qid:dario amodei', 'Q115468560', now()->addDays(30));

        /** @var EntityReferenceService $service */
        $service = app(EntityReferenceService::class);

        $result = $service->findOrFetch('Dario Amodei', 'person');

        $this->assertNotNull($result);
        $this->assertSame('Q115468560', $result['qid']);
        $this->assertSame('Dario Amodei', $result['name']);
        $this->assertSame('person', $result['entity_type']);
        $this->assertStringContainsString('Q115468560_dario-amodei.jpg', $result['url']);

        Http::assertNothingSent();

        // use_count should have been bumped from 1 → 2
        $this->assertSame(2, EntityReference::where('qid', 'Q115468560')->value('use_count'));
    }

    /** @test */
    public function find_or_fetch_rejects_cc_by_sa_license_and_does_not_insert_row(): void
    {
        $this->mockWikidataSparql('Dario Amodei', 'Q115468560', sitelinks: 12, p18: 'Dario.jpg');
        $this->mockCommonsImageInfo('Dario.jpg', license: 'CC BY-SA 4.0', imageUrl: 'https://example.com/dario.jpg');

        /** @var EntityReferenceService $service */
        $service = app(EntityReferenceService::class);

        $result = $service->findOrFetch('Dario Amodei', 'person');

        $this->assertNull($result);
        $this->assertSame(0, EntityReference::count());
    }

    /** @test */
    public function find_or_fetch_accepts_whitelisted_licenses(): void
    {
        $this->mockWikidataSparql('White House', 'Q35525', sitelinks: 60, p18: 'WhiteHouse.jpg');
        $this->mockCommonsImageInfo('WhiteHouse.jpg', license: 'PD-USGov', imageUrl: 'https://example.com/wh.jpg');
        $this->mockImageDownload('https://example.com/wh.jpg');

        /** @var EntityReferenceService $service */
        $service = app(EntityReferenceService::class);

        $result = $service->findOrFetch('White House', 'landmark');

        $this->assertNotNull($result);
        $this->assertSame('Q35525', $result['qid']);
        $this->assertSame('PD-USGov', $result['license']);
        $this->assertSame(1, EntityReference::where('qid', 'Q35525')->count());
    }

    /** @test */
    public function find_or_fetch_rejects_low_notability_entities(): void
    {
        // Only 2 sitelinks — below notability threshold of 5
        $this->mockWikidataSparql('Some Random Person', 'Q99999999', sitelinks: 2, p18: 'random.jpg');

        /** @var EntityReferenceService $service */
        $service = app(EntityReferenceService::class);

        $result = $service->findOrFetch('Some Random Person', 'person');

        $this->assertNull($result);
        $this->assertSame(0, EntityReference::count());
    }

    /** @test */
    public function find_or_fetch_returns_null_when_wikidata_has_no_match(): void
    {
        $this->mockEmptyWikidataSparql();

        /** @var EntityReferenceService $service */
        $service = app(EntityReferenceService::class);

        $result = $service->findOrFetch('Totally Unknown Name', 'person');

        $this->assertNull($result);
        $this->assertSame(0, EntityReference::count());
    }

    private function mockWikidataSparql(string $name, string $qid, int $sitelinks, string $p18): void
    {
        Http::fake([
            'query.wikidata.org/sparql*' => Http::response([
                'results' => [
                    'bindings' => [
                        [
                            'item' => ['value' => "http://www.wikidata.org/entity/{$qid}"],
                            'itemLabel' => ['value' => $name],
                            'image' => ['value' => "http://commons.wikimedia.org/wiki/Special:FilePath/{$p18}"],
                            'sitelinks' => ['value' => (string) $sitelinks],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function mockEmptyWikidataSparql(): void
    {
        Http::fake([
            'query.wikidata.org/sparql*' => Http::response([
                'results' => ['bindings' => []],
            ], 200),
        ]);
    }

    private function mockCommonsImageInfo(string $filename, string $license, string $imageUrl): void
    {
        Http::fake([
            'commons.wikimedia.org/w/api.php*' => Http::response([
                'query' => [
                    'pages' => [
                        '123' => [
                            'title' => "File:{$filename}",
                            'imageinfo' => [
                                [
                                    'url' => $imageUrl,
                                    'extmetadata' => [
                                        'LicenseShortName' => ['value' => $license],
                                        'Artist' => ['value' => 'Test Author'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function mockImageDownload(string $url): void
    {
        Http::fake([
            $url => Http::response(str_repeat('fake-png-bytes', 200), 200),
        ]);
    }
}
