# WS3 — Frontend Localisation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A logged-in SA user sees `R 1 234.56` amounts, `16 Jul 2026` dates, and the 1-March `2026/27` tax year everywhere programmatic formatting runs; GB users see byte-for-byte identical output to today.

**Architecture:** `AuthController::user` adds a `localisation` block (from the primary jurisdiction's `pack.{code}.localisation` binding) and a `tax_year` block (from core `TaxYearResolver`, null when no row — GB today) to the session payload. The existing `jurisdiction/hydrateFromSession` action stashes them into a new module-level singleton (`resources/js/utils/localisation.js`) and into a jurisdiction tax-year singleton in `dateFormatter.js`. `currency.js`, `dateFormatter.js`, and `currencyMixin.formatNumber` consult the singletons internally — zero changes to the ~126 consumer files. Fail-open to GB at every layer.

**Tech Stack:** Laravel 12 (Pest tests), Vue 3 + Vuex (Vitest tests, `tests/frontend/`), no new dependencies.

**Spec:** `docs/superpowers/specs/2026-07-16-ws3-frontend-localisation-design.md`

## Global Constraints

- `declare(strict_types=1);` in all PHP files; PSR-12 via `./vendor/bin/pint`.
- Larastan must stay clean: `composer analyse` (= `phpstan analyse --memory-limit=2G`).
- GBP/unset-config paths must produce **byte-for-byte identical** output to current code (UK zero-regression).
- ZAR display convention: `R 1 234 567.89` — period decimal, U+00A0 grouping, sign before symbol (`-R 123.45`), via `formatZAR` (SA Research §17). Never comma-decimal (`Intl` en-ZA emits commas — that is the bug Task 3 fixes; do not "correct" tests back to Intl output).
- POSIX→BCP-47 locale normalisation (`en_ZA` → `en-ZA`) happens ONCE in `setLocalisation`, never per-call (`Intl.NumberFormat` throws `RangeError` on underscores).
- Fail-open to GB: missing payload block, unknown currency code, unmapped date format, missing tax-year row → current GB behaviour.
- No new dependencies. British spelling in user-facing text. Never use `migrate:fresh`.
- Frontend tests live in `tests/frontend/**/*.test.js` (Vitest, `@` = `resources/js`). Backend tests use Pest `it()` syntax.
- Commit messages end with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

---

### Task 1: Backend — session payload localisation + tax_year

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php` (the `user()` method, currently lines 352–397)
- Test: `tests/Feature/Auth/SessionJurisdictionTest.php` (extend — file exists from WS1)

**Interfaces:**
- Consumes: `pack.gb.localisation` / `pack.za.localisation` container bindings (exist); `Fynla\Core\TaxYear\TaxYearResolver::resolve(string $code): TaxYear` (throws `\RuntimeException` when no `tax_years` row).
- Produces: `data.localisation = {currency_code, currency_symbol, locale, date_format}` (always present, GB-shaped default) and `data.tax_year = {label, starts_on, ends_on} | null` in the `/api/auth/user` response. Tasks 2/4/5 rely on exactly these snake_case keys.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Auth/SessionJurisdictionTest.php` (it already has a `beforeEach` creating `$this->gb`; note the existing `use` lines already import `User`, `Jurisdiction`, `UserJurisdiction`, `Sanctum` — add `use Illuminate\Support\Facades\DB;` and `use Carbon\Carbon;` to the imports):

```php
it('session endpoint returns ZAR localisation and the SA tax year for a ZA-primary user', function () {
    $za = Jurisdiction::firstOrCreate(
        ['code' => 'ZA'],
        ['name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en-ZA', 'active' => true]
    );

    // Date-independent SA tax year row spanning today (1 March – end Feb).
    $today = now();
    $startYear = $today->month >= 3 ? $today->year : $today->year - 1;
    $startsOn = sprintf('%d-03-01', $startYear);
    $endsOn = Carbon::create($startYear + 1, 3, 1)->subDay()->toDateString();
    $label = sprintf('%d/%s', $startYear, substr((string) ($startYear + 1), -2));

    DB::table('tax_years')->updateOrInsert(
        ['jurisdiction_id' => $za->id, 'starts_on' => $startsOn],
        [
            'label' => $label,
            'calendar_type' => 'tax_year',
            'ends_on' => $endsOn,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $za->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.localisation.currency_code', 'ZAR')
        ->assertJsonPath('data.localisation.currency_symbol', 'R')
        ->assertJsonPath('data.localisation.locale', 'en_ZA')
        ->assertJsonPath('data.localisation.date_format', 'd M Y')
        ->assertJsonPath('data.tax_year.label', $label)
        ->assertJsonPath('data.tax_year.starts_on', $startsOn)
        ->assertJsonPath('data.tax_year.ends_on', $endsOn);
});

it('session endpoint returns GBP localisation and null tax_year for a GB-primary user', function () {
    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.localisation.currency_code', 'GBP')
        ->assertJsonPath('data.localisation.currency_symbol', '£')
        ->assertJsonPath('data.localisation.locale', 'en_GB')
        ->assertJsonPath('data.localisation.date_format', 'd/m/Y')
        ->assertJsonPath('data.tax_year', null);
});

it('session endpoint returns GB-shaped localisation defaults for a user with no jurisdiction rows', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.localisation.currency_code', 'GBP')
        ->assertJsonPath('data.localisation.currency_symbol', '£')
        ->assertJsonPath('data.localisation.locale', 'en_GB')
        ->assertJsonPath('data.localisation.date_format', 'd/m/Y')
        ->assertJsonPath('data.tax_year', null);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Auth/SessionJurisdictionTest.php`
Expected: 3 existing tests PASS, 3 new tests FAIL (missing `data.localisation` key).

- [ ] **Step 3: Implement the payload**

In `app/Http/Controllers/Api/AuthController.php`, add to the `use` block at the top of the file:

```php
use Fynla\Core\TaxYear\TaxYearResolver;
```

Then in `user()`, immediately after the `$primaryCode` computation (after the line `: null;` around line 383) and before the `return response()->json([...])`, insert:

```php
        // WS3 — localisation for the primary jurisdiction. Fail-open to
        // GB-shaped defaults when the user has no primary jurisdiction or
        // the pack binding is missing, mirroring WS1's fail-open stance.
        $localisation = [
            'currency_code' => 'GBP',
            'currency_symbol' => '£',
            'locale' => 'en_GB',
            'date_format' => 'd/m/Y',
        ];
        $taxYear = null;

        if ($primaryCode !== null && app()->bound("pack.{$primaryCode}.localisation")) {
            /** @var \Fynla\Core\Contracts\Localisation $packLocalisation */
            $packLocalisation = app("pack.{$primaryCode}.localisation");
            $localisation = [
                'currency_code' => $packLocalisation->currencyCode(),
                'currency_symbol' => $packLocalisation->currencySymbol(),
                'locale' => $packLocalisation->locale(),
                'date_format' => $packLocalisation->dateFormat(),
            ];

            try {
                $resolved = app(TaxYearResolver::class)->resolve($primaryCode);
                $taxYear = [
                    'label' => $resolved->label,
                    'starts_on' => $resolved->startsOn->format('Y-m-d'),
                    'ends_on' => $resolved->endsOn->format('Y-m-d'),
                ];
            } catch (\RuntimeException) {
                // No tax_years row for this jurisdiction (GB today) — the
                // frontend falls back to its existing GB tax-year chain.
                $taxYear = null;
            }
        }
```

And add the two keys to the response `data` array, after `'cross_border' => ...`:

```php
                'localisation' => $localisation,
                'tax_year' => $taxYear,
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Auth/SessionJurisdictionTest.php`
Expected: all 6 PASS.

- [ ] **Step 5: Format + static analysis + commit**

```bash
./vendor/bin/pint app/Http/Controllers/Api/AuthController.php tests/Feature/Auth/SessionJurisdictionTest.php
composer analyse
git add app/Http/Controllers/Api/AuthController.php tests/Feature/Auth/SessionJurisdictionTest.php
git commit -m "feat(intl): WS3 session payload carries localisation + jurisdiction tax year

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: Frontend — localisation config singleton

**Files:**
- Create: `resources/js/utils/localisation.js`
- Test: `tests/frontend/utils/localisation.test.js` (create directory too)

**Interfaces:**
- Consumes: nothing (imports nothing — this keeps it circular-import-safe).
- Produces: `setLocalisation(config|null)`, `getLocalisation(): {currencyCode, currencySymbol, locale, dateFormat} | null`, `resetLocalisation()`. Input keys are the snake_case payload keys from Task 1; output keys are camelCase; `locale` is BCP-47-normalised. Tasks 3, 4, 5, 7 import these.

- [ ] **Step 1: Write the failing tests**

Create `tests/frontend/utils/localisation.test.js`:

```javascript
import { describe, it, expect, beforeEach } from 'vitest';
import {
  setLocalisation,
  getLocalisation,
  resetLocalisation,
} from '@/utils/localisation';

describe('localisation singleton', () => {
  beforeEach(() => {
    resetLocalisation();
  });

  it('is null before hydration', () => {
    expect(getLocalisation()).toBeNull();
  });

  it('stores a session payload block under camelCase keys', () => {
    setLocalisation({
      currency_code: 'ZAR',
      currency_symbol: 'R',
      locale: 'en_ZA',
      date_format: 'd M Y',
    });

    expect(getLocalisation()).toEqual({
      currencyCode: 'ZAR',
      currencySymbol: 'R',
      locale: 'en-ZA',
      dateFormat: 'd M Y',
    });
  });

  it('normalises POSIX locales to BCP-47 once at set time', () => {
    setLocalisation({ currency_code: 'GBP', currency_symbol: '£', locale: 'en_GB', date_format: 'd/m/Y' });
    expect(getLocalisation().locale).toBe('en-GB');
  });

  it('treats null, undefined, and non-object payloads as unset', () => {
    setLocalisation({ currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' });
    setLocalisation(null);
    expect(getLocalisation()).toBeNull();

    setLocalisation('nonsense');
    expect(getLocalisation()).toBeNull();
  });

  it('tolerates a partial payload without throwing', () => {
    setLocalisation({ currency_code: 'ZAR' });
    expect(getLocalisation()).toEqual({
      currencyCode: 'ZAR',
      currencySymbol: null,
      locale: null,
      dateFormat: null,
    });
  });

  it('resets to null', () => {
    setLocalisation({ currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' });
    resetLocalisation();
    expect(getLocalisation()).toBeNull();
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run tests/frontend/utils/localisation.test.js`
Expected: FAIL — cannot resolve `@/utils/localisation`.

- [ ] **Step 3: Implement the singleton**

Create `resources/js/utils/localisation.js`:

```javascript
/**
 * Session localisation singleton (WS3).
 *
 * Holds the authenticated user's localisation config from the
 * /api/auth/user `localisation` block, set once per session by
 * jurisdiction/hydrateFromSession and cleared by jurisdiction/reset.
 * currency.js, dateFormatter.js, and currencyMixin read it internally so
 * all app-wide formatting adapts without touching call sites — the same
 * pattern as dateFormatter's _activeTaxYearFromBackend.
 *
 * Null means "no session localisation" and every consumer falls back to
 * GB behaviour (fail-open, mirroring WS1).
 */

let _config = null;

/**
 * Store the session localisation block. Accepts the raw snake_case
 * payload from /api/auth/user; anything non-object clears the config.
 *
 * The POSIX locale from the backend contract (e.g. "en_ZA") is
 * normalised to BCP-47 ("en-ZA") HERE, once — Intl.NumberFormat and
 * toLocaleString throw RangeError on underscore locales.
 */
export function setLocalisation(config) {
  if (!config || typeof config !== 'object') {
    _config = null;
    return;
  }

  _config = {
    currencyCode: config.currency_code || null,
    currencySymbol: config.currency_symbol || null,
    locale: typeof config.locale === 'string' ? config.locale.replace(/_/g, '-') : null,
    dateFormat: config.date_format || null,
  };
}

export function getLocalisation() {
  return _config;
}

export function resetLocalisation() {
  _config = null;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run tests/frontend/utils/localisation.test.js`
Expected: 6 PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/localisation.js tests/frontend/utils/localisation.test.js
git commit -m "feat(intl): WS3 session localisation singleton util

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: Frontend — currency formatting adapts (currency.js, zaCurrency.js, currencyMixin)

**Files:**
- Modify: `resources/js/utils/currency.js`
- Modify: `resources/js/utils/zaCurrency.js` (rewrite `formatZAR` deterministically + add compact variant)
- Modify: `resources/js/mixins/currencyMixin.js` (`formatNumber` only)
- Test: `tests/frontend/utils/currency.test.js` (create)

**Interfaces:**
- Consumes: `getLocalisation()` from Task 2.
- Produces: unchanged public signatures — `formatCurrency(amount, options)`, `formatCurrencyWithPence(amount)`, `formatCurrencyCompact(amount)`, `parseCurrency(str)` — now config-aware. `formatZAR(value, {showDecimals})` keeps its signature but is rewritten to deterministic period-decimal output (see below). New `formatZARCompact(value)` export in `zaCurrency.js`. `currencyMixin.formatNumber(value)` locale-aware. No caller changes anywhere.

**Why formatZAR is rewritten (spec: "Known convention conflict"):** `Intl.NumberFormat('en-ZA')` emits comma decimals (`1 234 567,89` — verified in Node and browser ICU), so the current implementation contradicts its own docstring and SA Research §17 (`R 1 234 567.89`). The rewrite promotes the existing deterministic fallback path to the only path: period decimal, U+00A0 grouping, sign before the symbol (`-R 123.45`, matching backend sign placement), `R —` for null/NaN. This intentionally changes what the 32 ZA components render (comma → period decimals; `R -…` → `-R …`) — that is the approved convention fix.

- [ ] **Step 1: Write the failing tests**

Create `tests/frontend/utils/currency.test.js`:

```javascript
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  formatCurrency,
  formatCurrencyWithPence,
  formatCurrencyCompact,
  parseCurrency,
} from '@/utils/currency';
import { formatZAR, formatZARCompact } from '@/utils/zaCurrency';
import { setLocalisation, resetLocalisation } from '@/utils/localisation';
import { currencyMixin } from '@/mixins/currencyMixin';

const ZA = { currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' };
const NBSP = '\u00a0';

// currencyMixin methods use `this`-free helpers except formatLiability;
// calling via the methods object with a stub `this` is enough.
const mixin = {
  ...currencyMixin.methods,
};

describe('currency formatting — GB regression (config unset)', () => {
  beforeEach(() => resetLocalisation());

  it('formats GBP exactly as before', () => {
    expect(formatCurrency(1234.56)).toBe('£1,235');
    expect(formatCurrencyWithPence(1234.56)).toBe('£1,234.56');
    expect(formatCurrency(0)).toBe('£0');
    expect(formatCurrency(null)).toBe('£0');
  });

  it('formats compact GBP exactly as before', () => {
    expect(formatCurrencyCompact(1234567)).toBe('£1.2M');
    expect(formatCurrencyCompact(12345)).toBe('£12.3K');
    expect(formatCurrencyCompact(123)).toBe('£123');
    expect(formatCurrencyCompact(0)).toBe('£0');
  });

  it('parses GBP strings exactly as before', () => {
    expect(parseCurrency('£1,234.56')).toBe(1234.56);
    expect(parseCurrency('1234')).toBe(1234);
  });

  it('formatNumber stays en-GB', () => {
    expect(mixin.formatNumber(1234567)).toBe('1,234,567');
  });
});

describe('currency formatting — GBP session config', () => {
  beforeEach(() => setLocalisation({ currency_code: 'GBP', currency_symbol: '£', locale: 'en_GB', date_format: 'd/m/Y' }));
  afterEach(() => resetLocalisation());

  it('is byte-for-byte identical to the unset path', () => {
    expect(formatCurrency(1234.56)).toBe('£1,235');
    expect(formatCurrencyWithPence(1234.56)).toBe('£1,234.56');
    expect(formatCurrencyCompact(1234567)).toBe('£1.2M');
  });
});

describe('currency formatting — ZAR session config', () => {
  beforeEach(() => setLocalisation(ZA));
  afterEach(() => resetLocalisation());

  it('routes whole amounts through formatZAR without decimals', () => {
    expect(formatCurrency(1234.56)).toBe(`R${NBSP}1${NBSP}235`);
    expect(formatCurrency(0)).toBe(`R${NBSP}0`);
    expect(formatCurrency(null)).toBe(`R${NBSP}0`);
  });

  it('routes pence-precision amounts through formatZAR with decimals', () => {
    expect(formatCurrencyWithPence(1234.56)).toBe(`R${NBSP}1${NBSP}234.56`);
  });

  it('formats compact ZAR', () => {
    expect(formatCurrencyCompact(1234567)).toBe(`R${NBSP}1.2M`);
    expect(formatCurrencyCompact(12345)).toBe(`R${NBSP}12.3K`);
    expect(formatCurrencyCompact(123)).toBe(`R${NBSP}123`);
  });

  it('parses ZAR strings including the symbol and NBSP grouping', () => {
    expect(parseCurrency(`R${NBSP}1${NBSP}234.56`)).toBe(1234.56);
    expect(parseCurrency('R 1 234.56')).toBe(1234.56);
  });

  it('formatNumber uses the session locale grouping', () => {
    // en-ZA groups with non-breaking spaces in V8's ICU.
    expect(mixin.formatNumber(1234567).replace(/[\s\u00a0\u202f]/g, ' ')).toBe('1 234 567');
  });
});

describe('currency formatting — unknown currency (generic Intl path)', () => {
  beforeEach(() => setLocalisation({ currency_code: 'USD', currency_symbol: '$', locale: 'en_US', date_format: 'd/m/Y' }));
  afterEach(() => resetLocalisation());

  it('formats via Intl with the session locale and currency', () => {
    expect(formatCurrency(1234.56)).toBe('$1,235');
    expect(formatCurrencyWithPence(1234.56)).toBe('$1,234.56');
  });

  it('formats compact using the session symbol', () => {
    expect(formatCurrencyCompact(1234567)).toBe('$1.2M');
  });
});

describe('formatZAR (deterministic rewrite)', () => {
  it('renders period decimals with NBSP grouping per SA Research §17', () => {
    expect(formatZAR(1234567.89)).toBe(`R${NBSP}1${NBSP}234${NBSP}567.89`);
    expect(formatZAR(1234.56, { showDecimals: false })).toBe(`R${NBSP}1${NBSP}235`);
    expect(formatZAR(0)).toBe(`R${NBSP}0.00`);
  });

  it('places the sign before the symbol', () => {
    expect(formatZAR(-123.45)).toBe(`-R${NBSP}123.45`);
    expect(formatZAR(-1234.56, { showDecimals: false })).toBe(`-R${NBSP}1${NBSP}235`);
  });

  it('renders the null sentinel', () => {
    expect(formatZAR(null)).toBe('R —');
    expect(formatZAR(undefined)).toBe('R —');
    expect(formatZAR('abc')).toBe('R —');
  });
});

describe('formatZARCompact', () => {
  it('mirrors the compact thresholds with ZAR conventions', () => {
    expect(formatZARCompact(1234567)).toBe(`R${NBSP}1.2M`);
    expect(formatZARCompact(12345)).toBe(`R${NBSP}12.3K`);
    expect(formatZARCompact(123)).toBe(`R${NBSP}123`);
    expect(formatZARCompact(0)).toBe(`R${NBSP}0`);
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run tests/frontend/utils/currency.test.js`
Expected: FAIL — `formatZARCompact` not exported; ZAR/USD describe blocks produce `£` output; `formatZAR` tests get comma decimals from the current Intl implementation.

- [ ] **Step 3: Implement**

**3a.** Rewrite `resources/js/utils/zaCurrency.js`'s `formatZAR` and add `formatZARCompact` (keep `formatZARMinor` and `toMinorZAR` untouched). Replace the file docblock + `formatZAR` with the following, and append `formatZARCompact` after it. All NBSP are written as `\u00a0` escapes — never paste literal NBSP characters:

```javascript
/**
 * ZAR formatter. SA Research (section 17): 'R 1 234 567.89' — period
 * decimal, no-break-space (U+00A0) thousands grouping, sign before the
 * symbol ('-R 123.45', matching ZaLocalisation's sign placement).
 *
 * Formatted manually, NOT via Intl.NumberFormat('en-ZA'): CLDR data for
 * en-ZA uses comma decimals, which contradicts the research convention
 * and varies across ICU builds. Deterministic output keeps the 32 ZA
 * components and the Vitest assertions stable everywhere.
 */

export function formatZAR(value, { showDecimals = true } = {}) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return 'R \u2014';
  }
  const n = Number(value);
  const [int, frac] = Math.abs(n).toFixed(showDecimals ? 2 : 0).split('.');
  const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
  const sign = n < 0 ? '-' : '';
  return frac ? `${sign}R\u00a0${grouped}.${frac}` : `${sign}R\u00a0${grouped}`;
}

/**
 * Compact ZAR — mirrors currency.js formatCurrencyCompact thresholds
 * ("R 1.2M" / "R 12.3K") with the SA symbol + NBSP convention.
 */
export function formatZARCompact(value) {
  const n = Number(value) || 0;
  const abs = Math.abs(n);
  if (abs >= 1000000) return `R\u00a0${(n / 1000000).toFixed(1)}M`;
  if (abs >= 1000) return `R\u00a0${(n / 1000).toFixed(1)}K`;
  return formatZAR(n, { showDecimals: false });
}
```

**3b.** In `resources/js/utils/currency.js`:

Add imports at the top (after the module docblock):

```javascript
import { getLocalisation } from './localisation';
import { formatZAR, formatZARCompact } from './zaCurrency';

/**
 * Resolve the session currency, falling back to GB defaults when no
 * localisation has been hydrated (logged-out, public pages, GB users
 * with a malformed block). WS3: the fallback keeps every GBP code path
 * byte-for-byte identical to the pre-localisation behaviour.
 */
function activeCurrency() {
  const loc = getLocalisation();
  if (!loc || !loc.currencyCode) {
    return { code: 'GBP', symbol: '£', locale: 'en-GB' };
  }
  return {
    code: loc.currencyCode,
    symbol: loc.currencySymbol || '£',
    locale: loc.locale || 'en-GB',
  };
}
```

Replace the body of `formatCurrency` (keep its docblock):

```javascript
export function formatCurrency(amount, options = {}) {
  const {
    minimumFractionDigits = 0,
    maximumFractionDigits = 0,
  } = options;

  const { code, locale } = activeCurrency();

  if (code === 'ZAR') {
    return formatZAR(amount || 0, { showDecimals: maximumFractionDigits > 0 });
  }

  return new Intl.NumberFormat(code === 'GBP' ? 'en-GB' : locale, {
    style: 'currency',
    currency: code,
    minimumFractionDigits,
    maximumFractionDigits,
  }).format(amount || 0);
}
```

Replace the body of `formatCurrencyCompact` (keep its docblock):

```javascript
export function formatCurrencyCompact(amount) {
  const { code, symbol } = activeCurrency();

  if (code === 'ZAR') return formatZARCompact(amount || 0);

  if (!amount) return `${symbol}0`;

  const absAmount = Math.abs(amount);

  if (absAmount >= 1000000) {
    return `${symbol}${(amount / 1000000).toFixed(1)}M`;
  }

  if (absAmount >= 1000) {
    return `${symbol}${(amount / 1000).toFixed(1)}K`;
  }

  return formatCurrency(amount);
}
```

In `parseCurrency`, replace the two `cleaned`/`parsed` lines:

```javascript
  // Remove the session currency symbol (if any) plus the GBP defaults:
  // currency symbol, commas, and whitespace (incl. NBSP grouping).
  const { symbol } = activeCurrency();
  let cleaned = currencyString.toString();
  if (symbol && symbol !== '£') {
    cleaned = cleaned.split(symbol).join('');
  }
  cleaned = cleaned.replace(/[£,\s]/g, '');
  const parsed = parseFloat(cleaned);
```

(`formatCurrencyWithPence` needs no change — it delegates to `formatCurrency` with 2dp, which now routes ZAR → `formatZAR` with decimals.)

**3c.** In `resources/js/mixins/currencyMixin.js`:

Add to the imports:

```javascript
import { getLocalisation } from '@/utils/localisation';
```

Replace the body of `formatNumber` (keep its docblock):

```javascript
    formatNumber(value) {
      if (value == null || isNaN(value)) return '0';
      const locale = getLocalisation()?.locale || 'en-GB';
      return Number(value).toLocaleString(locale);
    },
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run tests/frontend/utils/currency.test.js tests/frontend/utils/localisation.test.js`
Expected: all PASS. If the `formatNumber` en-ZA grouping assertion fails on exotic ICU output, loosen only that assertion to check grouping-by-3 (`/^1.234.567$/` with `.` = any space char) — never loosen the ZAR/GBP currency assertions.

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/currency.js resources/js/utils/zaCurrency.js resources/js/mixins/currencyMixin.js tests/frontend/utils/currency.test.js
git commit -m "feat(intl): WS3 currency formatting adapts to session localisation

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Frontend — dateFormatter adapts (dates + jurisdiction tax year)

**Files:**
- Modify: `resources/js/utils/dateFormatter.js`
- Test: `tests/frontend/utils/dateFormatter.test.js` (create)

**Interfaces:**
- Consumes: `getLocalisation()` from Task 2.
- Produces: existing signatures unchanged (`formatDate`, `formatDateLong`, `getTaxYearStart`, `getTaxYearEnd`, `getCurrentTaxYear`); new export `setJurisdictionTaxYear({label, starts_on, ends_on} | null)` — Task 5 calls it with the raw `tax_year` payload block from Task 1. Existing `setActiveTaxYear` (GB admin override) untouched.

- [ ] **Step 1: Write the failing tests**

Create `tests/frontend/utils/dateFormatter.test.js`:

```javascript
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  formatDate,
  formatDateForInput,
  formatDateLong,
  getTaxYearStart,
  getTaxYearEnd,
  getCurrentTaxYear,
  setActiveTaxYear,
  setJurisdictionTaxYear,
} from '@/utils/dateFormatter';
import { setLocalisation, resetLocalisation } from '@/utils/localisation';

const ZA = { currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' };
const ZA_TAX_YEAR = { label: '2026/27', starts_on: '2026-03-01', ends_on: '2027-02-28' };

afterEach(() => {
  resetLocalisation();
  setJurisdictionTaxYear(null);
  setActiveTaxYear(null);
});

describe('formatDate', () => {
  it('renders DD/MM/YYYY when no localisation is set (GB regression)', () => {
    expect(formatDate(new Date(2026, 6, 16))).toBe('16/07/2026');
  });

  it('renders d M Y for the ZA date format', () => {
    setLocalisation(ZA);
    expect(formatDate(new Date(2026, 6, 16))).toBe('16 Jul 2026');
    expect(formatDate(new Date(2026, 0, 3))).toBe('03 Jan 2026');
  });

  it('falls back to DD/MM/YYYY for an unmapped format string', () => {
    setLocalisation({ ...ZA, date_format: 'm-d-Y' });
    expect(formatDate(new Date(2026, 6, 16))).toBe('16/07/2026');
  });

  it('still returns empty string for invalid input', () => {
    setLocalisation(ZA);
    expect(formatDate(null)).toBe('');
    expect(formatDate('not-a-date')).toBe('');
  });
});

describe('formatDateForInput', () => {
  it('stays ISO regardless of localisation', () => {
    setLocalisation(ZA);
    expect(formatDateForInput(new Date(2026, 6, 16))).toBe('2026-07-16');
  });
});

describe('formatDateLong', () => {
  it('uses the session locale', () => {
    setLocalisation(ZA);
    // en-ZA and en-GB both render "16 July 2026" — assert it does not throw
    // on the BCP-47 locale and contains the pieces.
    const result = formatDateLong(new Date(2026, 6, 16));
    expect(result).toContain('2026');
    expect(result).toContain('July');
  });
});

describe('jurisdiction tax year', () => {
  beforeEach(() => setJurisdictionTaxYear(ZA_TAX_YEAR));

  it('getCurrentTaxYear returns the session label when set', () => {
    expect(getCurrentTaxYear()).toBe('2026/27');
  });

  it('boundary maths: 28 Feb is inside the prior year, 1 Mar starts the next', () => {
    expect(getCurrentTaxYear(new Date(2027, 1, 28))).toBe('2026/27');
    expect(getCurrentTaxYear(new Date(2027, 2, 1))).toBe('2027/28');
  });

  it('getTaxYearStart rolls on the 1-March boundary', () => {
    expect(getTaxYearStart(new Date(2026, 6, 16))).toEqual(new Date(2026, 2, 1));
    expect(getTaxYearStart(new Date(2027, 1, 28))).toEqual(new Date(2026, 2, 1));
    expect(getTaxYearStart(new Date(2027, 2, 1))).toEqual(new Date(2027, 2, 1));
  });

  it('getTaxYearEnd is the day before the next boundary (leap-year safe)', () => {
    expect(getTaxYearEnd(new Date(2026, 6, 16))).toEqual(new Date(2027, 1, 28));
    expect(getTaxYearEnd(new Date(2027, 6, 16))).toEqual(new Date(2028, 1, 29));
  });

  it('session tax year wins over the GB admin override; clearing it restores the chain', () => {
    setActiveTaxYear('2025/26');
    expect(getCurrentTaxYear()).toBe('2026/27');

    setJurisdictionTaxYear(null);
    expect(getCurrentTaxYear()).toBe('2025/26');
  });
});

describe('GB tax year regression (no jurisdiction tax year)', () => {
  it('keeps the 6-April boundary and 5-April end', () => {
    expect(getTaxYearStart(new Date(2026, 6, 16))).toEqual(new Date(2026, 3, 6));
    expect(getTaxYearStart(new Date(2026, 3, 5))).toEqual(new Date(2025, 3, 6));
    expect(getTaxYearEnd(new Date(2026, 6, 16))).toEqual(new Date(2027, 3, 5));
    expect(getCurrentTaxYear(new Date(2026, 6, 16))).toBe('2026/27');
  });

  it('honours the GB admin override when no referenceDate is passed', () => {
    setActiveTaxYear('2025/26');
    expect(getCurrentTaxYear()).toBe('2025/26');
    expect(getCurrentTaxYear(new Date(2026, 6, 16))).toBe('2026/27');
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run tests/frontend/utils/dateFormatter.test.js`
Expected: FAIL — `setJurisdictionTaxYear` not exported.

- [ ] **Step 3: Implement**

In `resources/js/utils/dateFormatter.js`:

**3a.** Add the import at the top:

```javascript
import { getLocalisation } from './localisation';

// Deterministic month abbreviations for the 'd M Y' date format —
// avoids Intl short-month variance across ICU versions.
const MONTH_ABBREVIATIONS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
```

**3b.** In `formatDate`, after the validity check (`if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) { return ''; }`), insert before the existing day/month/year extraction:

```javascript
  // WS3: the session date format from the pack Localisation contract.
  // Two-entry map, not a PHP-format interpreter: 'd M Y' (ZA) renders
  // "16 Jul 2026"; anything else falls through to GB DD/MM/YYYY.
  if (getLocalisation()?.dateFormat === 'd M Y') {
    const abbrDay = String(dateObj.getDate()).padStart(2, '0');
    return `${abbrDay} ${MONTH_ABBREVIATIONS[dateObj.getMonth()]} ${dateObj.getFullYear()}`;
  }
```

**3c.** In `formatDateLong`, replace the return line:

```javascript
  return dateObj.toLocaleDateString(getLocalisation()?.locale || 'en-GB', options);
```

**3d.** After the `_activeTaxYearFromBackend` declaration block, add:

```javascript
/**
 * Jurisdiction tax year from the session (WS3). Set by
 * jurisdiction/hydrateFromSession with the /api/auth/user `tax_year`
 * block ({label, starts_on, ends_on}) — non-null only for jurisdictions
 * with a tax_years row (ZA today; GB uses the TaxConfiguration flow via
 * setActiveTaxYear instead, so the two singletons never fight).
 */
let _jurisdictionTaxYear = null;

/**
 * Store the session tax year. Pass null (or a malformed block) to clear.
 */
export function setJurisdictionTaxYear(taxYear) {
  if (!taxYear || !taxYear.label || !taxYear.starts_on) {
    _jurisdictionTaxYear = null;
    return;
  }
  const [, startMonth, startDay] = taxYear.starts_on.split('-').map(Number);
  _jurisdictionTaxYear = {
    label: taxYear.label,
    boundaryMonth: startMonth - 1, // JS Date months are 0-indexed
    boundaryDay: startDay,
  };
}
```

**3e.** Replace `getTaxYearStart` (keep its docblock, note the UK line now says "UK tax year runs from 6 April to 5 April; jurisdictions with a session tax year use their own boundary"):

```javascript
export function getTaxYearStart(referenceDate = new Date()) {
  const boundary = _jurisdictionTaxYear
    ? { month: _jurisdictionTaxYear.boundaryMonth, day: _jurisdictionTaxYear.boundaryDay }
    : { month: 3, day: 6 }; // GB: 6 April

  const year = referenceDate.getFullYear();
  const month = referenceDate.getMonth();
  const day = referenceDate.getDate();

  if (month < boundary.month || (month === boundary.month && day < boundary.day)) {
    return new Date(year - 1, boundary.month, boundary.day);
  }

  return new Date(year, boundary.month, boundary.day);
}
```

**3f.** Replace `getTaxYearEnd`'s body:

```javascript
export function getTaxYearEnd(referenceDate = new Date()) {
  const start = getTaxYearStart(referenceDate);
  const end = new Date(start.getFullYear() + 1, start.getMonth(), start.getDate());
  end.setDate(end.getDate() - 1);
  return end;
}
```

(GB check: start 6 Apr 2026 → 6 Apr 2027 minus a day = 5 Apr 2027, identical to the old hardcoded value. ZA: 1 Mar 2026 → 28 Feb 2027; leap years land on 29 Feb, which is correct for SA.)

**3g.** In `getCurrentTaxYear`, replace the override check at the top of the body:

```javascript
  if (!referenceDate) {
    if (_jurisdictionTaxYear) {
      return _jurisdictionTaxYear.label;
    }
    if (_activeTaxYearFromBackend) {
      return _activeTaxYearFromBackend;
    }
  }
```

(The rest of the function — boundary math from `getTaxYearStart` — is unchanged and now jurisdiction-aware automatically. `getCalendarTaxYear` is left untouched: it exists to compare against the GB admin-selected year and only runs in GB flows where the jurisdiction singleton is null.)

**3h.** Add `setJurisdictionTaxYear` to the default export object at the bottom of the file (after `setActiveTaxYear,`):

```javascript
  setJurisdictionTaxYear,
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run tests/frontend/utils/dateFormatter.test.js`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/dateFormatter.js tests/frontend/utils/dateFormatter.test.js
git commit -m "feat(intl): WS3 dates + tax year adapt to session jurisdiction

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: Frontend — jurisdiction store hydrates/resets the singletons

**Files:**
- Modify: `resources/js/store/modules/jurisdiction.js`
- Modify: `resources/js/store/modules/auth.js` (`mobileLogout` only — one dispatch line)
- Test: `tests/frontend/store/jurisdiction.test.js` (extend — exists from WS1)

**Interfaces:**
- Consumes: `setLocalisation`/`resetLocalisation` (Task 2), `setJurisdictionTaxYear` (Task 4); the `localisation` and `tax_year` payload keys (Task 1).
- Produces: `jurisdiction/hydrateFromSession` and `jurisdiction/reset` keep their existing dispatch contracts (already wired in `auth.js` for login, fetchUser, and web logout; `exitPreview` also dispatches reset). `mobileLogout` gains the missing `jurisdiction/reset` dispatch so a mobile logout can't leave the previous user's formatting live (audit finding §11d) — `fetchUser` re-hydrates after biometric login, so the Face ID flow is unaffected.

- [ ] **Step 1: Write the failing tests**

In `tests/frontend/store/jurisdiction.test.js`, add to the imports:

```javascript
import { getLocalisation } from '@/utils/localisation';
import { getCurrentTaxYear, setJurisdictionTaxYear } from '@/utils/dateFormatter';
```

Append inside the top-level `describe('jurisdiction store module', ...)`:

```javascript
  describe('localisation side effects (WS3)', () => {
    it('hydrates the localisation singleton and jurisdiction tax year from the session payload', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['za'],
        primary_jurisdiction: 'za',
        cross_border: false,
        localisation: {
          currency_code: 'ZAR',
          currency_symbol: 'R',
          locale: 'en_ZA',
          date_format: 'd M Y',
        },
        tax_year: { label: '2026/27', starts_on: '2026-03-01', ends_on: '2027-02-28' },
      });

      expect(getLocalisation()).toEqual({
        currencyCode: 'ZAR',
        currencySymbol: 'R',
        locale: 'en-ZA',
        dateFormat: 'd M Y',
      });
      expect(getCurrentTaxYear()).toBe('2026/27');
    });

    it('clears both singletons when the payload has no localisation blocks', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      expect(getLocalisation()).toBeNull();
    });

    it('reset clears both singletons', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['za'],
        primary_jurisdiction: 'za',
        cross_border: false,
        localisation: { currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' },
        tax_year: { label: '2026/27', starts_on: '2026-03-01', ends_on: '2027-02-28' },
      });

      store.dispatch('jurisdiction/reset');

      expect(getLocalisation()).toBeNull();
      expect(getCurrentTaxYear(new Date(2026, 6, 16))).toBe('2026/27'); // GB calendar math again
      expect(getCurrentTaxYear(new Date(2026, 2, 5))).toBe('2025/26'); // 5 Mar < 6 Apr ⇒ prior GB year
    });
  });
```

Also add cleanup so WS1 tests stay isolated — extend the existing top-level `beforeEach` (which currently only does `store = makeStore();`):

```javascript
  beforeEach(() => {
    store = makeStore();
    store.dispatch('jurisdiction/reset');
  });
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run tests/frontend/store/jurisdiction.test.js`
Expected: the three new tests FAIL (`getLocalisation()` stays null after hydration); WS1 tests PASS.

- [ ] **Step 3: Implement**

In `resources/js/store/modules/jurisdiction.js`:

Add imports after the existing navigation imports:

```javascript
import { setLocalisation, resetLocalisation } from '@/utils/localisation';
import { setJurisdictionTaxYear } from '@/utils/dateFormatter';
```

Pass the localisation blocks through the commit payload and mirror them
inside the mutation, atomically with the state write (same pattern as
`taxConfig.js`'s `setActiveTaxYear` mutation — adjudicated 2026-07-16:
mutation placement governs, so a future direct commit can't desync
formatting from jurisdiction state):

```javascript
    // hydrateFromSession action — extend the existing commit payload:
    commit('SET_JURISDICTION_STATE', {
      active: /* unchanged */,
      primary: /* unchanged */,
      crossBorder: /* unchanged */,
      localisation: payload.localisation || null,
      taxYear: payload.tax_year || null,
    });
    // reset action — add localisation: null, taxYear: null to its commit.

    // Mutation:
  SET_JURISDICTION_STATE(state, { active, primary, crossBorder, localisation = null, taxYear = null }) {
    state.activeJurisdictions = active;
    state.primaryJurisdiction = primary;
    state.crossBorder = crossBorder;
    // WS3 — mirror into the formatting singletons atomically with the
    // state write (same pattern as taxConfig.setActiveTaxYear) so any
    // direct commit keeps formatting in sync with jurisdiction state.
    setLocalisation(localisation);
    setJurisdictionTaxYear(taxYear);
  },
```

(`resetLocalisation` import becomes unused — `setLocalisation(null)` clears.)

In `resources/js/store/modules/auth.js`, inside the `mobileLogout` action (it already destructures `{ commit, dispatch }`), add after `commit('clearAuth');`:

```javascript
      // WS3 — clear jurisdiction formatting singletons on mobile logout
      // (mirrors logout/exitPreview); fetchUser re-hydrates after
      // biometric login, so Face ID is unaffected.
      dispatch('jurisdiction/reset', null, { root: true }).catch(() => {});
```

(The reset behaviour itself is covered by this task's jurisdiction tests; the mobileLogout wiring is verified by code review here and the mobile-unaffected check in Task 8.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run tests/frontend/store/jurisdiction.test.js`
Expected: all PASS (WS1 + new).

- [ ] **Step 5: Commit**

```bash
git add resources/js/store/modules/jurisdiction.js resources/js/store/modules/auth.js tests/frontend/store/jurisdiction.test.js
git commit -m "feat(intl): WS3 jurisdiction store hydrates localisation singletons

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: Frontend — gate taxConfig/fetchActive to GB-holding sessions

**Files:**
- Modify: `resources/js/store/modules/taxConfig.js`
- Test: `tests/frontend/store/taxConfig.test.js` (create)

**Interfaces:**
- Consumes: `jurisdiction/activeJurisdictions` rootGetter (exists). Dispatch order guarantee: `auth/fetchUser` dispatches `jurisdiction/hydrateFromSession` **before** `taxConfig/fetchActive` (verified in `auth.js`), so the getter is populated when the guard runs. `App.vue:34` dispatches after `fetchUser` resolves — also safe.
- Produces: `taxConfig/fetchActive` returns `null` without any HTTP call only when the session holds 1+ jurisdictions and none is GB (mirrors `ActiveJurisdictionMiddleware`'s fail-open logic); unchanged behaviour for GB holders (incl. cross-border) and row-less/unset sessions.

- [ ] **Step 1: Write the failing tests**

Create `tests/frontend/store/taxConfig.test.js`:

```javascript
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({
  default: { get: vi.fn() },
}));

import api from '@/services/api';
import taxConfig from '@/store/modules/taxConfig';
import jurisdiction from '@/store/modules/jurisdiction';

function makeStore() {
  return createStore({
    modules: { taxConfig, jurisdiction },
  });
}

describe('taxConfig/fetchActive jurisdiction gating (WS3)', () => {
  let store;

  beforeEach(() => {
    vi.clearAllMocks();
    store = makeStore();
    store.dispatch('jurisdiction/reset');
  });

  it('skips the GB endpoint entirely for a ZA-only session', async () => {
    store.dispatch('jurisdiction/hydrateFromSession', {
      active_jurisdictions: ['za'],
      primary_jurisdiction: 'za',
      cross_border: false,
    });

    const result = await store.dispatch('taxConfig/fetchActive');

    expect(result).toBeNull();
    expect(api.get).not.toHaveBeenCalled();
  });

  it('still calls the GB endpoint for a cross-border GB+ZA session with ZA primary', async () => {
    api.get.mockResolvedValue({
      data: { data: { tax_year: '2026/27', effective_from: '2026-04-06', effective_to: '2027-04-05' } },
    });

    store.dispatch('jurisdiction/hydrateFromSession', {
      active_jurisdictions: ['gb', 'za'],
      primary_jurisdiction: 'za',
      cross_border: true,
    });

    const result = await store.dispatch('taxConfig/fetchActive');

    expect(api.get).toHaveBeenCalledWith('/gb/tax-year/current');
    expect(result).toBe('2026/27');
  });

  it('still calls the GB endpoint for a GB-primary session', async () => {
    api.get.mockResolvedValue({
      data: { data: { tax_year: '2026/27', effective_from: '2026-04-06', effective_to: '2027-04-05' } },
    });

    store.dispatch('jurisdiction/hydrateFromSession', {
      active_jurisdictions: ['gb'],
      primary_jurisdiction: 'gb',
      cross_border: false,
    });

    const result = await store.dispatch('taxConfig/fetchActive');

    expect(api.get).toHaveBeenCalledWith('/gb/tax-year/current');
    expect(result).toBe('2026/27');
  });

  it('still calls the GB endpoint when no jurisdiction is hydrated (fail-open)', async () => {
    api.get.mockResolvedValue({
      data: { data: { tax_year: '2026/27', effective_from: '2026-04-06', effective_to: '2027-04-05' } },
    });

    await store.dispatch('taxConfig/fetchActive');

    expect(api.get).toHaveBeenCalledWith('/gb/tax-year/current');
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run tests/frontend/store/taxConfig.test.js`
Expected: first test FAILS (api.get called for the ZA-only session — no guard exists yet); other three PASS.

- [ ] **Step 3: Implement the guard**

In `resources/js/store/modules/taxConfig.js`, change the `fetchActive` signature and add the guard as the first statement:

```javascript
  async fetchActive({ commit, rootGetters }) {
    // WS3 — /gb/tax-year/current is a GB pack route; users holding
    // jurisdictions without GB get 403'd by ActiveJurisdictionMiddleware.
    // Mirror its fail-open logic: skip only when the user holds 1+
    // jurisdictions and none is GB (their tax year arrives via the
    // session payload). Row-less users and GB holders (incl.
    // cross-border) fetch as today.
    const active = rootGetters['jurisdiction/activeJurisdictions'] || [];
    if (active.length > 0 && !active.includes('gb')) {
      return null;
    }

    commit('setLoading', true);
```

(The rest of the action is unchanged.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run tests/frontend/store/taxConfig.test.js`
Expected: 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/store/modules/taxConfig.js tests/frontend/store/taxConfig.test.js
git commit -m "feat(intl): WS3 gate GB tax-year fetch to GB-holding sessions

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Frontend — CurrencyInputField renders the session symbol

**Files:**
- Modify: `resources/js/components/Shared/CurrencyInputField.vue`
- Test: `tests/frontend/components/Shared/CurrencyInputField.test.js` (create directory too)

**Interfaces:**
- Consumes: `getLocalisation()` from Task 2.
- Produces: no prop/emit changes — the `£` prefix span becomes the session `currencySymbol` with `£` fallback.

- [ ] **Step 1: Write the failing test**

Create `tests/frontend/components/Shared/CurrencyInputField.test.js`:

```javascript
// @vitest-environment jsdom
import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import CurrencyInputField from '@/components/Shared/CurrencyInputField.vue';
import { setLocalisation, resetLocalisation } from '@/utils/localisation';

afterEach(() => resetLocalisation());

describe('CurrencyInputField', () => {
  it('shows £ when no session localisation is set', () => {
    const wrapper = mount(CurrencyInputField, { props: { modelValue: 100 } });
    expect(wrapper.find('span').text()).toBe('£');
  });

  it('shows the session currency symbol', () => {
    setLocalisation({ currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' });
    const wrapper = mount(CurrencyInputField, { props: { modelValue: 100 } });
    expect(wrapper.find('span').text()).toBe('R');
  });

  it('still emits numeric update:modelValue on input', async () => {
    const wrapper = mount(CurrencyInputField, { props: { modelValue: 0 } });
    await wrapper.find('input').setValue('250');
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([250]);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run tests/frontend/components/Shared/CurrencyInputField.test.js`
Expected: "shows the session currency symbol" FAILS (renders `£`); the other two PASS.

- [ ] **Step 3: Implement**

In `resources/js/components/Shared/CurrencyInputField.vue`:

Template — replace the symbol span:

```html
      <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">{{ currencySymbol }}</span>
```

Script — add the import above `export default`:

```javascript
import { getLocalisation } from '@/utils/localisation';
```

And add to `computed` (alongside `displayValue`):

```javascript
    currencySymbol() {
      return getLocalisation()?.currencySymbol || '£';
    },
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run tests/frontend/components/Shared/CurrencyInputField.test.js`
Expected: 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Shared/CurrencyInputField.vue tests/frontend/components/Shared/CurrencyInputField.test.js
git commit -m "feat(intl): WS3 shared currency input renders session symbol

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: Full suites, static analysis, and browser verification

**Files:**
- No new files (fixes only if something fails).

**Interfaces:**
- Consumes: everything above.
- Produces: green full suites + browser-verified SA and GB experiences.

- [ ] **Step 1: Full frontend suite**

Run: `npm run test:run`
Expected: all Vitest suites PASS (existing + the 5 new/extended files).

- [ ] **Step 2: Full backend suite + static analysis**

Run: `./vendor/bin/pest`
Expected: full suite green (3089+ passing, 0 failures; the known pre-existing `InvestmentControllerTest` flake passes on re-run if it trips).

Run: `composer analyse`
Expected: no errors.

- [ ] **Step 3: Browser verification (Playwright — interact, don't just look)**

Per CLAUDE.md browser-testing rules — every check below is an actual interaction:

1. Start the dev server (`./dev.sh` if not already running), open `http://localhost:8000`.
2. From the landing page persona selector, enter the **sa_professional** preview persona.
3. Verify on the SA dashboard/module pages: money renders as `R 1 234…` (NBSP-spaced, no `£` in programmatic amounts), the tax year label shows `2026/27` where displayed, and formatted dates render as `16 Jul 2026` style.
4. Open a form containing the shared `CurrencyInputField` and verify the prefix shows `R`.
5. Exit preview; enter a GB persona (**young_family**). Verify: `£` amounts, DD/MM/YYYY dates, 6-April tax year label — unchanged from pre-WS3.
6. Log in as `chris@fynla.org` / `Password1!` (fetch the verification code from the DB per CLAUDE.md), spot-check Dashboard + one module page for unchanged GB output.
7. Anything that cannot be tested must be reported as "I COULD NOT TEST THIS" — never marked verified.

- [ ] **Step 3b: Drive-by — correct the stale ZaPreviewUserSeeder docblock**

`packs/country-za/database/seeders/ZaPreviewUserSeeder.php` lines 22–28 claim "Surfacing an SA persona in the landing-page selector is blocked" — stale: `sa_professional` is fully wired (`PreviewController::VALID_PERSONAS`, frontend `preview.js` `PERSONA_ORDER`) and Step 3 above depends on selecting it. Replace that docblock sentence with a one-liner stating the persona IS selectable from the landing-page persona selector. Comment-only change; include it in Step 4's commit.

- [ ] **Step 4: Fix anything found, re-run the affected suite, then final commit if fixes were made**

```bash
git add -A
git commit -m "fix(intl): WS3 browser-verification fixes

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

(If no fixes were needed, this commit still carries the Step 3b seeder docblock change — reword to `docs(intl): WS3 correct stale ZaPreviewUserSeeder docblock`.)

- [ ] **Step 5: Mark the spec implemented**

Update the `status:` line in `docs/superpowers/specs/2026-07-16-ws3-frontend-localisation-design.md` to `IMPLEMENTED (<date> — suites green; commits <shas>)`, then:

```bash
git add docs/superpowers/specs/2026-07-16-ws3-frontend-localisation-design.md
git commit -m "docs(intl): mark WS3 spec implemented

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```
