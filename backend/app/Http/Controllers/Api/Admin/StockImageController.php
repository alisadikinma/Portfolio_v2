<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockImageController extends Controller
{
    /**
     * Search stock photos via Pexels (primary) with Unsplash fallback.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        if (empty(trim($query))) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required.',
            ], 422);
        }

        $orientation = $request->query('orientation', 'landscape');
        $perPage = min((int) $request->query('per_page', 20), 40);
        $page = max((int) $request->query('page', 1), 1);

        // Try Pexels first
        $pexelsKey = config('services.pexels.api_key');
        if ($pexelsKey) {
            $result = $this->searchPexels($query, $orientation, $perPage, $page, $pexelsKey);
            if ($result !== null && count($result['results']) > 0) {
                return response()->json(['success' => true, 'data' => $result]);
            }
        }

        // Fallback to Unsplash
        $unsplashKey = config('services.unsplash.access_key');
        if ($unsplashKey) {
            $result = $this->searchUnsplash($query, $orientation, $perPage, $page, $unsplashKey);
            if ($result !== null) {
                return response()->json(['success' => true, 'data' => $result]);
            }
        }

        // Both failed
        return response()->json([
            'success' => true,
            'data' => [
                'results' => [],
                'total' => 0,
                'query' => $query,
            ],
        ]);
    }

    private function searchPexels(string $query, string $orientation, int $perPage, int $page, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => $apiKey])
                ->get('https://api.pexels.com/v1/search', [
                    'query' => $query,
                    'orientation' => $orientation,
                    'per_page' => $perPage,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                Log::warning('[StockImage] Pexels API error: ' . $response->status());
                return null;
            }

            $data = $response->json();
            $results = collect($data['photos'] ?? [])->map(fn($photo) => [
                'id' => (string) $photo['id'],
                'provider' => 'pexels',
                'url_thumb' => $photo['src']['small'] ?? '',
                'url_regular' => $photo['src']['medium'] ?? '',
                'url_full' => $photo['src']['original'] ?? '',
                'width' => $photo['width'] ?? 0,
                'height' => $photo['height'] ?? 0,
                'photographer' => $photo['photographer'] ?? '',
                'alt' => $photo['alt'] ?? $query,
            ])->toArray();

            return [
                'results' => $results,
                'total' => $data['total_results'] ?? count($results),
                'query' => $query,
            ];
        } catch (\Exception $e) {
            Log::warning('[StockImage] Pexels exception: ' . $e->getMessage());
            return null;
        }
    }

    private function searchUnsplash(string $query, string $orientation, int $perPage, int $page, string $accessKey): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => "Client-ID {$accessKey}"])
                ->get('https://api.unsplash.com/search/photos', [
                    'query' => $query,
                    'orientation' => $orientation,
                    'per_page' => $perPage,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                Log::warning('[StockImage] Unsplash API error: ' . $response->status());
                return null;
            }

            $data = $response->json();
            $results = collect($data['results'] ?? [])->map(fn($photo) => [
                'id' => $photo['id'] ?? '',
                'provider' => 'unsplash',
                'url_thumb' => $photo['urls']['thumb'] ?? '',
                'url_regular' => $photo['urls']['regular'] ?? '',
                'url_full' => $photo['urls']['full'] ?? '',
                'width' => $photo['width'] ?? 0,
                'height' => $photo['height'] ?? 0,
                'photographer' => $photo['user']['name'] ?? '',
                'alt' => $photo['alt_description'] ?? $query,
            ])->toArray();

            return [
                'results' => $results,
                'total' => $data['total'] ?? count($results),
                'query' => $query,
            ];
        } catch (\Exception $e) {
            Log::warning('[StockImage] Unsplash exception: ' . $e->getMessage());
            return null;
        }
    }
}
