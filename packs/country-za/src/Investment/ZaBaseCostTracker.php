<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Investment;

use Fynla\Packs\Za\Models\ZaHoldingLot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Weighted-average base-cost lot tracker for SA discretionary CGT.
 *
 * Records purchases as separate lots. Disposals draw down weighted-
 * average cost basis proportionally across all open lots for the holding,
 * then write the new open-cost-basis total back to the main-app
 * `holdings.cost_basis` column so the holding row stays in sync with the
 * ledger.
 *
 * Specific-identification is a future enhancement; spec § 8.3 permits
 * either method but weighted-average is the default for unit trusts /
 * ETFs which is the v1 scope.
 */
class ZaBaseCostTracker
{
    public function recordPurchase(
        int $userId,
        int $holdingId,
        float $quantity,
        int $costMinor,
        string $acquisitionDate,
        ?string $notes = null,
    ): int {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Purchase quantity must be positive.');
        }
        if ($costMinor < 0) {
            throw new InvalidArgumentException('Acquisition cost cannot be negative.');
        }

        $lot = ZaHoldingLot::create([
            'user_id' => $userId,
            'holding_id' => $holdingId,
            'quantity_acquired' => $quantity,
            'quantity_open' => $quantity,
            'acquisition_cost_minor' => $costMinor,
            'acquisition_cost_ccy' => 'ZAR',
            'acquisition_date' => $acquisitionDate,
            'notes' => $notes,
        ]);

        return (int) $lot->id;
    }

    /**
     * @return array{units_disposed: float, cost_basis_removed_minor: int}
     */
    public function recordDisposal(
        int $userId,
        int $holdingId,
        float $quantity,
        string $disposalDate,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Disposal quantity must be positive.');
        }

        $openQuantity = $this->openQuantity($holdingId);
        if ($quantity > $openQuantity + 1e-6) {
            throw new InvalidArgumentException(
                "Disposal quantity {$quantity} exceeds open quantity {$openQuantity}.",
            );
        }

        $avgCostPerUnit = $this->averageCostPerUnitMinor($holdingId);
        $costBasisRemoved = (int) round($quantity * $avgCostPerUnit);

        // Draw down proportionally from each open lot.
        $remaining = $quantity;
        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->orderBy('acquisition_date')
            ->orderBy('id')
            ->get();

        $totalOpen = (float) $lots->sum('quantity_open');

        foreach ($lots as $lot) {
            if ($remaining <= 1e-9) {
                break;
            }
            $share = $totalOpen > 0 ? $lot->quantity_open / $totalOpen : 0.0;
            $drawdown = min($remaining, $quantity * $share, $lot->quantity_open);

            $lot->quantity_open = max(0.0, $lot->quantity_open - $drawdown);
            if ($lot->quantity_open <= 1e-9) {
                $lot->disposed_at = $disposalDate;
            }
            $lot->save();

            $remaining -= $drawdown;
        }

        if ($remaining > 1e-9) {
            $earliest = ZaHoldingLot::query()
                ->where('holding_id', $holdingId)
                ->where('quantity_open', '>', 0)
                ->orderBy('acquisition_date')
                ->orderBy('id')
                ->first();
            if ($earliest !== null) {
                $earliest->quantity_open = max(0.0, $earliest->quantity_open - $remaining);
                if ($earliest->quantity_open <= 1e-9) {
                    $earliest->disposed_at = $disposalDate;
                }
                $earliest->save();
            }
        }

        // Write back the new open-cost basis to the main-app holding row
        // so the ledger and the holdings row never drift. `holdings.cost_basis`
        // is decimal(15,2) in the UK-era schema (stored as pounds/rand, not
        // minor units) — divide cents by 100.
        $openCostMinor = $this->openCostBasisMinor($holdingId);
        DB::table('holdings')
            ->where('id', $holdingId)
            ->update(['cost_basis' => round($openCostMinor / 100, 2)]);

        return [
            'units_disposed' => $quantity,
            'cost_basis_removed_minor' => $costBasisRemoved,
        ];
    }

    /**
     * @return array<int, array{id: int, quantity_open: float, acquisition_cost_minor: int, acquisition_date: string}>
     */
    public function openLots(int $holdingId): array
    {
        return ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->orderBy('acquisition_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($lot) => [
                'id' => (int) $lot->id,
                'quantity_open' => (float) $lot->quantity_open,
                'acquisition_cost_minor' => (int) $lot->acquisition_cost_minor,
                'acquisition_date' => $lot->acquisition_date?->format('Y-m-d') ?? '',
            ])
            ->all();
    }

    public function openQuantity(int $holdingId): float
    {
        return (float) ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->sum('quantity_open');
    }

    public function averageCostPerUnitMinor(int $holdingId): float
    {
        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->get();

        $totalOpenCost = 0.0;
        $totalOpenUnits = 0.0;

        foreach ($lots as $lot) {
            if ($lot->quantity_acquired <= 0) {
                continue;
            }
            $proportion = $lot->quantity_open / $lot->quantity_acquired;
            $totalOpenCost += $lot->acquisition_cost_minor * $proportion;
            $totalOpenUnits += $lot->quantity_open;
        }

        return $totalOpenUnits > 0 ? $totalOpenCost / $totalOpenUnits : 0.0;
    }

    /**
     * Total open cost basis across all open lots for the holding, in
     * minor currency units. Ledger's authoritative open cost basis.
     */
    public function openCostBasisMinor(int $holdingId): int
    {
        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->get();

        $total = 0.0;
        foreach ($lots as $lot) {
            if ($lot->quantity_acquired <= 0) {
                continue;
            }
            $proportion = $lot->quantity_open / $lot->quantity_acquired;
            $total += $lot->acquisition_cost_minor * $proportion;
        }

        return (int) round($total);
    }
}
