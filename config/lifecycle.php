<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Test recipient override
    |--------------------------------------------------------------------------
    |
    | When set, the lifecycle engine routes ALL outbound lifecycle emails
    | to this address instead of the user's real email. Used in staging /
    | dev to exercise lifecycle dispatches without spamming real users.
    | Production leaves this unset.
    |
    */

    'test_recipient_override' => env('LIFECYCLE_TEST_RECIPIENT'),

    /*
    |--------------------------------------------------------------------------
    | Lifecycle events
    |--------------------------------------------------------------------------
    |
    | Map of event name → handler config. Populated by the full lifecycle
    | engine (see docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md).
    | The G-(-1) MVP leaves this empty — LifecycleEngine::dispatch() is a
    | stub that logs the dispatch without sending mail.
    |
    */

    'events' => [],

];
