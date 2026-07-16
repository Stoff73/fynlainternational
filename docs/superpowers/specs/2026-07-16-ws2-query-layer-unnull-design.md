---
type: spec
date: 2026-07-16
workstream: WS2 — Query-Layer Un-Null (SA data reaches core)
program: International Layer (WS1–WS6)
status: IMPLEMENTED (2026-07-16 — full suite 3089 pass / 0 fail; commits cb84328, 6db3515, 08d98fb)
branch: feat/international-layer
depends_on: WS1 (jurisdiction lifecycle) — DONE
companion_evidence: July/July15Updates/reconciliation-2026-07-15.md, findings.md
---

# WS2 — Query-Layer Un-Null

## Program context

Second of six international-layer workstreams (WS1 done). End-to-end goal: an SA
user sees a coherent, correct, ZAR SA experience. **WS2's slice: the SA user's
real assets reach core Net Worth / Dashboard / Goals instead of £0.**

## Problem (from the reconciliation)

Core aggregates each pack's assets through four typed contracts
(`PackAssetRepository`, `PackEstateRepository`, `PackAssetResolver`,
`PackUserRelationProvider`); `CompositePack*` implementations in core iterate
`PackRegistry` and merge every pack's results. The GB pack implements all four
for real. **The ZA implementations are Null stubs** — every method
`return new Collection()` / `null`. So an SA user's TFSA, retirement funds, and
offshore holdings never reach the core Net Worth aggregation, the Dashboard, or
Goals: their SA home screen reads £0/empty.

## What ZA data exists (traced)

ZA-owned tables, int-minor ZAR money columns:

| Model | Table | Value column(s) | Net-worth asset? |
|---|---|---|---|
| `ZaTfsaContribution` | `za_tfsa_contributions` | `amount_minor` (`amount_ccy`) | Yes (tax-free savings) |
| `ZaRetirementFundBucket` | `za_retirement_fund_buckets` | `vested_balance_minor` + `provident_vested_pre2021_balance_minor` + `savings_balance_minor` + `retirement_balance_minor` (`balance_ccy`) | Yes (retirement) |
| `ZaHoldingLot` | `za_holding_lots` | (offshore/discretionary investment lots) | Yes (investment) |
| `ZaProtectionPolicy` | `za_protection_policies` | `cover_amount_minor` | **No** — cover, not an asset (GB repo also excludes protection) |
| `ZaDonation` | `za_donations` | donation amount | Estate (gifts register), not a live asset |
| `ZaExchangeControlEntry` | `za_exchange_control_ledger` | transfer amounts | No (ledger) |

`AssetSummary` (core VO) fields: `id, type, name, valueMinor, currency, userId,
jointOwnerId, ownershipPercentage`.

## Design

### Component 1 — `ZaPackAssetRepository` (real)

`userAccounts(int $userId): Collection<AssetSummary>` — query the SA net-worth
assets the user owns and map each to `AssetSummary` with `currency = 'ZAR'`:

- `ZaTfsaContribution` where `user_id = $userId` → one summary per TFSA account
  (type `tfsa`). Value = the account's current balance. **Design note:** TFSA is
  modelled as contributions; confirm at build whether there is a parent TFSA
  account model or the balance is the summed/most-recent contribution — map to
  the account's true current value, not a raw contribution row, and add a test
  pinning the expected total.
- `ZaRetirementFundBucket` where `user_id = $userId` → type `retirement`, value =
  sum of the four `*_balance_minor` columns.
- `ZaHoldingLot` (offshore/discretionary) where `user_id = $userId` → type
  `investment`, value = lot market value.
- **Exclude** `ZaProtectionPolicy` (cover, not an asset) — mirrors the GB repo.

`householdAssets(int $householdId): Collection<AssetSummary>` — the same asset
set for every user in the household (join `users.household_id`), mirroring how
`GbPackAssetRepository::householdAssets` scopes.

All money stays int-minor; `currency = 'ZAR'`. `ownershipPercentage` defaults to
100 unless a joint field exists on the ZA model (TFSA/retirement are individual
by SA rule; use 100).

### Component 2 — `ZaPackEstateRepository` (real where SA has the concept, empty otherwise)

`PackEstateRepository` methods: `liabilitiesForUser`, `trustsForUser`,
`ihtProfileForUser`, `estateAssetsForUser`, `giftsForUser`, `lpasForUser`. SA has
estate duty (not IHT), donations (not PET gifts), and no LPA. Map:

