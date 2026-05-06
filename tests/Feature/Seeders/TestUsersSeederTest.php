<?php

declare(strict_types=1);

use Fynla\Core\Models\FamilyMember;
use Fynla\Packs\Gb\Models\Mortgage;
use Fynla\Packs\Gb\Models\Property;
use App\Models\User;
use Database\Seeders\HouseholdSeeder;
use Database\Seeders\JurisdictionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TestUsersSeeder;

describe('TestUsersSeeder idempotency', function () {
    beforeEach(function () {
        $this->seed(JurisdictionSeeder::class);
        $this->seed(RolesPermissionsSeeder::class);
        $this->seed(HouseholdSeeder::class);
    });

    it('does not duplicate ZA Protection user dependants, properties, or mortgages on re-run', function () {
        $this->seed(TestUsersSeeder::class);

        $user = User::where('email', 'za-protection-test@example.com')->firstOrFail();

        expect(FamilyMember::where('user_id', $user->id)->where('is_dependent', true)->count())->toBe(2);
        expect(Property::where('user_id', $user->id)->count())->toBe(1);
        expect(Mortgage::where('user_id', $user->id)->count())->toBe(1);

        $this->seed(TestUsersSeeder::class);

        expect(FamilyMember::where('user_id', $user->id)->where('is_dependent', true)->count())->toBe(2);
        expect(Property::where('user_id', $user->id)->count())->toBe(1);
        expect(Mortgage::where('user_id', $user->id)->count())->toBe(1);
    });
});
