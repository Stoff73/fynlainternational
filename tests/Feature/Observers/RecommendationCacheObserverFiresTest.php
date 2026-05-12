<?php

declare(strict_types=1);

use App\Agents\TaxOptimisationAgent;
use Fynla\Core\Models\LifeEvent;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Agents\CoordinatingAgent;
use Fynla\Packs\Gb\Agents\EstateAgent;
use Fynla\Packs\Gb\Agents\InvestmentAgent;
use Fynla\Packs\Gb\Agents\ProtectionAgent;
use Fynla\Packs\Gb\Agents\RetirementAgent;
use Fynla\Packs\Gb\Agents\SavingsAgent;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use Fynla\Packs\Gb\Models\Property;
use Fynla\Packs\Gb\Models\SavingsAccount;

/**
 * G-1-b firing tests for RecommendationCacheObserver.
 *
 * Observer: app/Observers/RecommendationCacheObserver.php
 *
 * Fires on: created / updated / deleted of every model with a user_id.
 * Routes to agent-specific invalidateUserCache() calls based on model class.
 * CoordinatingAgent is ALWAYS invalidated. Joint owner is also invalidated.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->jointOwner = User::factory()->create();

    $this->spies = [];
    foreach ([
        SavingsAgent::class,
        InvestmentAgent::class,
        RetirementAgent::class,
        ProtectionAgent::class,
        EstateAgent::class,
        TaxOptimisationAgent::class,
        CoordinatingAgent::class,
    ] as $agentClass) {
        $spy = Mockery::spy($agentClass);
        app()->instance($agentClass, $spy);
        $this->spies[$agentClass] = $spy;
    }
});

afterEach(function () {
    Mockery::close();
});

it('routes SavingsAccount changes to SavingsAgent + TaxOptimisationAgent + CoordinatingAgent', function () {
    SavingsAccount::factory()->create(['user_id' => $this->user->id]);

    $this->spies[SavingsAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[TaxOptimisationAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);

    $this->spies[InvestmentAgent::class]->shouldNotHaveReceived('invalidateUserCache');
    $this->spies[RetirementAgent::class]->shouldNotHaveReceived('invalidateUserCache');
    $this->spies[ProtectionAgent::class]->shouldNotHaveReceived('invalidateUserCache');
    $this->spies[EstateAgent::class]->shouldNotHaveReceived('invalidateUserCache');
});

it('routes InvestmentAccount changes to InvestmentAgent + TaxOptimisationAgent + CoordinatingAgent', function () {
    InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

    $this->spies[InvestmentAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[TaxOptimisationAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);

    $this->spies[SavingsAgent::class]->shouldNotHaveReceived('invalidateUserCache');
    $this->spies[RetirementAgent::class]->shouldNotHaveReceived('invalidateUserCache');
});

it('routes DCPension changes to RetirementAgent + TaxOptimisationAgent + CoordinatingAgent', function () {
    DCPension::factory()->create(['user_id' => $this->user->id]);

    $this->spies[RetirementAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[TaxOptimisationAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);

    $this->spies[SavingsAgent::class]->shouldNotHaveReceived('invalidateUserCache');
});

it('routes Property changes to EstateAgent + ProtectionAgent + TaxOptimisationAgent + CoordinatingAgent', function () {
    Property::factory()->create(['user_id' => $this->user->id]);

    $this->spies[EstateAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[ProtectionAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[TaxOptimisationAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);

    $this->spies[SavingsAgent::class]->shouldNotHaveReceived('invalidateUserCache');
});

it('routes LifeEvent changes to SavingsAgent + InvestmentAgent + CoordinatingAgent', function () {
    // The observer's routing arm covers `str_contains($class, 'Goal') || 'LifeEvent'`
    // → [SavingsAgent, InvestmentAgent]. Goal::class isn't registered in
    // EventServiceProvider for this observer, so LifeEvent is the practical
    // way to exercise this routing branch.
    LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'inheritance',
    ]);

    $this->spies[SavingsAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[InvestmentAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);

    $this->spies[RetirementAgent::class]->shouldNotHaveReceived('invalidateUserCache');
    $this->spies[ProtectionAgent::class]->shouldNotHaveReceived('invalidateUserCache');
});

it('always invalidates CoordinatingAgent regardless of model type', function () {
    SavingsAccount::factory()->create(['user_id' => $this->user->id]);
    InvestmentAccount::factory()->create(['user_id' => $this->user->id]);
    DCPension::factory()->create(['user_id' => $this->user->id]);
    Property::factory()->create(['user_id' => $this->user->id]);

    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')
        ->with($this->user->id)
        ->times(4);
});

it('invalidates for joint_owner_id when present', function () {
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $this->jointOwner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
    ]);

    $this->spies[SavingsAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[SavingsAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->jointOwner->id);
    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->user->id);
    $this->spies[CoordinatingAgent::class]->shouldHaveReceived('invalidateUserCache')->with($this->jointOwner->id);
});
