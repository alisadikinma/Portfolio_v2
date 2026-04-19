<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Multi-source trending topic aggregator.
 *
 * Sources (same as SparkFluence platform):
 * 1. Google Trends RSS — daily trending searches
 * 2. TikTok Creative Center — viral hashtags
 * 3. YouTube Trending (Piped API) — trending video topics
 * 4. Google News RSS — breaking tech headlines
 *
 * All results are filtered for AI/tech relevance and deduplicated
 * against existing blog posts.
 */
class TrendingTopicService
{
    /**
     * Blog focus: AI tools & ecosystem
     * Topics: Vibe Coding, AI Automation, AI Agents, AI Image/Video Gen, LLMs
     */
    private array $techKeywords = [
        // Vibe Coding Tools
        'vibe coding', 'cursor', 'windsurf', 'claude code', 'copilot', 'codeium',
        'v0', 'bolt', 'lovable', 'replit', 'devin', 'aide', 'trae',
        'coding assistant', 'ai coding', 'ai ide', 'code generation',

        // AI Agents
        'ai agent', 'openclaw', 'open claw', 'mcp', 'model context protocol',
        'managed agents', 'autonomous agent', 'agentic', 'crew ai', 'crewai',
        'autogen', 'langchain', 'langgraph', 'agent framework',

        // AI Automation
        'ai automation', 'n8n', 'make.com', 'zapier ai', 'workflow automation',
        'rpa', 'ai pipeline', 'prompt engineering', 'ai workflow',

        // LLMs & AI Companies
        'claude', 'anthropic', 'chatgpt', 'openai', 'gemini', 'gpt',
        'llm', 'large language model', 'grok', 'mistral', 'llama',
        'deepseek', 'qwen', 'phi', 'sonnet', 'opus', 'haiku',

        // AI Image Generation
        'midjourney', 'dall-e', 'stable diffusion', 'flux', 'imagen',
        'nano banana', 'ai image', 'image generation', 'text to image',
        'comfyui', 'automatic1111', 'fooocus', 'seedream',

        // AI Video Generation
        'sora', 'runway', 'pika', 'kling', 'veo', 'wan',
        'ai video', 'video generation', 'text to video',
        'luma', 'minimax', 'hailuo',

        // General AI/ML
        'artificial intelligence', 'machine learning', 'deep learning',
        'transformer', 'fine-tuning', 'rag', 'neural', 'diffusion',
        'ai tool', 'ai app', 'generative ai', 'gen ai',
    ];

