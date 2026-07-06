<?php

declare(strict_types=1);

use Fynla\Core\Models\RecommendationTracking;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

// The old mock block here stubbed App\Services\{Estate\EstateAnalyzer,
// Protection\ProtectionAgent, Retirement\RetirementProjector,
// Savings\EmergencyFundAnalyzer} — classes that no longer exist after the
// pack relocation. Mockery auto-defined the missing names and the container
// bindings were never resolved, so nothing was actually stubbed. Removed
// 2026-07-06: the endpoints run their real dependencies against a user with
// no financial data, which yields the same empty-recommendation baseline.

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('GET /api/recommendations returns all recommendations', function () {
    $response = $this->getJson('/api/recommendations');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'recommendation_id',
                    'module',
                    'recommendation_text',
                    'priority_score',
                    'timeline',
                    'category',
                    'impact',
                    'status',
                ],
            ],
            'count',
        ]);
});

it('filters recommendations by module via GET /api/recommendations', function () {
    $response = $this->getJson('/api/recommendations?module=protection');

    $response->assertStatus(200);

    $data = $response->json('data');
    if (! empty($data)) {
        foreach ($data as $rec) {
            expect($rec['module'])->toBe('protection');
        }
    }
});

it('filters recommendations by priority via GET /api/recommendations', function () {
    $response = $this->getJson('/api/recommendations?priority=high');

    $response->assertStatus(200);

    $data = $response->json('data');
    if (! empty($data)) {
        foreach ($data as $rec) {
            expect($rec['impact'])->toBe('high');
        }
    }
});

it('filters recommendations by timeline via GET /api/recommendations', function () {
    $response = $this->getJson('/api/recommendations?timeline=immediate');

    $response->assertStatus(200);

    $data = $response->json('data');
    if (! empty($data)) {
        foreach ($data as $rec) {
            expect($rec['timeline'])->toBe('immediate');
        }
    }
});

it('applies limit parameter to GET /api/recommendations', function () {
    $response = $this->getJson('/api/recommendations?limit=3');

    $response->assertStatus(200);

    $data = $response->json('data');
    expect(count($data))->toBeLessThanOrEqual(3);
});

it('validates module parameter in GET /api/recommendations', function () {
    $response = $this->getJson('/api/recommendations?module=invalid_module');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['module']);
});

it('returns summary statistics via GET /api/recommendations/summary', function () {
    $response = $this->getJson('/api/recommendations/summary');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'total_count',
                'by_priority' => ['high', 'medium', 'low'],
                'by_module',
                'by_timeline',
                'total_potential_benefit',
                'total_estimated_cost',
            ],
        ]);
});

it('returns limited high-priority recommendations via GET /api/recommendations/top', function () {
    $response = $this->getJson('/api/recommendations/top?limit=5');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data',
            'count',
        ]);

    $data = $response->json('data');
    expect(count($data))->toBeLessThanOrEqual(5);

    // Verify they are sorted by priority
    if (count($data) > 1) {
        for ($i = 0; $i < count($data) - 1; $i++) {
            expect($data[$i]['priority_score'])
                ->toBeGreaterThanOrEqual($data[$i + 1]['priority_score']);
        }
    }
});

it('creates tracking record via POST /api/recommendations/{id}/mark-done', function () {
    $recommendationId = 'test_rec_'.uniqid();

    $response = $this->postJson("/api/recommendations/{$recommendationId}/mark-done", [
        'module' => 'protection',
        'recommendation_text' => 'Test recommendation',
        'priority_score' => 75.0,
        'timeline' => 'short_term',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Recommendation marked as completed',
        ]);

    $this->assertDatabaseHas('recommendation_tracking', [
        'user_id' => $this->user->id,
        'recommendation_id' => $recommendationId,
        'status' => 'completed',
    ]);
});

it('creates tracking record via POST /api/recommendations/{id}/in-progress', function () {
    $recommendationId = 'test_rec_'.uniqid();

    $response = $this->postJson("/api/recommendations/{$recommendationId}/in-progress", [
        'module' => 'savings',
        'recommendation_text' => 'Test recommendation',
        'priority_score' => 80.0,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Recommendation marked as in progress',
        ]);

    $this->assertDatabaseHas('recommendation_tracking', [
        'user_id' => $this->user->id,
        'recommendation_id' => $recommendationId,
        'status' => 'in_progress',
    ]);
});

it('creates tracking record via POST /api/recommendations/{id}/dismiss', function () {
    $recommendationId = 'test_rec_'.uniqid();

    $response = $this->postJson("/api/recommendations/{$recommendationId}/dismiss", [
        'module' => 'investment',
        'recommendation_text' => 'Test recommendation',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Recommendation dismissed',
        ]);

    $this->assertDatabaseHas('recommendation_tracking', [
        'user_id' => $this->user->id,
        'recommendation_id' => $recommendationId,
        'status' => 'dismissed',
    ]);
});

it('updates notes via PATCH /api/recommendations/{id}/notes', function () {
    $tracking = RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'test_'.uniqid(),
        'module' => 'retirement',
        'recommendation_text' => 'Test recommendation',
        'priority_score' => 60.0,
    ]);

    $response = $this->patchJson("/api/recommendations/{$tracking->recommendation_id}/notes", [
        'notes' => 'These are my notes about this recommendation',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Notes updated successfully',
        ]);

    $this->assertDatabaseHas('recommendation_tracking', [
        'id' => $tracking->id,
        'notes' => 'These are my notes about this recommendation',
    ]);
});

it('validates notes field in PATCH /api/recommendations/{id}/notes', function () {
    $tracking = RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'test_'.uniqid(),
        'module' => 'estate',
        'recommendation_text' => 'Test recommendation',
    ]);

    $response = $this->patchJson("/api/recommendations/{$tracking->recommendation_id}/notes", [
        'notes' => '', // Empty notes should fail
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['notes']);
});

it('returns completed recommendations via GET /api/recommendations/completed', function () {
    // Create completed recommendation
    RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'completed_'.uniqid(),
        'module' => 'protection',
        'recommendation_text' => 'Completed recommendation',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    // Create pending recommendation (should not be returned)
    RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'pending_'.uniqid(),
        'module' => 'savings',
        'recommendation_text' => 'Pending recommendation',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/recommendations/completed');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'recommendation_id',
                    'module',
                    'recommendation_text',
                    'status',
                    'completed_at',
                ],
            ],
            'count',
        ]);

    $data = $response->json('data');
    foreach ($data as $rec) {
        expect($rec['status'])->toBe('completed');
        expect($rec['user_id'])->toBe($this->user->id);
    }
});

it('requires authentication for recommendations API', function () {
    // The old version of this test used withoutMiddleware(), which also
    // disabled the LegacyApiRewrite shim — the request fell through to the
    // SPA catch-all and the 200 it asserted was the SPA shell, not the API.
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/recommendations')->assertStatus(401);
});

it('restricts users to updating only their own recommendation notes', function () {
    $otherUser = User::factory()->create();

    $tracking = RecommendationTracking::create([
        'user_id' => $otherUser->id,
        'recommendation_id' => 'other_user_rec',
        'module' => 'protection',
        'recommendation_text' => 'Other user recommendation',
    ]);

    $response = $this->patchJson("/api/recommendations/{$tracking->recommendation_id}/notes", [
        'notes' => 'Trying to update other user notes',
    ]);

    $response->assertStatus(500); // Should fail to find the record
});
