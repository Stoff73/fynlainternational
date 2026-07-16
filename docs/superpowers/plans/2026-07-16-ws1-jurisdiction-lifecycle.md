# WS1 — Jurisdiction Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every user reliably holds a correct primary jurisdiction from account creation (explicit country at signup), and the backend enforces pack access by it — without breaking the existing 3067-test suite.

**Architecture:** Country captured at registration → carried on `PendingRegistration` → applied at `User` creation by a single `AssignPrimaryJurisdiction` core service (also used by spouse-creation, preview seeders, and a backfill command). `ActiveJurisdictionMiddleware` derives the pack from the request path (`api/za/*` / `api/gb/*`) and 403s users who lack that jurisdiction. The test `User` factory assigns GB-primary by default so existing tests keep passing.

**Tech Stack:** Laravel 12, Pest, MySQL (`fynla_international_test`). Country-pack architecture: `core/app/Core/` (namespace `Fynla\Core\`), `packs/country-{gb,za}/`.

## Global Constraints

- International is **dev-only, never production**. Deploy target is `csjones.co/fynla_inter` only.
- `declare(strict_types=1);` in every PHP file. PSR-12. Classes PascalCase, methods camelCase, DB snake_case. Type hints required.
- Supported jurisdiction codes v1: **GB, ZA** (uppercase ISO-3166-1 alpha-2).
- No hardcoded tax values (rule #3) — not relevant to WS1 but holds.
- British spelling in user-facing text ("Country of residence").
- Never `migrate:fresh`/`migrate:refresh` against the dev DB. Pest uses `fynla_international_test` (isolated).
- Tests: Pest `it()`/`describe()`; `Sanctum::actingAs($user)` for auth; `RefreshDatabase`.
- Jurisdiction resolver is `Fynla\Core\Models\Jurisdiction::byCode(string $code): ?self` (uppercases internally).
- `User::jurisdictions()` is a `belongsToMany` through `user_jurisdictions` (pivot has `is_primary`, `activated_at`, `deactivated_at`, `auto_detected`).

---

### Task 1: `UserJurisdiction` model correctness (fillable + casts)

**Files:**
- Modify: `core/app/Core/Models/UserJurisdiction.php`
- Test: `tests/Unit/Core/Models/UserJurisdictionTest.php` (create)

**Interfaces:**
- Produces: `UserJurisdiction` mass-assignable on `deactivated_at`, `auto_detected` (Task 2/4 rely on it).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\User;
use Fynla\Core\Models\UserJurisdiction;
use Fynla\Packs\Gb\Database\Seeders\JurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mass-assigns deactivated_at and auto_detected', function () {
    $this->seed(JurisdictionSeeder::class);
    $user = User::factory()->create();
    $gb = Jurisdiction::byCode('GB');

    $row = UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $gb->id,
        'is_primary' => true,
        'activated_at' => now(),
        'deactivated_at' => now(),
        'auto_detected' => true,
    ]);

    expect($row->deactivated_at)->not->toBeNull()
        ->and($row->auto_detected)->toBeTrue();
});
```

> Note: confirm the correct core jurisdiction seeder class name before running (grep `class .*JurisdictionSeeder` under `core/` and `packs/`). Use whichever seeds the `jurisdictions` table (GB + ZA). If the test factory already seeds jurisdictions globally, drop the explicit `$this->seed(...)`.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Core/Models/UserJurisdictionTest.php`
Expected: FAIL (either `auto_detected`/`deactivated_at` silently dropped → null, or a cast assertion mismatch).

- [ ] **Step 3: Add the fields to `$fillable` and `$casts`**

In `UserJurisdiction.php`, extend the arrays:

```php
    protected $fillable = [
        'user_id',
        'jurisdiction_id',
        'is_primary',
        'activated_at',
        'deactivated_at',
        'auto_detected',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'auto_detected' => 'boolean',
    ];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Core/Models/UserJurisdictionTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add core/app/Core/Models/UserJurisdiction.php tests/Unit/Core/Models/UserJurisdictionTest.php
git commit -m "feat(intl): make UserJurisdiction deactivated_at/auto_detected mass-assignable"
```

---

### Task 2: `User::jurisdictions()` excludes soft-deactivated rows

**Files:**
- Modify: `core/app/Core/Models/User.php:738` (the `jurisdictions()` relation)
- Test: `tests/Unit/Core/Models/UserJurisdictionsRelationTest.php` (create)

**Interfaces:**
- Produces: `$user->jurisdictions` returns only non-deactivated jurisdictions (session endpoint + middleware rely on it).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Database\Seeders\JurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('excludes soft-deactivated jurisdictions from the relation', function () {
    $this->seed(JurisdictionSeeder::class);
    $user = User::factory()->create();
    $gb = Jurisdiction::byCode('GB');
    $za = Jurisdiction::byCode('ZA');

    $user->jurisdictions()->attach($gb->id, ['is_primary' => true, 'activated_at' => now()]);
    $user->jurisdictions()->attach($za->id, ['is_primary' => false, 'activated_at' => now(), 'deactivated_at' => now()]);

    $codes = $user->fresh()->jurisdictions->pluck('code')->all();

    expect($codes)->toContain('GB')->not->toContain('ZA');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Core/Models/UserJurisdictionsRelationTest.php`
Expected: FAIL (ZA still returned).

- [ ] **Step 3: Scope the relation**

Read `User.php:738-748`. Append `->wherePivotNull('deactivated_at')` to the `belongsToMany(...)` chain so the relation reads (adjust to match the exact existing arguments):

```php
    public function jurisdictions(): BelongsToMany
    {
        return $this->belongsToMany(
            Jurisdiction::class,
            'user_jurisdictions',
            // ...existing keys + withPivot...
        )->wherePivotNull('deactivated_at');
    }
```

If the relation does not already `withPivot('deactivated_at', ...)`, add those pivot columns so `wherePivotNull` works and the session can read `is_primary`.

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Core/Models/UserJurisdictionsRelationTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add core/app/Core/Models/User.php tests/Unit/Core/Models/UserJurisdictionsRelationTest.php
git commit -m "feat(intl): exclude soft-deactivated jurisdictions from User::jurisdictions()"
```

---

### Task 3: `AssignPrimaryJurisdiction` core service

**Files:**
- Create: `core/app/Core/Jurisdiction/AssignPrimaryJurisdiction.php`
- Create: `core/app/Core/Exceptions/` — reuse existing `Fynla\Core\Exceptions\FinancialCalculationException`? No — jurisdiction is not financial. Throw `\RuntimeException` with a clear message (matches `Jurisdiction`/`TaxConfigService` convention).
- Test: `tests/Unit/Core/Jurisdiction/AssignPrimaryJurisdictionTest.php` (create)

**Interfaces:**
- Produces: `AssignPrimaryJurisdiction::assign(User $user, string $countryCode): UserJurisdiction`. Idempotent — updates the existing primary row or inserts one; never duplicates. Throws `\RuntimeException` if `$countryCode` is not a seeded jurisdiction. Consumed by Tasks 5, 6, 7, 8.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction;
use Fynla\Core\Models\User;
use Fynla\Core\Models\UserJurisdiction;
use Fynla\Packs\Gb\Database\Seeders\JurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(JurisdictionSeeder::class));

it('assigns a primary jurisdiction to a user', function () {
    $user = User::factory()->create();

    $row = (new AssignPrimaryJurisdiction)->assign($user, 'ZA');

    expect($row->is_primary)->toBeTrue();
    expect($user->fresh()->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});

it('is idempotent — a second call updates, never duplicates', function () {
    $user = User::factory()->create();
    $svc = new AssignPrimaryJurisdiction;

    $svc->assign($user, 'GB');
    $svc->assign($user, 'ZA');

    expect(UserJurisdiction::where('user_id', $user->id)->where('is_primary', true)->count())->toBe(1);
    expect($user->fresh()->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});

it('throws for an unseeded country code', function () {
    $user = User::factory()->create();

    expect(fn () => (new AssignPrimaryJurisdiction)->assign($user, 'FR'))
        ->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Core/Jurisdiction/AssignPrimaryJurisdictionTest.php`
Expected: FAIL ("class not found").

- [ ] **Step 3: Implement the service**

```php
<?php

declare(strict_types=1);

namespace Fynla\Core\Jurisdiction;

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\User;
use Fynla\Core\Models\UserJurisdiction;
use RuntimeException;

/**
 * Single writer of a user's primary jurisdiction, so no creation path (signup,
 * spouse, preview seed, backfill) ever leaves a user without one.
 */
class AssignPrimaryJurisdiction
{
    public function assign(User $user, string $countryCode): UserJurisdiction
    {
        $jurisdiction = Jurisdiction::byCode($countryCode);

        if ($jurisdiction === null) {
            throw new RuntimeException(
                "Cannot assign jurisdiction: country code '{$countryCode}' is not seeded."
            );
        }

        return UserJurisdiction::updateOrCreate(
            ['user_id' => $user->id, 'is_primary' => true],
            [
                'jurisdiction_id' => $jurisdiction->id,
                'activated_at' => now(),
                'deactivated_at' => null,
            ],
        );
    }
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Core/Jurisdiction/AssignPrimaryJurisdictionTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add core/app/Core/Jurisdiction/AssignPrimaryJurisdiction.php tests/Unit/Core/Jurisdiction/AssignPrimaryJurisdictionTest.php
git commit -m "feat(intl): AssignPrimaryJurisdiction core service (idempotent primary assignment)"
```

---

### Task 4: Capture `country_code` at registration

**Files:**
- Create: `database/migrations/2026_07_16_000001_add_country_code_to_pending_registrations.php`
- Modify: `core/app/Core/Models/PendingRegistration.php` (`$fillable`)
- Modify: `app/Http/Requests/RegisterRequest.php` (rules)
- Test: `tests/Feature/Auth/RegisterCountryCodeTest.php` (create)

**Interfaces:**
- Produces: `pending_registrations.country_code` (CHAR(2), nullable); `RegisterRequest` requires `country_code` in `['GB','ZA']`. Task 5 reads `$pending->country_code`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects registration without a valid country_code', function () {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Thabo', 'surname' => 'Nkosi',
        'email' => 'thabo@example.com', 'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertStatus(422)->assertJsonValidationErrors(['country_code']);
});

