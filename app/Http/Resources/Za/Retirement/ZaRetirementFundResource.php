<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaRetirementFundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bucket = ZaRetirementFundBucket::query()
            ->where('user_id', $this->user_id)
            ->where('fund_holding_id', $this->id)
            ->first();

        $labelMap = [
            'retirement_annuity' => 'Retirement Annuity',
            'pension_fund' => 'Pension Fund',
            'provident_fund' => 'Provident Fund',
            'preservation_fund' => 'Preservation Fund',
        ];

        return [
            'id' => (int) $this->id,
            'fund_type' => (string) $this->pension_type,
            'fund_type_label' => $labelMap[$this->pension_type] ?? (string) $this->pension_type,
            'provider' => (string) $this->provider,
            'scheme_name' => $this->scheme_name,
            // Note: DCPension::$hidden includes 'member_number' for toArray(),
            // but JsonResource accesses it via __get which reads raw attributes.
            'member_number' => $this->member_number,
            'country' => 'South Africa',
            'country_code' => 'ZA',
            'buckets' => $bucket ? new ZaRetirementBucketResource($bucket) : null,
            'total_balance_minor' => $bucket
                ? (int) $bucket->vested_balance_minor
                    + (int) $bucket->provident_vested_pre2021_balance_minor
                    + (int) $bucket->savings_balance_minor
                    + (int) $bucket->retirement_balance_minor
                : 0,
            'created_at_iso' => $this->created_at?->toIso8601String(),
        ];
    }
}
