<?php

declare(strict_types=1);

use App\Services\Lifecycle\LifecycleEngine;
use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Log;

describe('LifecycleEngine (G-(-1) MVP)', function () {
    afterEach(function () {
        config()->set('lifecycle.test_recipient_override', null);
    });

    it('exposes config(lifecycle.test_recipient_override) from the LIFECYCLE_TEST_RECIPIENT env var', function () {
        config()->set('lifecycle.test_recipient_override', 'chris@fynla.org');

        expect(config('lifecycle.test_recipient_override'))->toBe('chris@fynla.org');
    });

    it('defaults config(lifecycle.test_recipient_override) to null when the env var is unset', function () {
        config()->set('lifecycle.test_recipient_override', null);

        expect(config('lifecycle.test_recipient_override'))->toBeNull();
    });

    it('routes recipient to the override when set', function () {
        config()->set('lifecycle.test_recipient_override', 'chris@fynla.org');

        $user = new User(['email' => 'real-user@example.com']);

        expect((new LifecycleEngine)->resolveRecipient($user))->toBe('chris@fynla.org');
    });

    it('routes recipient to the user email when no override is set', function () {
        config()->set('lifecycle.test_recipient_override', null);

        $user = new User(['email' => 'real-user@example.com']);

        expect((new LifecycleEngine)->resolveRecipient($user))->toBe('real-user@example.com');
    });

    it('ignores an empty-string override and falls back to user email', function () {
        config()->set('lifecycle.test_recipient_override', '');

        $user = new User(['email' => 'real-user@example.com']);

        expect((new LifecycleEngine)->resolveRecipient($user))->toBe('real-user@example.com');
    });

    it('logs a structured lifecycle.dispatch entry without sending mail', function () {
        config()->set('lifecycle.test_recipient_override', 'chris@fynla.org');

        $user = new User(['email' => 'real-user@example.com']);
        $user->id = 42;

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'lifecycle.dispatch'
                    && $context['event'] === 'trial_ending'
                    && $context['user_id'] === 42
                    && $context['recipient'] === 'chris@fynla.org'
                    && $context['override_active'] === true
                    && $context['context_keys'] === ['days_remaining'];
            });

        (new LifecycleEngine)->dispatch($user, 'trial_ending', ['days_remaining' => 3]);
    });

    it('registers lifecycle:run-daily on the schedule list', function () {
        $output = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? $event->description)
            ->filter()
            ->implode("\n");

        expect($output)->toContain('lifecycle:run-daily');
    });
});