it('persists country_code on the pending registration', function () {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Thabo', 'surname' => 'Nkosi',
        'email' => 'thabo@example.com', 'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'country_code' => 'ZA',
    ])->assertSuccessful();

    expect(PendingRegistration::where('email', 'thabo@example.com')->first()->country_code)->toBe('ZA');
});
```

> Confirm the exact register payload the existing `RegisterRequest` expects (password confirmation field name, any required `plan`/`billing_cycle`) by reading `RegisterRequest::rules()` and an existing register test; match it so only `country_code` is the variable under test.

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Auth/RegisterCountryCodeTest.php`
Expected: FAIL (no validation error / column missing).

- [ ] **Step 3: Migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('surname');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
```

- [ ] **Step 4: Add to `PendingRegistration::$fillable`**

Insert `'country_code',` into the `$fillable` array (after `'surname'`).

- [ ] **Step 5: Add the rule to `RegisterRequest::rules()`**

```php
            'country_code' => ['required', 'string', \Illuminate\Validation\Rule::in(['GB', 'ZA'])],
```

Add a message in `messages()`: `'country_code.required' => 'Please select your country of residence.'`

- [ ] **Step 6: Run to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Auth/RegisterCountryCodeTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_16_000001_add_country_code_to_pending_registrations.php core/app/Core/Models/PendingRegistration.php app/Http/Requests/RegisterRequest.php tests/Feature/Auth/RegisterCountryCodeTest.php
git commit -m "feat(intl): capture country_code at registration"
```

