<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Unit/Services', 'Unit/Observers');

// Agent tests that need database access (RefreshDatabase)
uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Unit/Agents/ProtectionAgentTest.php', 'Unit/Agents/SavingsAgentTest.php', 'Unit/Agents/GoalsAgentTest.php', 'Unit/Agents/SavingsAgentGoalsTest.php', 'Unit/Agents/ProtectionAgentGoalsTest.php', 'Unit/Agents/EstateAgentGoalsTest.php', 'Unit/Agents/RetirementAgentGoalsTest.php');

// BaseAgentTest is pure unit tests, no database needed
uses(Tests\TestCase::class)->in('Unit/Agents/BaseAgentTest.php');

// Core DB-backed tests (Jurisdiction models, TaxYearResolver, ActiveJurisdictions,
// JurisdictionDetectionObserver)
uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in(
    'Unit/Core/Models',
    'Unit/Core/TaxYear/TaxYearResolverDbTest.php',
    'Unit/Core/Jurisdiction/ActiveJurisdictionsDbTest.php',
    'Unit/Core/Observers',
);

// Core middleware tests need the app but not the database
uses(Tests\TestCase::class)->in('Unit/Core/Http/Middleware');

// Architecture tests that use Laravel helpers (base_path, app) — Pest's arch()
// macro handles the helper-free cases; files using describe/it + base_path()
// need the Laravel application bootstrapped.
uses(Tests\TestCase::class)->in('Architecture');

// Country-pack feature tests live under packs/*/tests/Feature and need both
// the Laravel TestCase (for getJson) and RefreshDatabase (so pack-owned
// tables can be touched in isolation).
uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in(
    __DIR__ . '/../packs/country-xx-smoke/tests/Feature',
    __DIR__ . '/../packs/country-za/tests/Unit',
    __DIR__ . '/../packs/country-za/tests/Feature',
);

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Integration');

// Global setup for all tests that need TaxConfiguration
beforeEach(function () {
    // Ensure active tax configuration exists for tests
    if (class_exists(\App\Models\TaxConfiguration::class)) {
        if (! \App\Models\TaxConfiguration::where('is_active', true)->exists()) {
            \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
        }
    }
})->in('Feature', 'Unit/Services', 'Unit/Observers', 'Unit/Agents/ProtectionAgentTest.php', 'Unit/Agents/SavingsAgentTest.php', 'Unit/Agents/GoalsAgentTest.php', 'Unit/Agents/SavingsAgentGoalsTest.php', 'Unit/Agents/ProtectionAgentGoalsTest.php', 'Unit/Agents/EstateAgentGoalsTest.php', 'Unit/Agents/RetirementAgentGoalsTest.php', 'Integration', 'Unit/Core/Models', 'Unit/Core/TaxYear/TaxYearResolverDbTest.php', 'Unit/Core/Jurisdiction/ActiveJurisdictionsDbTest.php');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
