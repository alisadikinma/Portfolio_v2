<?php

namespace App\Services;

use App\Models\EntityReference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EntityReferenceService
{
    /**
     * Licenses that are safe to use as AI reference images for commercial blog
     * output. Matched case-insensitively against the Commons LicenseShortName
     * metadata field. Excludes share-alike (CC-BY-SA) which would force the
     * AI-generated derivative to also be share-alike, and no-derivatives
     * (CC-BY-ND) which would block AI transformation entirely.
     */
    public const ALLOWED_LICENSES = [
        'cc0',
        'cc0 1.0',
        'public domain',
        'pd-usgov',
        'pd',
        'cc by 4.0',
        'cc-by-4.0',
        'cc by',
    ];

    /** Notability threshold — entity must appear on this many Wikipedia language editions. */
    public const MIN_SITELINKS = 5;

    private const QID_CACHE_TTL_DAYS = 30;
    private const USER_AGENT = 'alisadikinma.com-content-engine/1.0 (https://alisadikinma.com; ali.sadikincom85@gmail.com)';

    /**
     * Resolve an entity by name, returning its cached local reference image.
     * On cache miss, queries Wikidata + Commons, downloads the image if
     * license-compatible, and caches for reuse.
     *
     * Returns ['qid', 'name', 'entity_type', 'url', 'license', 'attribution']
     * or null when the entity is not notable, has no Wikimedia image, or its
     * image license is not in the allow-list.
     */
    public function findOrFetch(string $name, ?string $type = null): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $qid = $this->resolveWikidataQid($name);
        if ($qid === null) {
            return null;
        }

        $cached = EntityReference::where('qid', $qid)->first();
        if ($cached !== null) {
            $cached->incrementUseCount();
            return $this->toArray($cached);
        }

        // Cache miss — full fetch
        $wikidata = $this->fetchWikidataImage($name);
        if ($wikidata === null) {
            return null;
        }

        $entityType = $type ?? 'person';
        $commons = $this->fetchCommonsLicense($wikidata['p18_filename']);
        if ($commons === null) {
            return null;
        }

        if (!$this->isLicenseAllowed($commons['license'])) {
            Log::info('EntityReferenceService: rejected license', [
                'qid' => $qid,
                'name' => $name,
                'license' => $commons['license'],
            ]);
            return null;
        }

        $localUrl = $this->downloadAndStore(
            $commons['image_url'],
            $qid,
            $entityType,
            $name
        );

        if ($localUrl === null) {
            return null;
        }

        $record = EntityReference::create([
            'qid' => $qid,
            'name' => $name,
            'entity_type' => $entityType,
            'local_path' => $this->buildLocalPath($qid, $entityType, $name, $commons['image_url']),
            'local_url' => $localUrl,
            'wikimedia_source_url' => $commons['image_url'],
            'license' => $commons['license'],
            'attribution' => $commons['attribution'],
            'source' => 'wikimedia',
            'fetched_at' => now(),
        ]);

        return $this->toArray($record);
    }

    /**
     * Resolve an entity name to a Wikidata Q-ID using SPARQL label search
     * with sitelink-count filter for notability. Cached 30 days — name-to-QID
     * mapping is stable. Returns null when no notable match exists.
     */
    public function resolveWikidataQid(string $name): ?string
    {
        $cacheKey = 'wikidata_qid:' . Str::lower($name);

        return Cache::remember($cacheKey, now()->addDays(self::QID_CACHE_TTL_DAYS), function () use ($name) {
            $result = $this->fetchWikidataImage($name);
            return $result['qid'] ?? null;
        });
    }

    /**
     * Internal Wikidata SPARQL call. Returns ['qid', 'sitelinks', 'p18_filename']
     * or null when no notable match. Not cached directly — resolveWikidataQid
     * caches the extracted QID, and fetchWikidataImage is called again on
     * full-fetch path to re-extract p18 filename.
     */
    private function fetchWikidataImage(string $name): ?array
    {
        $escaped = str_replace('"', '\\"', $name);
        $sparql = <<<SPARQL
            SELECT ?item ?image (COUNT(DISTINCT ?sitelink) AS ?sitelinks) WHERE {
                ?item rdfs:label "{$escaped}"@en.
                ?item wdt:P18 ?image.
                ?sitelink schema:about ?item.
            }
            GROUP BY ?item ?image
            LIMIT 1
        SPARQL;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/sparql-results+json',
                ])
                ->get('https://query.wikidata.org/sparql', [
                    'query' => $sparql,
                    'format' => 'json',
                ]);

            if (!$response->successful()) {
                Log::warning('EntityReferenceService: Wikidata SPARQL non-200', [
                    'name' => $name,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $bindings = $response->json('results.bindings') ?? [];
            if (empty($bindings)) {
                return null;
            }

            $first = $bindings[0];
            $itemUri = $first['item']['value'] ?? '';
            $qid = $this->extractQid($itemUri);
            $sitelinks = (int) ($first['sitelinks']['value'] ?? 0);
            $imageUri = $first['image']['value'] ?? '';
            $p18Filename = $this->extractFilenameFromSpecialFilePath($imageUri);

            if ($qid === null || $p18Filename === null) {
                return null;
            }

            if ($sitelinks < self::MIN_SITELINKS) {
                Log::info('EntityReferenceService: rejected low notability', [
                    'qid' => $qid,
                    'name' => $name,
                    'sitelinks' => $sitelinks,
                ]);
                return null;
            }

            return [
                'qid' => $qid,
                'sitelinks' => $sitelinks,
                'p18_filename' => $p18Filename,
            ];
        } catch (\Throwable $e) {
            Log::warning('EntityReferenceService: Wikidata SPARQL exception', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch license + original URL for a Commons filename via MediaWiki API.
     * Returns ['license', 'attribution', 'image_url'] or null on failure.
     */
    public function fetchCommonsLicense(string $filename): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get('https://commons.wikimedia.org/w/api.php', [
                    'action' => 'query',
                    'prop' => 'imageinfo',
                    'iiprop' => 'url|extmetadata',
                    'titles' => 'File:' . $filename,
                    'format' => 'json',
                ]);

            if (!$response->successful()) {
                Log::warning('EntityReferenceService: Commons API non-200', [
                    'filename' => $filename,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $pages = $response->json('query.pages') ?? [];
            if (empty($pages)) {
                return null;
            }

            // Unknown page key — grab the first entry
            $page = array_values($pages)[0];
            $imageInfo = $page['imageinfo'][0] ?? null;
            if ($imageInfo === null) {
                return null;
            }

            $license = $imageInfo['extmetadata']['LicenseShortName']['value'] ?? null;
            $attribution = $imageInfo['extmetadata']['Artist']['value'] ?? null;
            $url = $imageInfo['url'] ?? null;

            if ($license === null || $url === null) {
                return null;
            }

            return [
                'license' => $license,
                'attribution' => $attribution,
                'image_url' => $url,
            ];
        } catch (\Throwable $e) {
            Log::warning('EntityReferenceService: Commons API exception', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function isLicenseAllowed(string $license): bool
    {
        $normalized = Str::lower(trim($license));
        foreach (self::ALLOWED_LICENSES as $allowed) {
            if (Str::startsWith($normalized, $allowed)) {
                // Explicit rejection for share-alike / no-derivatives variants
                // that could trip a startsWith 'cc by' match.
                if (Str::contains($normalized, ['sa', 'nc', 'nd'])) {
                    return false;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * A stored entity image is valid when it exists on the public disk with a
     * plausible size (>= 512 bytes — same floor the download path enforces).
     * Wrapped in try/catch because a transient FS/permission hiccup must read as
     * "not valid", never throw.
     */
    private function storedFileIsValid(string $relativePath): bool
    {
        try {
            return Storage::disk('public')->exists($relativePath)
                && Storage::disk('public')->size($relativePath) >= 512;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Download image bytes to public disk and return the served URL.
     * Returns null on any failure (network, empty body, storage write fail).
     */
    public function downloadAndStore(string $imageUrl, string $qid, string $type, string $name): ?string
    {
        $relativePath = $this->buildLocalPath($qid, $type, $name, $imageUrl);

        // Idempotent reuse: a prior fetch may have already written this file,
        // possibly in a DIFFERENT execution context (HTTP runs as www-data, the
        // queue worker runs as claudesn). Re-downloading would try to overwrite a
        // cross-owner file and fail the post-put exists() check ("file missing
        // after put"), returning null forever and never caching the DB row. If a
        // valid file is already on disk, reuse it — this call then persists the
        // EntityReference row so the entity is cached for good.
        if ($this->storedFileIsValid($relativePath)) {
            return url('/storage/' . $relativePath);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($imageUrl);

            if (!$response->successful()) {
                Log::warning('EntityReferenceService: image download non-200', [
                    'url' => $imageUrl,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $body = $response->body();
            if (empty($body) || strlen($body) < 512) {
                Log::warning('EntityReferenceService: image download empty/tiny', [
                    'url' => $imageUrl,
                    'bytes' => strlen($body),
                ]);
                return null;
            }

            Storage::disk('public')->put($relativePath, $body);

            // Trust the filesystem over Flysystem's cached view — a cross-owner
            // write can leave exists() momentarily false even when the bytes
            // landed; storedFileIsValid re-checks size directly on disk.
            if (!$this->storedFileIsValid($relativePath)) {
                Log::error('EntityReferenceService: file missing after put', [
                    'path' => $relativePath,
                ]);
                return null;
            }

            return url('/storage/' . $relativePath);
        } catch (\Throwable $e) {
            Log::warning('EntityReferenceService: downloadAndStore exception', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function buildLocalPath(string $qid, string $type, string $name, string $sourceUrl): string
    {
        $slug = Str::slug($name) ?: 'entity';
        $ext = $this->extensionFromUrl($sourceUrl);
        return "entity-refs/{$type}/{$qid}_{$slug}.{$ext}";
    }

    private function extensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        // Common extension normalization — we don't want .jpeg vs .jpg mixed
        return match ($ext) {
            'jpeg' => 'jpg',
            'jpg', 'png', 'gif', 'webp', 'svg' => $ext,
            default => 'jpg',
        };
    }

    private function extractQid(string $uri): ?string
    {
        if (preg_match('#/(Q\d+)$#', $uri, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractFilenameFromSpecialFilePath(string $uri): ?string
    {
        // Wikidata P18 images are exposed via
        // http://commons.wikimedia.org/wiki/Special:FilePath/{filename}
        if (preg_match('#Special:FilePath/(.+)$#', $uri, $m)) {
            return urldecode($m[1]);
        }
        return null;
    }

    private function toArray(EntityReference $record): array
    {
        return [
            'qid' => $record->qid,
            'name' => $record->name,
            'entity_type' => $record->entity_type,
            'url' => $record->local_url,
            'license' => $record->license,
            'attribution' => $record->attribution,
        ];
    }
}
