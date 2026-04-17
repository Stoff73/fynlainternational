<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Observers\JurisdictionDetectionObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Register the observer fresh for each test. The production registration
    // point is AppServiceProvider (Workstream 0.6 wiring-up step); here we
    // attach it directly so the test is self-contained and order-independent.
    InvestmentAccount::observe(JurisdictionDetectionObserver::class);

    $this->gb = Jurisdiction::firstOrCreate(
        ['code' => 'GB'],
        ['name' => 'United Kingdom', 'currency' => 'GBP', 'locale' => 'en-GB', 'active' => true]
    );
    $this->za = Jurisdiction::firstOrCreate(
        ['code' => 'ZA'],
        ['name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en-ZA', 'active' => true]
    );

    $this->user = User::factory()->create();

    // Seed the user's primary jurisdiction — the default for every test user.
    DB::table('user_jurisdictions')->insert([
        'user_id' => $this->user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
        'deactivated_at' => null,
        'auto_detected' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function activeAssignment(int $userId, int $jurisdictionId): ?object
{
    return DB::table('user_jurisdictions')
        ->where('user_id', $userId)
        ->where('jurisdiction_id', $jurisdictionId)
        ->whereNull('deactivated_at')
        ->first();
}

describe('activation on create', function () {
    it('activates ZA when the user adds a SA investment account', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        $assignment = activeAssignment($this->user->id, $this->za->id);

        expect($assignment)->not->toBeNull();
        expect((bool) $assignment->auto_detected)->toBeTrue();
        expect((bool) $assignment->is_primary)->toBeFalse();
    });

    it('does not create a duplicate when ZA is already active', function () {
        // First SA asset activates ZA.
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        // Second SA asset must not insert a second row (would violate the
        // unique (user_id, jurisdiction_id) constraint — if it did, this
        // test would throw).
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        $rows = DB::table('user_jurisdictions')
            ->where('user_id', $this->user->id)
            ->where('jurisdiction_id', $this->za->id)
            ->count();

        expect($rows)->toBe(1);
    });

    it('is a no-op when the asset has no country_code', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => null,
        ]);

        // Only GB (seeded in beforeEach) should remain.
        $count = DB::table('user_jurisdictions')
            ->where('user_id', $this->user->id)
            ->whereNull('deactivated_at')
            ->count();

        expect($count)->toBe(1);
    });

    it('is a no-op when the country_code is unsupported', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'US',  // no Jurisdiction row seeded
        ]);

        $count = DB::table('user_jurisdictions')
            ->where('user_id', $this->user->id)
            ->whereNull('deactivated_at')
            ->count();

        expect($count)->toBe(1);
    });

    it('reactivates a previously soft-deactivated jurisdiction', function () {
        // Simulate a historical SA assignment that was deactivated.
        DB::table('user_jurisdictions')->insert([
            'user_id' => $this->user->id,
            'jurisdiction_id' => $this->za->id,
            'is_primary' => false,
            'activated_at' => now()->subMonth(),
            'deactivated_at' => now()->subWeek(),
            'auto_detected' => true,
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subWeek(),
        ]);

        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        $rows = DB::table('user_jurisdictions')
            ->where('user_id', $this->user->id)
            ->where('jurisdiction_id', $this->za->id)
            ->count();
        // Still one row total — the soft-deactivated row was reactivated
        // in place, not duplicated.
        expect($rows)->toBe(1);

        $assignment = activeAssignment($this->user->id, $this->za->id);
        expect($assignment)->not->toBeNull();
        expect($assignment->deactivated_at)->toBeNull();
    });
});

describe('deactivation on delete', function () {
    it('deactivates ZA when the user deletes their last SA asset', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        expect(activeAssignment($this->user->id, $this->za->id))->not->toBeNull();

        $account->forceDelete();

        expect(activeAssignment($this->user->id, $this->za->id))->toBeNull();

        // Row still exists — soft-deactivated, not deleted.
        $history = DB::table('user_jurisdictions')
            ->where('user_id', $this->user->id)
            ->where('jurisdiction_id', $this->za->id)
            ->first();
        expect($history)->not->toBeNull();
        expect($history->deactivated_at)->not->toBeNull();
    });

    it('keeps ZA active when another SA asset still exists', function () {
        $first = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        $first->forceDelete();

        expect(activeAssignment($this->user->id, $this->za->id))->not->toBeNull();
    });

    it('never deactivates the user\'s primary jurisdiction', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'GB',
        ]);

        $account->forceDelete();

        // GB was primary; must remain active even though no GB asset remains.
        expect(activeAssignment($this->user->id, $this->gb->id))->not->toBeNull();
    });
});

describe('update flows', function () {
    it('activates the new country and deactivates the old when country_code changes', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        expect(activeAssignment($this->user->id, $this->za->id))->not->toBeNull();

        // Move the asset back to GB (the user's primary).
        $account->update(['country_code' => 'GB']);

        // ZA should deactivate because no other asset references it.
        expect(activeAssignment($this->user->id, $this->za->id))->toBeNull();
    });

    it('is a no-op when the country_code does not change', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => 'ZA',
        ]);

        $account->update(['account_name' => 'Renamed']);

        expect(activeAssignment($this->user->id, $this->za->id))->not->toBeNull();
    });
});
