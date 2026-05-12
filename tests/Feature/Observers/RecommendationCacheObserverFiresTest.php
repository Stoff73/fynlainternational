<?php

declare(strict_types=1);

use App\Agents\TaxOptimisationAgent;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Agents\CoordinatingAgent;
use Fynla\Packs\Gb\Agents\EstateAgent;
use Fynla\Packs\Gb\Agents\InvestmentAgent;
use Fynla\Packs\Gb\Agents\ProtectionAgent;
use Fynla\Packs\Gb\Agents\RetirementAgent;
use Fynla\Packs\Gb\Agents\SavingsAgent;

/**
 * G-1-b scaffold for RecommendationCacheObserver firing tests.
 *
 * Observer: app/Observers/RecommendationCacheObserver.php
 * Fires on: created / updated / deleted of every model with a user_id.
 *
 * Routing logic (per observer's match expression):
 *   - SavingsAccount      → SavingsAgent + TaxOptimisationAgent
 *   - InvestmentAccount,
 *     Holding             → InvestmentAgent + TaxOptimisationAgent
 *   - DCPension,
 *     DBPension,
 *     StatePension,
 *     RetirementProfile   → RetirementAgent + TaxOptimisationAgent
 *   - *Policy,
 *     ProtectionProfile   → ProtectionAgent
 *   - Gift, Trust,
 *     Liability, Chattel,
 *     BusinessInterest    → EstateAgent + TaxOptimisationAgent
 *   - Property, Mortgage  → EstateAgent + ProtectionAgent + TaxOptimisationAgent
 *   - FamilyMember        → ProtectionAgent + EstateAgent + TaxOptimisationAgent
 *   - Goal, LifeEvent     → SavingsAgent + InvestmentAgent
 *   - default             → all 5 main agents
 *
 * CoordinatingAgent is ALWAYS invalidated. Joint owner also invalidated.
 *
 * G-1-b implementer: spy on each agent's invalidateUserCache method, drive
 * one model per branch, assert correct routing.
 */

beforeEach(function () {
    $this->user = User::factory()->create();

    // Spy on the 6 agents that this observer dispatches to.
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

it('routes SavingsAccount changes to SavingsAgent + TaxOptimisationAgent + CoordinatingAgent')
    ->todo('G-1-b: SavingsAccount::factory()->create; assert only those 3 agents received invalidateUserCache');

it('routes InvestmentAccount changes to InvestmentAgent + TaxOptimisationAgent + CoordinatingAgent')
    ->todo('G-1-b: similar; assert routing for InvestmentAccount');

it('routes DCPension changes to RetirementAgent + TaxOptimisationAgent + CoordinatingAgent')
    ->todo('G-1-b: similar; assert routing for DCPension');

it('routes Property changes to EstateAgent + ProtectionAgent + TaxOptimisationAgent + CoordinatingAgent')
    ->todo('G-1-b: similar; assert routing for Property');

it('routes Goal changes to SavingsAgent + InvestmentAgent + CoordinatingAgent')
    ->todo('G-1-b: similar; assert routing for Goal');

it('always invalidates CoordinatingAgent regardless of model type')
    ->todo('G-1-b: trigger any model; CoordinatingAgent spy receives invalidateUserCache');

it('invalidates for joint_owner_id when present')
    ->todo('G-1-b: joint account; each routed agent receives invalidateUserCache twice (once per owner)');
