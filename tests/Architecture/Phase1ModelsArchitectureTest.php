<?php

declare(strict_types=1);

/**
 * Phase 1 Models Architecture Tests
 *
 * These tests ensure that all new models created in Phase 1 (multi-user, household,
 * and asset models) follow the established architectural patterns and coding standards.
 */

// Test: All new models extend Eloquent Model
arch('all new Phase 1 models extend Eloquent Model')
    ->expect([
        'App\Models\Household',
        'App\Models\FamilyMember',
        'App\Models\Property',
        'App\Models\Mortgage',
        'App\Models\BusinessInterest',
        'App\Models\Chattel',
        'App\Models\CashAccount',
        'App\Models\PersonalAccount',
    ])
    ->toExtend('Illuminate\Database\Eloquent\Model');

// Test: All new models use HasFactory trait
arch('new Phase 1 models use HasFactory trait')
    ->expect([
        'App\Models\Household',
        'App\Models\FamilyMember',
        'App\Models\Property',
        'App\Models\Mortgage',
        'App\Models\BusinessInterest',
        'App\Models\Chattel',
        'App\Models\CashAccount',
        'App\Models\PersonalAccount',
    ])
    ->toUse('Illuminate\Database\Eloquent\Factories\HasFactory');

// Test: All new models use strict types
arch('new Phase 1 models use strict types')
    ->expect('App\Models\Household')
    ->toUseStrictTypes()
    ->and('App\Models\FamilyMember')
    ->toUseStrictTypes()
    ->and('App\Models\Property')
    ->toUseStrictTypes()
    ->and('App\Models\Mortgage')
    ->toUseStrictTypes()
    ->and('App\Models\BusinessInterest')
    ->toUseStrictTypes()
    ->and('App\Models\Chattel')
    ->toUseStrictTypes()
    ->and('App\Models\CashAccount')
    ->toUseStrictTypes()
    ->and('App\Models\PersonalAccount')
    ->toUseStrictTypes();

// Test: Models use Eloquent relationships
arch('Household model uses relationships')
    ->expect('App\Models\Household')
    ->toUse('Illuminate\Database\Eloquent\Relations\HasMany');

arch('Property model uses relationships')
    ->expect('App\Models\Property')
    ->toUse('Illuminate\Database\Eloquent\Relations\BelongsTo')
    ->toUse('Illuminate\Database\Eloquent\Relations\HasMany');

arch('Mortgage model uses relationships')
    ->expect('App\Models\Mortgage')
    ->toUse('Illuminate\Database\Eloquent\Relations\BelongsTo');

// Test: Models are properly namespaced
arch('all Phase 1 models are in App\Models namespace')
    ->expect([
        'App\Models\Household',
        'App\Models\FamilyMember',
        'App\Models\Property',
        'App\Models\Mortgage',
        'App\Models\BusinessInterest',
        'App\Models\Chattel',
        'App\Models\CashAccount',
        'App\Models\PersonalAccount',
    ])
    ->toBeClasses();

// Test: Models do not use business logic services directly
arch('Phase 1 models do not use external services')
    ->expect([
        'App\Models\Household',
        'App\Models\FamilyMember',
        'App\Models\Property',
        'App\Models\Mortgage',
        'App\Models\BusinessInterest',
        'App\Models\Chattel',
        'App\Models\CashAccount',
        'App\Models\PersonalAccount',
    ])
    ->not->toUse([
        'Illuminate\Support\Facades\Cache',
        'Illuminate\Support\Facades\Queue',
        'Illuminate\Support\Facades\Mail',
        'Illuminate\Support\Facades\Log',
    ]);