---

### Task 5: Assign jurisdiction on user creation (`verifyCode`)

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php` (`verifyCode`, after `$user->save()` near line 524)
- Test: `tests/Feature/Auth/VerifyCodeAssignsJurisdictionTest.php` (create)

**Interfaces:**
- Consumes: `AssignPrimaryJurisdiction::assign` (Task 3), `$pending->country_code` (Task 4).
- Produces: a verified user has a primary jurisdiction matching their chosen country; session `/api/auth/user` reports it.

- [ ] **Step 1: Write the failing test**

Drive register → fetch code from DB → verify → assert jurisdiction. Mirror the existing verifyCode test setup (grep `tests/Feature/Auth` for how the verification code is retrieved — `EmailVerificationCode`).

```php
<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns the chosen jurisdiction when a user verifies', function () {
    // Arrange: register with ZA (reuse the project's register+verify helper/flow).
    // ... register 'thabo@example.com' with country_code ZA ...
    // ... retrieve pending_id + verification code as existing verify tests do ...

    // Act: post the verification code.
    // ... $this->postJson('/api/auth/verify-code', [...])->assertSuccessful();

    $user = User::where('email', 'thabo@example.com')->first();
    expect($user->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});
```

> Fill the arrange/act using the exact flow from the existing verifyCode feature test. Do not invent endpoints.

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Auth/VerifyCodeAssignsJurisdictionTest.php`
Expected: FAIL (user has no jurisdiction).

