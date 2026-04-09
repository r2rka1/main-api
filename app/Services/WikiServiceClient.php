<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WikiServiceClient
{
    public function dispatchFetchJob(int $userId): array
    {
        return $this->client($userId)
            ->post('/api/internal/jobs/fetch')
            ->throw()
            ->json();
    }

    public function getJob(int $userId, string $jobId): array
    {
        return $this->client($userId)
            ->get("/api/internal/jobs/{$jobId}")
            ->throw()
            ->json();
    }

    public function listArticles(int $userId, int $page = 1): array
    {
        return $this->client($userId)
            ->get('/api/internal/articles', ['page' => $page])
            ->throw()
            ->json();
    }

    public function getArticle(int $userId, int $id): array
    {
        return $this->client($userId)
            ->get("/api/internal/articles/{$id}")
            ->throw()
            ->json();
    }

    private function client(int $userId): PendingRequest
    {
        return Http::baseUrl((string) config('services.wiki_service.base_url'))
            ->timeout(15)
            ->acceptJson()
            ->withHeaders([
                'X-Internal-Secret' => (string) config('services.wiki_service.shared_secret'),
                'X-User-Id'         => (string) $userId,
            ]);
    }
}
