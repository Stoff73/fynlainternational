<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaAnnuityQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $kind = $r['kind'];

        $annual = (int) ($r['annual_income_minor'] ?? $r['gross_annual_minor'] ?? $r['annual_annuity_minor']);
        $tax = (int) $r['tax_due_minor'];
        $net = (int) ($r['net_annual_minor'] ?? $annual - $tax);

        $base = [
            'kind' => $kind,
            'tax_year' => $r['tax_year'],
            'capital_minor' => $r['capital_minor'] ?? null,
            'annual_income_minor' => $annual,
            'tax_due_minor' => $tax,
            'net_annual_minor' => $net,
            'monthly_income_minor' => intdiv($annual, 12),
            'net_monthly_income_minor' => intdiv($net, 12),
            'marginal_rate' => (float) $r['marginal_rate'],
        ];

        if ($kind === 'living') {
            $base['drawdown_rate_bps'] = (int) $r['drawdown_rate_bps'];
        }

        if ($kind === 'life') {
            $base['section_10c_exempt_minor'] = (int) $r['section_10c_exempt_minor'];
            $base['section_10c_remaining_pool_minor'] = (int) $r['section_10c_remaining_pool_minor'];
            $base['pool_exhausted'] = (bool) $r['pool_exhausted'];
            $base['taxable_minor'] = (int) $r['taxable_minor'];
        }

        return $base;
    }
}
