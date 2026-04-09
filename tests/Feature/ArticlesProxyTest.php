<?php

namespace Tests\Feature;

use App\Models\JobReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticlesProxyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::create([
            'name'     => 'Test',
            'email'    => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
        $this->withHeader('Authorization', 'Bearer ' . $user->createToken('t')->plainTextToken);
        return $user;
    }

    public function test_dispatch_fetch_job_calls_wiki_service_and_stores_reference(): void
    {
        Http::fake([
            'wiki-service.test/api/internal/jobs/fetch' => Http::response([
                'data' => ['id' => 'job-uuid-1', 'status' => 'pending'],
            ], 202),
        ]);

        $user = $this->actingAsUser();

        $this->postJson('/api/articles/fetch-job')
            ->assertStatus(202)
            ->assertJsonPath('data.id', 'job-uuid-1');

        $this->assertDatabaseHas('job_references', [
            'user_id'         => $user->id,
            'external_job_id' => 'job-uuid-1',
            'status'          => 'pending',
        ]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Internal-Secret', 'test-secret')
                && $request->hasHeader('X-User-Id');
        });
    }

    public function test_articles_index_proxies_response(): void
    {
        Http::fake([
            'wiki-service.test/api/internal/articles*' => Http::response([
                'data' => [
                    ['id' => 1, 'title' => 'Mars'],
                    ['id' => 2, 'title' => 'Saturn'],
                ],
                'meta' => ['current_page' => 1, 'total' => 2],
            ], 200),
        ]);

        $this->actingAsUser();

        $this->getJson('/api/articles')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Mars');
    }

    public function test_jobs_show_requires_owned_reference(): void
    {
        $user = $this->actingAsUser();

        // Not yet recorded → 404
        $this->getJson('/api/jobs/some-other-id')->assertStatus(404);

        JobReference::create([
            'user_id'         => $user->id,
            'external_job_id' => 'job-uuid-9',
            'status'          => 'pending',
        ]);

        Http::fake([
            'wiki-service.test/api/internal/jobs/job-uuid-9' => Http::response([
                'data' => ['id' => 'job-uuid-9', 'status' => 'done'],
            ], 200),
        ]);

        $this->getJson('/api/jobs/job-uuid-9')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'done');

        $this->assertDatabaseHas('job_references', [
            'external_job_id' => 'job-uuid-9',
            'status'          => 'done',
        ]);
    }
}
