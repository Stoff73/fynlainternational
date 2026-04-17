<?php

declare(strict_types=1);

namespace Fynla\Core\Observers;

use Fynla\Core\Models\Jurisdiction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Activates and deactivates user jurisdictions based on asset location.
 *
 * The observer watches create/update/delete events on any asset-bearing
 * model that has a `user_id` and a `country_code` column. When a user
 * acquires an asset in a country they're not already active in, a new
 * user_jurisdictions row is inserted (auto_detected = true). When the
 * last asset in a country is removed, the row is soft-deactivated
 * (deactivated_at is set but the row isn't deleted — it preserves the
 * jurisdiction's history and keeps `auto_detected` honest).
 *
 * Users never see the word "jurisdiction". The sidebar composes from
 * this state silently (Workstream 0.5). If the user adds a SA property,
 * they see SA modules appear on their sidebar the next time they load
 * the app — no opt-in, no toggle.
 *
 * The observer is strictly additive: it never deletes rows, never flips
 * is_primary, and never touches jurisdictions the user already has.
 */
final class JurisdictionDetectionObserver
{
    /**
     * Invoked after a model is created. If the new row has a country_code
     * that isn't already active for this user, activate it.
     */
    public function created(Model $model): void
    {
        $this->activateIfNeeded($model);
    }

    /**
     * Invoked after a model is updated. Re-evaluates entitlement:
     *   - If the country_code changed, activate the new one (keeps the
     *     old one active until the deleted event for that row fires).
     *   - If the country_code was the only link to a country that now
     *     doesn't appear on any other asset, deactivate it.
     */
    public function updated(Model $model): void
    {
        // Activate the new code if needed
        $this->activateIfNeeded($model);

        // If the column changed, check whether the previous value is now
        // orphaned (no remaining asset references it).
        if ($model->wasChanged('country_code')) {
            $previous = $model->getOriginal('country_code');
            if (is_string($previous) && $previous !== '') {
                $this->deactivateIfOrphaned(
                    userId: (int) $model->user_id,
                    code: $previous,
                    excludeTable: $model->getTable(),
                    excludeId: (int) $model->getKey(),
                );
            }
        }
    }

    /**
     * Invoked after a model is deleted. Deactivates the asset's country
     * code if no other asset still references it.
     */
    public function deleted(Model $model): void
    {
        $code = $model->country_code ?? null;
        if (! is_string($code) || $code === '') {
            return;
        }

        $this->deactivateIfOrphaned(
            userId: (int) $model->user_id,
            code: $code,
            excludeTable: $model->getTable(),
            excludeId: (int) $model->getKey(),
        );
    }

    /**
     * Insert a user_jurisdictions row for the given country if the user
     * doesn't already have an active assignment for it.
     *
     * Short-circuits if the asset's country_code is null, empty, or
     * matches the user's existing primary jurisdiction (the trivial
     * "adds a UK asset while already UK" case).
     */
    private function activateIfNeeded(Model $model): void
    {
        $code = $model->country_code ?? null;
        if (! is_string($code) || $code === '') {
            return;
        }

        $userId = (int) ($model->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $jurisdiction = Jurisdiction::byCode($code);
        if ($jurisdiction === null) {
            // The asset references an unsupported country. Store the code
            // for analytics but do not activate a jurisdiction we can't serve.
            return;
        }

        // Short-circuit if already active for this user.
        $existing = DB::table('user_jurisdictions')
            ->where('user_id', $userId)
            ->where('jurisdiction_id', $jurisdiction->id)
            ->whereNull('deactivated_at')
            ->exists();
        if ($existing) {
            return;
        }

        // If there's a soft-deactivated row, reactivate it rather than
        // creating a duplicate (the unique constraint forbids duplicates).
        $soft = DB::table('user_jurisdictions')
            ->where('user_id', $userId)
            ->where('jurisdiction_id', $jurisdiction->id)
            ->whereNotNull('deactivated_at')
            ->first();
        if ($soft !== null) {
            DB::table('user_jurisdictions')
                ->where('id', $soft->id)
                ->update([
                    'deactivated_at' => null,
                    'activated_at' => now(),
                    'auto_detected' => true,
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('user_jurisdictions')->insert([
            'user_id' => $userId,
            'jurisdiction_id' => $jurisdiction->id,
            'is_primary' => false,
            'activated_at' => now(),
            'deactivated_at' => null,
            'auto_detected' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Soft-deactivate the user's assignment to the given country if no
     * remaining asset row references it.
     *
     * Exclusions: the (table, id) tuple being deleted or updated out of
     * this country — we can't query the live row set and simultaneously
     * exclude ourselves without it.
     *
     * Never deactivates the user's primary jurisdiction. If a user
     * removes their last UK asset they still see UK modules; primary
     * status is sticky and only changes through explicit account-level
     * action (not covered in this observer).
     */
    private function deactivateIfOrphaned(
        int $userId,
        string $code,
        string $excludeTable,
        int $excludeId,
    ): void {
        $jurisdiction = Jurisdiction::byCode($code);
        if ($jurisdiction === null) {
            return;
        }

        $assignment = DB::table('user_jurisdictions')
            ->where('user_id', $userId)
            ->where('jurisdiction_id', $jurisdiction->id)
            ->whereNull('deactivated_at')
            ->first();
        if ($assignment === null || (bool) $assignment->is_primary) {
            return;
        }

        if ($this->hasOtherAssetInCountry($userId, $code, $excludeTable, $excludeId)) {
            return;
        }

        DB::table('user_jurisdictions')
            ->where('id', $assignment->id)
            ->update([
                'deactivated_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Check whether the user has any remaining asset (across all six
     * tables) in the given country, excluding the specific row identified
     * by (excludeTable, excludeId).
     */
    private function hasOtherAssetInCountry(
        int $userId,
        string $code,
        string $excludeTable,
        int $excludeId,
    ): bool {
        foreach (self::ASSET_TABLES as $table) {
            $query = DB::table($table)
                ->where('user_id', $userId)
                ->where('country_code', $code);

            if ($table === $excludeTable) {
                $query->where('id', '!=', $excludeId);
            }

            // Respect soft-deletes where the column exists. SchemaGrammar
            // doesn't expose this cleanly at runtime, so guard via the
            // known set of tables with `deleted_at`.
            if (in_array($table, self::SOFT_DELETE_TABLES, true)) {
                $query->whereNull('deleted_at');
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Asset tables the observer considers when deciding orphaning.
     * Kept in sync with the migration that adds country_code.
     */
    private const ASSET_TABLES = [
        'investment_accounts',
        'dc_pensions',
        'db_pensions',
        'savings_accounts',
        'properties',
        'assets',
    ];

    /**
     * Subset of ASSET_TABLES that have a `deleted_at` soft-delete column.
     * All six have `deleted_at` per current schema (verified 17 April 2026
     * via Schema::getColumnListing on each table).
     */
    private const SOFT_DELETE_TABLES = [
        'investment_accounts',
        'dc_pensions',
        'db_pensions',
        'savings_accounts',
        'properties',
        'assets',
    ];
}
