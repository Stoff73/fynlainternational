<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * G-(-1) MVP stub. Logs the dispatch and resolves the recipient through
 * the test-recipient override, but does NOT send mail. The full lifecycle
 * engine (10 event types, scheduling matrix, audit logging) is captured
 * as tech debt in docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md
 * and is out of scope for the test gauntlet.
 */
class LifecycleEngine
{
    public function dispatch(User $user, string $event, array $context = []): void
    {
        $recipient = $this->resolveRecipient($user);

        Log::info('lifecycle.dispatch', [
            'event' => $event,
            'user_id' => $user->id,
            'recipient' => $recipient,
            'override_active' => $recipient !== $user->email,
            'context_keys' => array_keys($context),
        ]);
    }

    public function resolveRecipient(User $user): string
    {
        $override = config('lifecycle.test_recipient_override');

        return is_string($override) && $override !== '' ? $override : $user->email;
    }
}
