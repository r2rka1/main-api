<?php

namespace App\Http\Controllers;

use App\Models\JobReference;
use App\Services\WikiServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticlesController extends Controller
{
    public function __construct(private readonly WikiServiceClient $wiki)
    {
    }

    public function dispatchFetch(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $payload = $this->wiki->dispatchFetchJob($userId);
        $job     = $payload['data'] ?? $payload;

        JobReference::create([
            'user_id'         => $userId,
            'external_job_id' => $job['id'],
            'status'          => $job['status'] ?? 'pending',
        ]);

        return response()->json(['data' => $job], 202);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $page   = (int) $request->query('page', 1);

        return response()->json($this->wiki->listArticles($userId, $page));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->wiki->getArticle($request->user()->id, $id));
    }
}
