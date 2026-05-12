<?php

declare(strict_types=1);

use Fynla\Core\Models\Goal;
use Fynla\Core\Models\GoalContribution;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;

/**
 * G-1-b scaffold for InvestmentAccountGoalObserver firing tests.
 *
 * Observer: packs/country-gb/src/Observers/InvestmentAccountGoalObserver.php
 * Fires via TracksGoalContributions trait when an InvestmentAccount's
 * current_value increases AND a Goal is linked via
 * linked_investment_account_id (or pivot, per trait).
 *
 * Side effect: creates a GoalContribution record (contribution_type =
 * "automatic"), updates Goal::current_amount.
 *
 * Note: GoalObserversTest.php already covers some of this for SavingsAccount.
 * G-1-b should mirror that test's pattern for InvestmentAccount and assert
 * post-relocation namespaces resolve correctly.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates automatic GoalContribution when linked InvestmentAccount current_value increases')
    ->todo('G-1-b: mirror GoalObserversTest savings pattern for InvestmentAccount → linked Goal');

it('does NOT create contribution when current_value decreases')
    ->todo('G-1-b: balance drop; assert GoalContribution count unchanged');

it('does NOT create contribution for unlinked accounts')
    ->todo('G-1-b: account with no linked Goal; assert no contribution');

it('uses provider + account_name in contribution note')
    ->todo('G-1-b: assert GoalContribution::note matches "Auto-tracked from {provider} ({account_name})"');

it('respects paused goals (no contribution recorded)')
    ->todo('G-1-b: paused $goal; assert no contribution on value change');