- `giftsForUser` → `ZaDonation` rows (SA donations register).
- `estateAssetsForUser` → the user's ZA assets (reuse the asset-repo mapping, or
  the estate-relevant subset).
- `liabilitiesForUser`, `trustsForUser`, `ihtProfileForUser`, `lpasForUser` →
  return empty collections / null **honestly** where SA has no equivalent
  (documented), rather than faking GB shapes. Estate duty is computed by the
  existing `ZaEstateEngine`, not surfaced through the IHT-shaped contract.

### Component 3 — `ZaPackAssetResolver` (real)

`resolveAccount(string $assetType, int $id): ?Model` — resolve ZA account FK
tags (`za.tfsa`, `za.retirement_fund`, `za.holding`) to the ZA model instance, so
core `Goal` typed-arrow relations (if an SA user links a goal to a ZA account)
resolve. Return null for unknown tags.

### Component 4 — `ZaPackUserRelationProvider` (real)

Implement per the contract's methods (mirror `GbPackUserRelationProvider`) — the
SA-side of any user→pack relations core needs. Trace the GB implementation and
provide the ZA equivalent; empty where SA has no equivalent.

### Component 5 — Bindings

`ZaPackServiceProvider` already binds the four `pack.za.*` keys to the Null
classes — repoint them to the real implementations (same binding keys, no core
change). Core's `CompositePack*` already merges them.

## Data flow

```
SA user's za_tfsa_contributions / za_retirement_fund_buckets / za_holding_lots
  → ZaPackAssetRepository::userAccounts (AssetSummary, ZAR)
  → CompositePackAssetRepository (core, iterates PackRegistry) merges GB+ZA
  → core NetWorth / Dashboard / Goals aggregation
  → SA user sees their real SA totals (rendered in ZAR by WS3)
```

## Testing (Pest)

- Seed a ZA user (WS1 `->jurisdiction('ZA')`) with a TFSA contribution, a
  retirement bucket, and a holding lot. Assert `ZaPackAssetRepository::userAccounts`
  returns three `AssetSummary` rows with `currency = 'ZAR'` and the correct
  int-minor values (retirement = sum of four buckets).
- Assert protection policies are NOT in `userAccounts`.
- Assert the core `CompositePackAssetRepository` (or the net-worth aggregation
  that consumes it) includes the SA assets for that user — the end-to-end proof
  that SA money now reaches core. (Verify the exact core consumer at build.)
- `ZaPackEstateRepository::giftsForUser` returns the user's donations;
  IHT/LPA/trust methods return empty (documented).
- `ZaPackAssetResolver::resolveAccount('za.tfsa', $id)` resolves; unknown → null.
- Regression: full Pest suite green; a GB user's aggregation is unchanged (the
  composite must not double-count or leak ZA into GB totals — assert a GB-only
  user still sees only GB assets).

## Acceptance criteria

- [ ] An SA user's TFSA, retirement, and offshore holdings appear in the core
      net-worth aggregation (not £0/empty).
- [ ] Values are correct int-minor ZAR; protection excluded from assets.
- [ ] A GB-only user's aggregation is completely unchanged.
- [ ] The four ZA repos are no longer Null stubs; bindings point at them.
- [ ] Full Pest suite green; Larastan clean.

## Out of scope for WS2

- Rendering the amounts as "R …" in the UI (WS3 localisation — WS2 gets the data
  to core in ZAR int-minor; WS3 formats it).
- Sidebar module hiding / SA-aware dashboard screens (WS4).
- Any new ZA tax/estate math (reuse existing `ZaEstateEngine`/`ZaTaxEngine`).

## Risks

- **The exact ZA asset value model** (TFSA balance vs contributions; holding-lot
  valuation) must be traced from the ZA modules at build so summaries reflect the
  true current value — pinned by tests. This is the main investigation cost.
- **Composite double-counting.** The core composite merges all packs; a bug could
  surface ZA assets for GB users or vice-versa. The GB-only-unchanged regression
  test guards this.
- **Household scoping** across jurisdictions (a mixed-jurisdiction household) is a
  Phase-2/dual-user concern; WS2 scopes by user + same-jurisdiction household as
  the GB repo does, and does not attempt cross-jurisdiction household merging.