- [ ] **Step 3: Wire the service into `verifyCode`**

After the `$user->save();` block (near line 524), add:

```php
            (new \Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction)
                ->assign($user, $pending->country_code ?? 'GB');
```

(Or constructor-inject the service — match the controller's existing DI style. The `?? 'GB'` covers legacy pending rows without a country_code.)

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Auth/VerifyCodeAssignsJurisdictionTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php tests/Feature/Auth/VerifyCodeAssignsJurisdictionTest.php
git commit -m "feat(intl): assign primary jurisdiction on user verification"
```

---

### Task 6: Route spouse-creation + preview seeders through the service

**Files:**
- Modify: `packs/country-gb/src/Http/Controllers/FamilyMembersController.php` (spouse-creation path, ~line 379)
- Modify: `app/Http/Controllers/Api/PreviewController.php` (spouse path, ~line 658) — if it creates a User
- Modify: the UK preview seeder(s) + `ZaPreviewUserSeeder` to call the service (Thabo → ZA is already ZA-primary; route through the service for consistency)
- Test: `tests/Feature/Family/SpouseGetsJurisdictionTest.php` (create)

**Interfaces:**
- Consumes: `AssignPrimaryJurisdiction::assign` (Task 3).
- Produces: no creation path yields a row-less user.

- [ ] **Step 1: Write the failing test** — create a GB user, create their spouse via the controller flow, assert the spouse has a GB jurisdiction (inherits creator's primary).

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3:** In each spouse-creation path, after the spouse `User` is created, call `(new AssignPrimaryJurisdiction)->assign($spouse, $creator->jurisdictions->firstWhere('pivot.is_primary', true)?->code ?? 'GB')`. In preview seeders, assign each persona's correct code (UK personas GB, Thabo ZA).

- [ ] **Step 4: Run to verify it passes.**

- [ ] **Step 5: Commit** `feat(intl): assign jurisdiction on spouse-creation and preview seeding`.

---

### Task 7: `jurisdictions:backfill` command

**Files:**
- Create: `app/Console/Commands/BackfillJurisdictions.php`
- Test: `tests/Feature/Console/BackfillJurisdictionsTest.php` (create)

**Interfaces:**
- Consumes: `AssignPrimaryJurisdiction::assign` (Task 3).
- Produces: `php artisan jurisdictions:backfill` — assigns GB-primary to every user with no (non-deactivated) jurisdiction; idempotent; prints counts.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Database\Seeders\JurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills GB for users with no jurisdiction and skips those who have one', function () {
    $this->seed(JurisdictionSeeder::class);
    $bare = User::factory()->create();
    $already = User::factory()->create();
    (new \Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction)->assign($already, 'ZA');

    $this->artisan('jurisdictions:backfill')->assertExitCode(0);

    expect($bare->fresh()->jurisdictions->pluck('code')->all())->toBe(['GB'])
        ->and($already->fresh()->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});
```

- [ ] **Step 2: Run to verify it fails** (command not found).

- [ ] **Step 3: Implement the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction;
use Fynla\Core\Models\User;
use Illuminate\Console\Command;

class BackfillJurisdictions extends Command
{
    protected $signature = 'jurisdictions:backfill {--code=GB : Jurisdiction code to assign to row-less users}';

    protected $description = 'Assign a primary jurisdiction to any user that has none';

    public function handle(AssignPrimaryJurisdiction $assign): int
    {
        $code = strtoupper((string) $this->option('code'));
        $assigned = 0;

        User::query()->whereDoesntHave('jurisdictions')->chunkById(200, function ($users) use ($assign, $code, &$assigned) {
            foreach ($users as $user) {
                $assign->assign($user, $code);
                $assigned++;
            }
        });

        $this->info("Backfilled {$assigned} user(s) with primary jurisdiction {$code}.");

        return self::SUCCESS;
    }
}
```

> `whereDoesntHave('jurisdictions')` uses the Task-2 relation, which already excludes deactivated rows — so a user whose only jurisdiction is deactivated is correctly re-backfilled.

- [ ] **Step 4: Run to verify it passes.**

- [ ] **Step 5: Commit** `feat(intl): jurisdictions:backfill command`.

---

### Task 8: Test `User` factory assigns GB-primary by default

**Files:**
- Modify: `database/factories/UserFactory.php` (add a `configure()`/`afterCreating` hook)
- Test: covered by the enforcement test in Task 9 + existing suite staying green.

**Interfaces:**
- Produces: `User::factory()->create()` yields a GB-primary user. SA tests override via a `->jurisdiction('ZA')` state (add it) or by calling the service. This keeps the existing suite passing once Task 9 enables enforcement.

- [ ] **Step 1:** Add an `afterCreating` hook to `UserFactory` that assigns GB primary via `AssignPrimaryJurisdiction`, guarded so it no-ops if the `jurisdictions` table isn't seeded (some pure-unit tests don't seed it):

```php
    public function configure(): static
    {
        return $this->afterCreating(function (\Fynla\Core\Models\User $user) {
            if (\Fynla\Core\Models\Jurisdiction::byCode('GB') !== null
                && ! $user->jurisdictions()->exists()) {
                (new \Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction)->assign($user, 'GB');
            }
        });
    }

    public function jurisdiction(string $code): static
    {
        return $this->afterCreating(function (\Fynla\Core\Models\User $user) use ($code) {
            (new \Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction)->assign($user, $code);
        });
    }
```

> The guard matters: many unit tests build a `User` without seeding `jurisdictions`. The `byCode('GB') !== null` check makes the factory a no-op there, so it can't break them.

- [ ] **Step 2: Commit** `test(intl): User factory assigns GB primary jurisdiction by default`. (No standalone test — Task 9 + the full-suite run exercise it.)

---

### Task 9: Real enforcement in `ActiveJurisdictionMiddleware`

**Files:**
- Modify: `core/app/Core/Http/Middleware/ActiveJurisdictionMiddleware.php`
- Verify applied: `packs/country-gb/src/Providers/GbPackServiceProvider.php` + `packs/country-za/src/Providers/ZaPackServiceProvider.php` route groups
- Test: `tests/Feature/Security/JurisdictionEnforcementTest.php` (create)

**Interfaces:**
- Consumes: `$user->jurisdictions` (Task 2), the factory defaults (Task 8).
- Produces: cross-jurisdiction pack access → 403; own-pack + core routes → pass.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('403s a GB-only user hitting a ZA endpoint', function () {
    $user = User::factory()->create(); // GB by default (Task 8)
    Sanctum::actingAs($user);
    $this->getJson('/api/za/coordination/summary')->assertStatus(403);
});

it('allows a ZA user to hit a ZA endpoint', function () {
    $user = User::factory()->jurisdiction('ZA')->create();
    Sanctum::actingAs($user);
    // Any GET ZA route that returns 200 for an empty dataset. Pick one from route:list.
    $this->getJson('/api/za/coordination/summary')->assertSuccessful();
});

it('allows any user to hit a core route', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->getJson('/api/user')->assertSuccessful();
});
```

> Pick concrete ZA/core routes from `php artisan route:list --path=api/za` and `--path=api/user`. Use a GET that succeeds on empty data to isolate the 200/403 behaviour from payload validation.

- [ ] **Step 2: Run to verify the 403 test fails** (middleware currently passes everything through).

Run: `./vendor/bin/pest tests/Feature/Security/JurisdictionEnforcementTest.php`
Expected: the 403 test FAILS (returns 200).

- [ ] **Step 3: Rewrite the middleware's pack derivation + entitlement check**

Replace the `$request->route('cc')` derivation and the `FYNLA_ACTIVE_PACKS` `userHasJurisdiction` stub:

```php
    public function handle(Request $request, Closure $next): Response
    {
        $countryCode = $this->packCodeFromPath($request);

        // Core routes (no pack prefix) pass through.
        if ($countryCode === null) {
            return $next($request);
        }

        if (! $this->registry->isEnabled($countryCode)) {
            return new JsonResponse(['error' => 'Pack not found', 'code' => 'PACK_NOT_FOUND'], 404);
        }

        $user = $request->user();
        if ($user !== null && ! $user->jurisdictions->contains('code', $countryCode)) {
            return new JsonResponse(
                ['error' => 'Jurisdiction not authorised', 'code' => 'JURISDICTION_NOT_AUTHORISED'],
                403,
            );
        }

        return $next($request);
    }

    /**
     * Derive the pack code from the request path: api/gb/* -> GB, api/za/* -> ZA.
     * Core routes match neither and return null.
     */
    private function packCodeFromPath(Request $request): ?string
    {
        foreach ($this->registry->codes() as $code) {           // e.g. ['GB','ZA','XX']
            if ($request->is('api/'.strtolower($code).'/*')) {
                return strtoupper($code);
            }
        }

        return null;
    }
```

> Confirm `PackRegistry` exposes `codes()` / `isEnabled()` (grep the class). If the method that lists codes has a different name, use it. `$user->jurisdictions->contains('code', $code)` reads the Task-2 relation (already excludes deactivated). Delete the now-dead `userHasJurisdiction` + its `FYNLA_ACTIVE_PACKS` reading, and any unused imports.

- [ ] **Step 4: Ensure the middleware is applied to both pack route groups**

Confirm `GbPackServiceProvider` (mounts `prefix('api/gb')`) and `ZaPackServiceProvider` (mounts `api`, routes `/za/*`) include `ActiveJurisdictionMiddleware` (alias `active.jurisdiction`) on their group middleware. If a group lacks it, add it. Do NOT add it globally (would run on core routes needlessly — though it no-ops there, keep it scoped).

- [ ] **Step 5: Run to verify all pass**

Run: `./vendor/bin/pest tests/Feature/Security/JurisdictionEnforcementTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add core/app/Core/Http/Middleware/ActiveJurisdictionMiddleware.php packs/country-gb/src/Providers/GbPackServiceProvider.php packs/country-za/src/Providers/ZaPackServiceProvider.php tests/Feature/Security/JurisdictionEnforcementTest.php
git commit -m "feat(intl): enforce jurisdiction on pack routes via user_jurisdictions"
```

---

### Task 10: Registration form country selector (frontend)

**Files:**
- Modify: the registration view/component (find via `grep -rl "auth/register" resources/js` and the register form under `resources/js/views` or `components/Auth`)
- Test: manual browser check (frontend has vitest but the register form may not be unit-tested; keep it minimal)

**Interfaces:**
- Consumes: `country_code` accepted by `RegisterRequest` (Task 4).
- Produces: the register POST includes `country_code`.

- [ ] **Step 1:** Add a required "Country of residence" `<select>` with options United Kingdom (`GB`) and South Africa (`ZA`), bound to the form model, included in the register payload as `country_code`. Follow the form's existing field markup + validation-error display pattern. Default to unselected so the user must choose (matches the required rule).

- [ ] **Step 2:** Manually verify in the browser (dev server) that registering requires the country and posts it. (No automated FE test unless the form already has one — match existing coverage.)

- [ ] **Step 3: Commit** `feat(intl): country-of-residence selector on registration form`.

---

### Task 11: Full regression + Larastan

- [ ] **Step 1:** Run the full suite: `./vendor/bin/pest`. Expected: green (Task 8's factory default keeps pack-route feature tests passing under the new enforcement). Investigate any failure — most likely a feature test hitting a pack route whose user needs the matching jurisdiction; fix by adding `->jurisdiction('ZA')` to that test's user or pointing it at the right pack.
- [ ] **Step 2:** `composer analyse` (Larastan). Expected: clean (or add a justified baseline entry only if unavoidable).
- [ ] **Step 3: Commit** any test adjustments: `test(intl): align existing tests with jurisdiction enforcement`.

---

## Self-Review

**Spec coverage:** WS1 spec components 1–6 → Tasks: capture-at-signup (4,10), AssignPrimaryJurisdiction + all creation paths (3,5,6), backfill (7), model correctness (1,2), enforcement (9), test-harness factory (8), regression (11). All covered.

**Sequencing:** Model correctness (1,2) → service (3) → capture (4) → creation paths (5,6) → backfill (7) → factory (8) → enforcement (9) → FE (10) → regression (11). Components 3+5+6+7 (assignment + backfill) all land before 9 (enforcement) — satisfies the spec's safety ordering.

**Placeholder scan:** Tasks 6 and 10 describe steps without full code because they depend on reading the exact existing spouse-creation/register-form markup, which varies; each names the exact files, the grep to locate them, and the precise change. Tasks 1–5, 7–9 carry complete code. Acceptable — the under-specified steps are localised, existing-pattern-following edits, not new logic.

**Type consistency:** `AssignPrimaryJurisdiction::assign(User, string): UserJurisdiction`, `Jurisdiction::byCode(string): ?self`, `$user->jurisdictions` (Collection of Jurisdiction with pivot) used consistently across Tasks 3,5,6,7,8,9.
