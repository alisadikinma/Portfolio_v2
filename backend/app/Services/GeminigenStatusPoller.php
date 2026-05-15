<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queries GeminiGen.ai's public status page via Firecrawl to detect outage
 * states for the project's primary image model. Used ONLY by the circuit
 * breaker's early-trip accelerator (Phase M) — NOT continuously polled.
 *
 * Cost-controlled: result cached 5 min, so even with the dispatch-hook lock
 * already throttling job dispatches, repeat queries within the cache TTL
 * don't re-scrape.
 */
class GeminigenStatusPoller
{
    private const CACHE_KEY = 'geminigen:status:last_result';

    /**
     * @return bool|null  true  = our model status contains "outage" (confirmed)
     *                    false = our model status is operational
     *                    null  = couldn't determine (Firecrawl down, model missing, etc.)
     */
    public function isOurModelInOutage(): ?bool
    {
        $cacheTtl = (int) config('geminigen-circuit.status_cache_seconds', 300);

        // Cache hit short-circuit (allows cached null too)
        if (Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        $apiKey = config('geminigen-circuit.firecrawl_api_key');
        if (empty($apiKey)) {
            Log::warning('[GeminigenStatusPoller] FIRECRAWL_API_KEY missing — cannot probe status page');
            return null;
        }

        $statusUrl = config('geminigen-circuit.status_page_url', 'https://geminigen.ai/status');
        $modelName = config('geminigen-circuit.status_model_name', 'Nano Banana Pro');
        $baseUrl = rtrim(config('geminigen-circuit.firecrawl_base_url', 'https://api.firecrawl.dev/v2'), '/');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post($baseUrl . '/scrape', [
                    'url' => $statusUrl,
                    'formats' => ['json'],
                    'onlyMainContent' => true,
                    'waitFor' => 3000,
                    'jsonOptions' => [
                        'prompt' => 'Extract each service component and its current status from the status page.',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'components' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'status' => ['type' => 'string'],
                                        ],
                                        'required' => ['name', 'status'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('[GeminigenStatusPoller] Firecrawl returned non-2xx', ['status' => $response->status()]);
                Cache::put(self::CACHE_KEY, null, $cacheTtl);
                return null;
            }

            // Firecrawl v2 response shape: data.json.components — fall back to json.components
            // in case the wrapper changes.
            $components = $response->json('data.json.components')
                ?: $response->json('json.components')
                ?: $response->json('data.extract.components')
                ?: [];

            if (empty($components) || !is_array($components)) {
                Log::warning('[GeminigenStatusPoller] no components in Firecrawl response');
                Cache::put(self::CACHE_KEY, null, $cacheTtl);
                return null;
            }

            // Match by name (case-insensitive, trimmed)
            $normalized = strtolower(trim($modelName));
            foreach ($components as $component) {
                $name = strtolower(trim($component['name'] ?? ''));
                if ($name === $normalized) {
                    $status = strtolower(trim($component['status'] ?? ''));
                    $result = str_contains($status, 'outage');
                    Cache::put(self::CACHE_KEY, $result, $cacheTtl);
                    Log::info('[GeminigenStatusPoller] resolved', [
                        'model' => $modelName,
                        'status' => $component['status'] ?? '',
                        'is_outage' => $result,
                    ]);
                    return $result;
                }
            }

            // Model not found in components list
            Log::warning('[GeminigenStatusPoller] model not in status page components', ['model' => $modelName]);
            Cache::put(self::CACHE_KEY, null, $cacheTtl);
            return null;
        } catch (\Throwable $e) {
            Log::warning('[GeminigenStatusPoller] exception during scrape', ['error' => $e->getMessage()]);
            Cache::put(self::CACHE_KEY, null, $cacheTtl);
            return null;
        }
    }
}
