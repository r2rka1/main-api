<?php

namespace App\Http\Controllers;

use App\Models\JobReference;
use App\Services\WikiServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobsController extends Controller
{
    public function __construct(private readonly WikiServiceClient $wiki)
    {
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        // Ensure the user owns this external job id
        JobReference::where('user_id', $userId)
            ->where('external_job_id', $id)
            ->firstOrFail();

        $payload = $this->wiki->getJob($userId, $id);

        // mirror status locally
        $status = $payload['data']['status'] ?? $payload['status'] ?? null;
        if ($status) {
            JobReference::where('user_id', $userId)
                ->where('external_job_id', $id)
                ->update(['status' => $status]);
        }

        return response()->json($payload);
    }
}