    /**
     * Aggregate trends from all sources, filter, dedup, return best topic.
     */
    public function getBestTopic(): ?array
    {
        $allTrends = [];

        // Fetch from all sources in parallel-ish (sequential but fast)
        $allTrends = array_merge($allTrends, $this->fetchGoogleTrends());
        $allTrends = array_merge($allTrends, $this->fetchTikTokTrending());
        $allTrends = array_merge($allTrends, $this->fetchYouTubeTrending());
        $allTrends = array_merge($allTrends, $this->fetchGoogleNews());

        Log::info("[TrendingTopic] Fetched " . count($allTrends) . " total trends from all sources.");

        if (empty($allTrends)) {
            return null;
        }

        // Filter for tech/AI relevance
        $techTrends = $this->filterTechTopics($allTrends);
        Log::info("[TrendingTopic] " . count($techTrends) . " tech-related trends after filtering.");

        if (empty($techTrends)) {
            return null;
        }

        // Sort by score descending
        usort($techTrends, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        // Deduplicate against existing posts
        foreach ($techTrends as $trend) {
            if (!$this->isDuplicate($trend['title'])) {
                Log::info("[TrendingTopic] Selected: \"{$trend['title']}\" from {$trend['source']}");
                return $trend;
            }
        }

        Log::info("[TrendingTopic] All tech trends are duplicates.");
        return null;
    }

    // ========================================================================
    // SOURCE 1: Google Trends RSS
    // ========================================================================

    private function fetchGoogleTrends(): array
    {
        // Google deprecated the /trending/rss feed in late 2024.
        // This uses the internal /trends/api/dailytrends JSON endpoint
        // (stable since ~2019). Response prefixed with )]}' for anti-hijack —
        // must strip before json_decode.
        $regions = [
            ['geo' => 'US', 'hl' => 'en-US'],
            ['geo' => 'ID', 'hl' => 'id'],
        ];

        $results = [];

        foreach ($regions as $region) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get('https://trends.google.com/trends/api/dailytrends', [
                        'hl' => $region['hl'],
                        'tz' => -480,
                        'geo' => $region['geo'],
                        'ns' => 15,
                    ]);

                if (!$response->successful()) {
                    Log::warning("[TrendingTopic] Google Trends {$region['geo']}: HTTP {$response->status()}");
                    continue;
                }

                $body = preg_replace('/^\)\]\}\',?\s*/', '', $response->body());
                $data = json_decode($body, true);
                if (!is_array($data)) {
                    Log::warning("[TrendingTopic] Google Trends {$region['geo']}: malformed JSON");
                    continue;
                }

                $days = data_get($data, 'default.trendingSearchesDays', []);
                foreach ($days as $day) {
                    $pubDate = $this->parseGoogleTrendsDate($day['date'] ?? null);
                    foreach ($day['trendingSearches'] ?? [] as $search) {
                        $title = data_get($search, 'title.query');
                        if (empty($title)) continue;

                        $results[] = [
                            'title' => $title,
                            'description' => trim(data_get($search, 'formattedTraffic', '') . ' searches'),
                            'source' => 'google_trends',
                            'country' => $region['geo'],
                            'score' => 70,
                            'pub_date' => $pubDate,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[TrendingTopic] Google Trends {$region['geo']}: {$e->getMessage()}");
            }
        }

        Log::info("[TrendingTopic] Google Trends: " . count($results) . " items");
        return $results;
    }

    private function parseGoogleTrendsDate(?string $yyyymmdd): ?string
    {
        if (!$yyyymmdd || strlen($yyyymmdd) !== 8) return null;
        try {
            return \Carbon\Carbon::createFromFormat('Ymd', $yyyymmdd)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    // ========================================================================
    // SOURCE 2: TikTok Creative Center (viral hashtags)
    // ========================================================================

    private function fetchTikTokTrending(): array
    {
        $results = [];

        try {
            // Strategy: Visit Creative Center page first for cookies, then API
            $client = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ]);

            // Step 1: Visit page to get cookies
            $pageUrl = 'https://ads.tiktok.com/business/creativecenter/inspiration/popular/hashtag/pc/en';
            $pageResp = $client->get($pageUrl);

            if (!$pageResp->successful()) {
                Log::warning("[TrendingTopic] TikTok page returned HTTP {$pageResp->status()}");
                return [];
            }

            // Step 2: Extract trending data from page HTML (dehydrated state)
            $html = $pageResp->body();
            if (preg_match('/"hashtag_name"\s*:\s*"([^"]+)"/', $html, $matches)) {
                // Parse all hashtags from dehydrated state
                preg_match_all('/"hashtag_name"\s*:\s*"([^"]+)"/', $html, $allMatches);

                foreach (array_unique($allMatches[1]) as $hashtag) {
                    $hashtag = trim($hashtag, '#');
                    if (strlen($hashtag) < 3) continue;

                    $results[] = [
                        'title' => $hashtag,
                        'description' => "Trending TikTok hashtag: #{$hashtag}",
                        'source' => 'tiktok',
                        'country' => 'global',
                        'score' => 80, // TikTok trends are high engagement
                    ];
                }
            }

            // Also try direct API call
            if (empty($results)) {
                $apiResp = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'application/json',
                        'Referer' => $pageUrl,
                    ])
                    ->get('https://ads.tiktok.com/creative_radar_api/v1/popular_trend/hashtag/list', [
                        'page' => 1,
                        'limit' => 30,
                        'period' => 7,
                        'country_code' => 'ID',
                        'sort_by' => 'popular',
                    ]);

                if ($apiResp->successful()) {
                    $data = $apiResp->json();
                    foreach (($data['data']['list'] ?? []) as $item) {
                        $kw = trim($item['hashtag_name'] ?? '', '#');
                        if (strlen($kw) < 3) continue;
                        $views = $item['publish_cnt'] ?? $item['video_views'] ?? 0;

                        $results[] = [
                            'title' => $kw,
                            'description' => "TikTok: #{$kw} ({$views} views)",
                            'source' => 'tiktok',
                            'country' => 'ID',
                            'score' => min(95, max(50, 50 + intval($views / 1000000))),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("[TrendingTopic] TikTok: {$e->getMessage()}");
        }

        Log::info("[TrendingTopic] TikTok: " . count($results) . " items");
        return $results;
    }

    // ========================================================================
    // SOURCE 3: YouTube Trending (Piped API)
    // ========================================================================

    private function fetchYouTubeTrending(): array
    {
        $instances = [
            'https://pipedapi.kavin.rocks',
            'https://pipedapi-libre.kavin.rocks',
            'https://pipedapi.nosebs.ru',
        ];

        $results = [];

        foreach ($instances as $baseUrl) {
            try {
                $response = Http::timeout(10)->get("{$baseUrl}/trending", [
                    'region' => 'ID',
                ]);

                if (!$response->successful()) continue;

                $videos = $response->json();
                if (!is_array($videos) || empty($videos)) continue;

                foreach (array_slice($videos, 0, 20) as $video) {
                    $title = $video['title'] ?? '';
                    if (empty($title)) continue;

                    $views = $video['views'] ?? 0;

                    $results[] = [
                        'title' => $title,
                        'description' => ($video['uploaderName'] ?? '') . ': ' . Str::limit($title, 100),
                        'source' => 'youtube',
                        'country' => 'ID',
                        'score' => min(90, max(40, 40 + intval($views / 100000))),
                    ];
                }

                Log::info("[TrendingTopic] YouTube via {$baseUrl}: " . count($results) . " items");
                break; // Success — don't try other instances

            } catch (\Exception $e) {
                Log::warning("[TrendingTopic] YouTube via {$baseUrl}: {$e->getMessage()}");
                continue;
            }
        }

        return $results;
    }

    // ========================================================================
    // SOURCE 4: Google News RSS (tech section)
    // ========================================================================

    private function fetchGoogleNews(): array
    {
        $feeds = [
            // AI-specific search feeds (not generic "Technology" topic)
            'https://news.google.com/rss/search?q=artificial+intelligence+OR+AI+agents+OR+LLM+OR+ChatGPT+OR+Claude+AI+OR+Gemini+AI&hl=en&gl=US&ceid=US:en',
            'https://news.google.com/rss/search?q=vibe+coding+OR+AI+automation+OR+AI+video+generation+OR+generative+AI&hl=en&gl=US&ceid=US:en',
            // Claude-product launches — narrow quoted names to keep signal high.
            // A wide OR query here dilutes results; Google News RSS caps ~100 items
            // per feed, so every extra OR disjunct (esp. broad terms like Anthropic
            // or OpenAI) pushes product-launch hits off the list.
            'https://news.google.com/rss/search?q=%22Claude+Design%22+OR+%22Claude+Skills%22+OR+%22Claude+Agents%22+OR+%22Claude+Apps%22+OR+%22Claude+Code%22&hl=en&gl=US&ceid=US:en',
            // Other AI-vendor product launches (kept separate to preserve Claude feed signal)
            'https://news.google.com/rss/search?q=%22GPT-5%22+OR+%22Gemini+3%22+OR+%22Gemini+Ultra%22+OR+Grok+OR+xAI&hl=en&gl=US&ceid=US:en',
            // Indonesian AI news
            'https://news.google.com/rss/search?q=kecerdasan+buatan+OR+AI+Indonesia+OR+ChatGPT+OR+otomatisasi+AI&hl=id&gl=ID&ceid=ID:id',
        ];

        $results = [];

        foreach ($feeds as $feedUrl) {
            try {
                $response = Http::timeout(10)->get($feedUrl);
                if (!$response->successful()) continue;

                $items = $this->parseRss($response->body());
                foreach ($items as &$item) {
                    // Publisher now comes from <source> element via parseRss; still strip
                    // the " - Publisher" suffix from the display title for readability.
                    $item['title'] = preg_replace('/\s*-\s*[^-]{2,40}$/', '', $item['title']);
                    $item['source'] = 'google_news';
                    $item['score'] = 75; // News is timely and authoritative
                    $item['publisher_tier'] = $this->classifyPublisherTier($item['publisher'] ?? '');
                }
                $results = array_merge($results, $items);
            } catch (\Exception $e) {
                Log::warning("[TrendingTopic] Google News: {$e->getMessage()}");
            }
        }

        Log::info("[TrendingTopic] Google News: " . count($results) . " items");
        return $results;
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function parseRss(string $xml): array
    {
        $items = [];

        try {
            $feed = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$feed || !isset($feed->channel->item)) return [];

            foreach ($feed->channel->item as $item) {
                $title = trim((string) $item->title);
                if (empty($title)) continue;

                // Google News RSS includes <source url="...">Publisher</source> — clean extraction
                $publisher = isset($item->source) ? trim((string) $item->source) : '';
                if ($publisher === '') {
                    // Fallback: parse trailing " - Publisher" from title
                    if (preg_match('/\s-\s([^-]{2,40})$/', $title, $m)) {
                        $publisher = trim($m[1]);
                    }
                }

                $items[] = [
                    'title' => $title,
                    'description' => trim((string) ($item->description ?? '')),
                    'link' => trim((string) ($item->link ?? '')),
                    'pub_date' => trim((string) ($item->pubDate ?? '')),
                    'publisher' => $publisher,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("[TrendingTopic] RSS parse error: {$e->getMessage()}");
        }

        return $items;
    }

    // ------------------------------------------------------------------------
    // Publisher trust tiers — used to filter + weight virality scoring.
    // Keys match against case-insensitive `contains` on publisher string.
    // ------------------------------------------------------------------------
    private const TIER_1_PUBLISHERS = [
        'reuters', 'bloomberg', 'wall street journal', 'wsj', 'new york times', 'nyt',
        'bbc', 'associated press', 'ap news', 'financial times', 'the economist',
        'techcrunch', 'wired', 'the verge', 'ars technica', 'mit technology review',
        'cnbc', 'forbes', 'the guardian', 'washington post', 'axios',
    ];

    private const TIER_2_PUBLISHERS = [
        'mashable', 'engadget', 'zdnet', 'business insider', 'fast company',
        '9to5mac', '9to5google', 'the information', 'venturebeat', 'techradar',
        'cnet', 'pcmag', 'siliconangle', 'decrypt', 'semafor', 'protocol',
        'anthropic', 'openai', 'google', 'microsoft', 'amazon', // official vendor blogs
        'kompas', 'detik', 'cnn indonesia', 'liputan6', 'tempo', 'tribun',
    ];

    private function classifyPublisherTier(string $publisher): int
    {
        $lower = strtolower($publisher);
        if ($lower === '') return 3;
        foreach (self::TIER_1_PUBLISHERS as $p) if (str_contains($lower, $p)) return 1;
        foreach (self::TIER_2_PUBLISHERS as $p) if (str_contains($lower, $p)) return 2;
        return 3;
    }

    /**
     * Group trends with ≥60% overlapping significant-word sets. Picks the
     * highest-tier publisher's title as canonical, sums distinct publishers,
     * and records the publisher list so the UI can show "covered by 4 sources".
     */
    private function dedupAndRank(array $trends): array
    {
        // Stable ordering: tier asc then date desc so tier-1 wins canonical title.
        usort($trends, function ($a, $b) {
            $tierDiff = ($a['publisher_tier'] ?? 3) <=> ($b['publisher_tier'] ?? 3);
            if ($tierDiff !== 0) return $tierDiff;
            $aTs = strtotime($a['pub_date'] ?? '') ?: 0;
            $bTs = strtotime($b['pub_date'] ?? '') ?: 0;
            return $bTs <=> $aTs;
        });

        $tokenize = function (string $title): array {
            $clean = preg_replace('/[^a-z0-9\s]/i', ' ', strtolower($title));
            $words = array_filter(explode(' ', $clean), fn($w) => strlen($w) > 3);
            return array_values(array_unique($words));
        };

        $clusters = [];
        foreach ($trends as $t) {
            $tokens = $tokenize($t['title'] ?? '');
            if (empty($tokens)) continue;
            $merged = false;
            foreach ($clusters as $ci => &$cluster) {
                $intersect = count(array_intersect($tokens, $cluster['tokens']));
                $smaller = min(count($tokens), count($cluster['tokens'])) ?: 1;
                if ($intersect / $smaller >= 0.6) {
                    $cluster['publishers'][] = $t['publisher'] ?? '';
                    $cluster['tiers'][] = $t['publisher_tier'] ?? 3;
                    $cluster['pub_dates'][] = $t['pub_date'] ?? '';
                    $merged = true;
                    break;
                }
            }
            unset($cluster);
            if (!$merged) {
                $clusters[] = [
                    'canonical' => $t,
                    'tokens' => $tokens,
                    'publishers' => [$t['publisher'] ?? ''],
                    'tiers' => [$t['publisher_tier'] ?? 3],
                    'pub_dates' => [$t['pub_date'] ?? ''],
                ];
            }
        }

        $now = time();
        $output = [];
        foreach ($clusters as $c) {
            $canon = $c['canonical'];
            // Only count distinct trusted publishers (tier 1+2) for heat.
            $trustedPublishers = [];
            foreach ($c['publishers'] as $idx => $pub) {
                if (($c['tiers'][$idx] ?? 3) <= 2 && $pub !== '') {
                    $trustedPublishers[strtolower($pub)] = $pub;
                }
            }
            $trustedCount = count($trustedPublishers);

            // Hottest age across all reports in the cluster (lowest hours).
            $minAgeH = PHP_INT_MAX;
            foreach ($c['pub_dates'] as $d) {
                $ts = strtotime($d) ?: 0;
                if ($ts > 0) {
                    $ageH = ($now - $ts) / 3600;
                    if ($ageH < $minAgeH) $minAgeH = $ageH;
                }
            }
            if ($minAgeH === PHP_INT_MAX) $minAgeH = 9999;

            // Heat: 🔥 HOT = 3+ trusted in 24h; 📈 TRENDING = 2 publishers in 12h
            $allPublishers = array_filter($c['publishers'], fn($p) => $p !== '');
            $allCount = count(array_unique(array_map('strtolower', $allPublishers)));
            if ($trustedCount >= 3 && $minAgeH <= 24) {
                $heat = 'hot';
            } elseif ($allCount >= 2 && $minAgeH <= 12) {
                $heat = 'trending';
            } else {
                $heat = 'standard';
            }

            $canon['publisher_count'] = $allCount;
            $canon['trusted_publisher_count'] = $trustedCount;
            $canon['heat'] = $heat;
            $canon['age_hours'] = round($minAgeH, 1);
            // Boost score by heat + trust so downstream sort surfaces best first.
            $heatBoost = $heat === 'hot' ? 40 : ($heat === 'trending' ? 20 : 0);
            $tierBoost = (4 - ($canon['publisher_tier'] ?? 3)) * 10;
            $canon['score'] = ($canon['score'] ?? 50) + $heatBoost + $tierBoost;
            $output[] = $canon;
        }

        return $output;
    }

    private function filterTechTopics(array $trends): array
    {
        $filtered = [];
        $seen = []; // Dedup by normalized title

        foreach ($trends as $trend) {
            $title = strtolower($trend['title']);
            $normalized = Str::slug($trend['title']);

            // Skip if we've seen a very similar title
            if (isset($seen[$normalized])) continue;

            // Match against TITLE ONLY (descriptions are too noisy — contain ads, unrelated HTML)
            foreach ($this->techKeywords as $keyword) {
                if (str_contains($title, $keyword)) {
                    $trend['matched_keyword'] = $keyword;
                    $filtered[] = $trend;
                    $seen[$normalized] = true;
                    break;
                }
            }
        }

        return $filtered;
    }

    private function isDuplicate(string $title): bool
    {
        $slug = Str::slug($title);
        $titleLower = strtolower($title);

        // 1. Exact slug match (any post, including trashed)
        if (Post::withTrashed()->where('slug', $slug)->exists()) return true;

        // 2. Title similarity against ALL post translations (not just recent 50)
        $allTitles = \App\Models\PostTranslation::select('title')
            ->pluck('title');

        foreach ($allTitles as $existing) {
            similar_text($titleLower, strtolower($existing), $percent);
            if ($percent > 65) return true;
        }

        // 3. Keyword overlap check — if 3+ significant words match, likely same topic
        $titleWords = collect(explode(' ', $titleLower))
            ->filter(fn($w) => strlen($w) > 3) // skip short words
            ->values();

        foreach ($allTitles as $existing) {
            $existingWords = collect(explode(' ', strtolower($existing)))
                ->filter(fn($w) => strlen($w) > 3);

            $overlap = $titleWords->intersect($existingWords)->count();
            if ($overlap >= 3 && $overlap >= $titleWords->count() * 0.5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return all tech-filtered trending topics from all sources.
     * Used by Content Command Center to let user pick topics.
     */
    public function getAllTrends(?string $source = null): array
    {
        $allTrends = [];

        if (!$source || $source === 'google_trends') {
            $allTrends = array_merge($allTrends, $this->fetchGoogleTrends());
        }
        if (!$source || $source === 'tiktok') {
            $allTrends = array_merge($allTrends, $this->fetchTikTokTrending());
        }
        if (!$source || $source === 'youtube') {
            $allTrends = array_merge($allTrends, $this->fetchYouTubeTrending());
        }
        if (!$source || $source === 'google_news') {
            $allTrends = array_merge($allTrends, $this->fetchGoogleNews());
        }

        $techTrends = $this->filterTechTopics($allTrends);
        // Ensure publisher_tier is populated even for non-news sources so the
        // frontend always sees a consistent schema.
        foreach ($techTrends as &$t) {
            if (!isset($t['publisher_tier'])) {
                $t['publisher_tier'] = $this->classifyPublisherTier($t['publisher'] ?? '');
            }
        }
        unset($t);
        // Cluster near-duplicate topics so cross-publisher coverage becomes a
        // virality signal rather than list-spam.
        $techTrends = $this->dedupAndRank($techTrends);
        usort($techTrends, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $techTrends;
    }

    /**
     * Scored variant of getAllTrends() — layers momentum + virality AI scores
     * on top of the filtered tech trends, sorted by display_score desc.
     *
     * Caps input at TopicScoringService::MAX_BATCH_SIZE so the AI batch fits
     * comfortably in one Sonnet call. Cached 1h downstream in TopicScoringService.
     *
     * display_score = composite_score + heat_boost + tier_boost (clamped 0-100).
     * This is the single number the admin UI shows and sorts by. Boost weights
     * are tunable via config/content.php. Individual signals (heat, tier,
     * virality_score, momentum_score) remain on the payload so the UI can
     * explain the score in a tooltip.
     *
     * @return array each element = original trend fields + momentum_score,
     *   virality_score, triggers, composite_score, display_score.
     */
    public function getScoredTopics(?string $source = null): array
    {
        $trends = $this->getAllTrends($source);
        if (empty($trends)) {
            return [];
        }

        $capped = array_slice(array_values($trends), 0, TopicScoringService::MAX_BATCH_SIZE);

        $scored = app(TopicScoringService::class)->scoreBatch($capped);

        $scored = array_map(fn ($t) => $this->applyDisplayScore($t), $scored);

        usort($scored, fn ($a, $b) => ($b['display_score'] ?? 0) <=> ($a['display_score'] ?? 0));

        return $scored;
    }

    /**
     * Fold heat + publisher-tier boost into composite_score to produce the
     * single display_score the admin UI uses for ranking. Pure function —
     * mutates and returns a copy of the topic array.
     */
    private function applyDisplayScore(array $topic): array
    {
        $composite = (int) ($topic['composite_score'] ?? 0);
        $heatBoosts = config('content.trending.heat_boost', ['hot' => 15, 'trending' => 8, 'standard' => 0]);
        $tierBoosts = config('content.trending.tier_boost', [1 => 5, 2 => 2, 3 => 0]);

        $heat = $topic['heat'] ?? 'standard';
        $tier = (int) ($topic['publisher_tier'] ?? 3);

        $heatBoost = (int) ($heatBoosts[$heat] ?? 0);
        $tierBoost = (int) ($tierBoosts[$tier] ?? 0);

        $topic['heat_boost'] = $heatBoost;
        $topic['tier_boost'] = $tierBoost;
        $topic['display_score'] = max(0, min(100, $composite + $heatBoost + $tierBoost));

        return $topic;
    }

    public function suggestCategory(string $title): int
    {
        $text = strtolower($title);

        // Categories: 1=AI, 2=Technology, 3=Software Dev, 4=Tutorial, 5=Industry Insights, 6=Product Reviews
        $map = [
            1 => ['ai', 'artificial intelligence', 'machine learning', 'llm', 'openai', 'chatgpt', 'claude', 'anthropic', 'gemini', 'gpt', 'agent', 'copilot', 'midjourney', 'diffusion', 'transformer', 'deep learning', 'generative ai', 'prompt', 'rag', 'fine-tuning', 'neural', 'sora', 'dall-e', 'stable diffusion', 'flux'],
            2 => ['tech', 'nvidia', 'chip', 'semiconductor', 'quantum', 'robot', 'ar', 'vr', 'blockchain', 'automation', 'n8n', 'mcp', 'openclaw', 'workflow'],
            3 => ['vibe coding', 'cursor', 'windsurf', 'claude code', 'v0', 'bolt', 'lovable', 'replit', 'devin', 'coding', 'developer', 'programming', 'laravel', 'vue', 'react', 'python', 'javascript', 'api', 'framework', 'open source'],
            4 => ['tutorial', 'how to', 'guide', 'step by step', 'learn', 'build', 'setup'],
            5 => ['industry', 'market', 'trend', 'forecast', 'analysis', 'startup', 'funding', 'acquisition'],
            6 => ['review', 'comparison', 'vs', 'best', 'top', 'alternative', 'tool', 'app'],
        ];

        foreach ($map as $id => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) return $id;
            }
        }

        return 2; // Default: Technology
    }
}
