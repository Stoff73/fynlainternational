<?php

declare(strict_types=1);

use App\Services\Payment\RevolutService;
use Fynla\Core\Models\Payment;
use Fynla\Core\Models\Subscription;
use Fynla\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

describe('G-4-b slice 2 — H-1: payments.revolut_order_id uniqueness', function () {
    it('rejects a duplicate revolut_order_id insertion', function () {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'yearly',
            'status' => 'trialing',
            'amount' => 0,
            'current_period_start' => now(),
            'current_period_end' => now(),
        ]);

        $orderId = (string) Str::uuid();

        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => 'pending',
            'plan_slug' => 'standard',
            'billing_cycle' => 'yearly',
        ]);

        expect(fn () => Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => 'pending',
            'plan_slug' => 'standard',
            'billing_cycle' => 'yearly',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});

describe('G-4-b slice 2 — H-2: confirmPayment only accepts completed states', function () {
    it('rejects activation when Revolut state is processing', function () {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'yearly',
            'status' => 'trialing',
            'amount' => 0,
            'current_period_start' => now(),
            'current_period_end' => now(),
        ]);

        $orderId = (string) Str::uuid();
        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => 'pending',
            'plan_slug' => 'standard',
            'billing_cycle' => 'yearly',
        ]);

        Http::fake([
            "*/api/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'state' => 'processing',
                'capture_mode' => 'automatic',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

        $response->assertStatus(400);
        $subscription->refresh();
        expect($subscription->status)->toBe('trialing');
    });

    it('rejects activation when Revolut state is pending', function () {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'yearly',
            'status' => 'trialing',
            'amount' => 0,
            'current_period_start' => now(),
            'current_period_end' => now(),
        ]);

        $orderId = (string) Str::uuid();
        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => 'pending',
            'plan_slug' => 'standard',
            'billing_cycle' => 'yearly',
        ]);

        Http::fake([
            "*/api/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'state' => 'pending',
                'capture_mode' => 'automatic',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

        $response->assertStatus(400);
        $subscription->refresh();
        expect($subscription->status)->toBe('trialing');
    });
});

describe('G-4-b slice 2 — M-6: missing capture_mode is fail-loud', function () {
    it('rejects confirmPayment when Revolut response omits capture_mode', function () {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'yearly',
            'status' => 'trialing',
            'amount' => 0,
            'current_period_start' => now(),
            'current_period_end' => now(),
        ]);

        $orderId = (string) Str::uuid();
        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => 'pending',
            'plan_slug' => 'standard',
            'billing_cycle' => 'yearly',
        ]);

        Http::fake([
            "*/api/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'state' => 'completed',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

        $response->assertStatus(400);
        $subscription->refresh();
        expect($subscription->status)->toBe('trialing');
    });
});

describe('G-4-b slice 2 — M-8: upgrade uses unique placeholder', function () {
    it('allows concurrent in-flight upgrades to coexist (unique placeholders)', function () {
        $user = User::factory()->create();
        $subscription = Subscription::factory()
            ->plan('standard')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => now()->subMonths(2),
                'current_period_end' => now()->addMonths(10),
                'amount' => 10000,
            ]);

        // Create two payments with unique upgrade placeholders directly
        // (simulating two concurrent upgradeSubscription requests).
        $p1 = Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => 'upgrade_pending_'.Str::uuid()->toString(),
            'amount' => 4000,
            'currency' => 'GBP',
            'status' => 'pending',
            'plan_slug' => 'pro',
            'billing_cycle' => 'yearly',
            'upgrade_from_plan' => 'standard',
        ]);

        $p2 = Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => 'upgrade_pending_'.Str::uuid()->toString(),
            'amount' => 4000,
            'currency' => 'GBP',
            'status' => 'pending',
            'plan_slug' => 'pro',
            'billing_cycle' => 'yearly',
            'upgrade_from_plan' => 'standard',
        ]);

        expect($p1->id)->not->toBe($p2->id);
        expect($p1->revolut_order_id)->not->toBe($p2->revolut_order_id);
    });
});
