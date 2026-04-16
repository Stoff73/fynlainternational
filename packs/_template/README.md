# Fynla Country Pack Template

This is the canonical scaffold for creating a new Fynla country pack. A country pack implements the 12 core contracts that adapt Fynla's financial planning engine to a specific country's tax system, retirement rules, investment landscape, and regulatory environment.

## Usage

### 1. Copy the template

```bash
cp -r packs/_template packs/za
```

### 2. Find and replace placeholders

Replace all occurrences throughout the new directory:

| Placeholder | Replace with | Example (South Africa) |
|-------------|-------------|----------------------|
| `XX` (uppercase) | Country code uppercase | `ZA` |
| `xx` (lowercase) | Country code lowercase | `za` |
| `XXX` | ISO 4217 currency code | `ZAR` |
| `en-XX` | BCP 47 locale | `en-ZA` |
| `<Country>` | Country name | `South Africa` |

```bash
cd packs/za
find . -type f -exec sed -i '' 's/XX/ZA/g; s/xx/za/g; s/XXX/ZAR/g; s/en-XX/en-ZA/g; s/<Country>/South Africa/g' {} +
```

### 3. Update composer.json

- Set the correct package name: `fynla/pack-country-za`
- Verify the namespace, currency, locale, and table prefix

### 4. Implement the contracts

Each stub in `src/` corresponds to a core contract. All stubs throw `RuntimeException` until implemented.

| Stub | Core Contract | Purpose |
|------|--------------|---------|
| `Tax/TaxEngine.php` | `Fynla\Core\Contracts\TaxEngine` | Income tax, capital gains, tax wrappers |
| `Retirement/RetirementEngine.php` | `Fynla\Core\Contracts\RetirementEngine` | Pension rules, contribution limits, decumulation |
| `Investment/InvestmentEngine.php` | `Fynla\Core\Contracts\InvestmentEngine` | Investment wrappers, allowances, platform rules |
| `Protection/ProtectionEngine.php` | `Fynla\Core\Contracts\ProtectionEngine` | Insurance types, regulatory requirements |
| `Estate/EstateEngine.php` | `Fynla\Core\Contracts\EstateEngine` | Inheritance tax, estate duty, succession rules |
| `ExchangeControl/NoopExchangeControl.php` | `Fynla\Core\Contracts\ExchangeControl` | Foreign exchange controls (no-op if none) |
| `Identity/IdentityValidator.php` | `Fynla\Core\Contracts\IdentityValidator` | National ID format validation |
| `Localisation/Localisation.php` | `Fynla\Core\Contracts\Localisation` | Date formats, number formats, terminology |
| `Banking/BankingValidator.php` | `Fynla\Core\Contracts\BankingValidator` | Bank account/sort code validation |

Additional files:

| File | Purpose |
|------|---------|
| `Http/Controllers/HealthController.php` | Pack health check endpoint |
| `Support/PackManifest.php` | Pack metadata for registry |
| `Providers/CountryPackServiceProvider.php` | Laravel service provider wiring |

### 5. Add migrations and seeders

Place country-specific database migrations in `database/migrations/` and seeders in `database/seeders/`. Prefix all table names with the country code (e.g. `za_tax_brackets`).

### 6. Add frontend components

Register Vue routes and components via `resources/js/index.js`.

### 7. Add translations

Add translation strings to `resources/lang/xx/messages.php`.

### 8. Run the health check

```bash
php artisan serve
curl http://localhost:8000/api/xx/health
```
