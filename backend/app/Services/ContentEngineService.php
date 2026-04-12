<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentEngineService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.content_engine.url'), '/');
        $this->apiKey = config('services.content_engine.api_key', '');
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['x-api-key' => $this->apiKey])
            ->timeout(30)
            ->acceptJson();
    }

    public function healthCheck(): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)->timeout(5)->get('/health');
            return ['healthy' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            Log::warning('[ContentEngine] Health check failed: ' . $e->getMessage());
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    public function createWorkflow(string $workflowType, array $inputData = []): array
    {
        $response = $this->client()->post('/workflows', [
            'workflow_type' => $workflowType,
            'input_data' => $inputData,
        ]);

        if (!$response->successful()) {
            Log::error('[ContentEngine] createWorkflow failed', [
                'status' => $response->status(), 'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                'Content Engine error: ' . ($response->json('detail') ?? $response->body())
            );
        }

        return $response->json();
    }

    public function getWorkflowStatus(int $id): array
    {
        $response = $this->client()->get("/workflows/{$id}");
        if (!$response->successful()) {
            throw new \RuntimeException('Workflow not found or engine error');
        }
        return $response->json();
    }

    public function listWorkflows(): array
    {
        $response = $this->client()->get('/workflows');
        return $response->successful() ? $response->json() : [];
    }

    public function getInstagramTrending(): array
    {
        try {
            $response = $this->client()->get('/instagram/media');
            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Exception $e) {
            Log::warning('[ContentEngine] Instagram trending failed: ' . $e->getMessage());
            return [];
        }
    }
}
