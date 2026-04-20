<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Fynla\Packs\Za\Models\ZaProtectionPolicy
 */
class ZaProtectionPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'joint_owner_id' => $this->joint_owner_id,
            'ownership_percentage' => (float) $this->ownership_percentage,
            'product_type' => $this->product_type,
            'provider' => $this->provider,
            'policy_number' => $this->policy_number,
            'cover_amount_minor' => (int) $this->cover_amount_minor,
            'cover_amount_major' => round($this->cover_amount_minor / 100, 2),
            'premium_amount_minor' => (int) $this->premium_amount_minor,
            'premium_amount_major' => round($this->premium_amount_minor / 100, 2),
            'premium_frequency' => $this->premium_frequency,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'severity_tier' => $this->severity_tier,
            'waiting_period_months' => $this->waiting_period_months,
            'benefit_term_months' => $this->benefit_term_months,
            'group_scheme' => (bool) $this->group_scheme,
            'notes' => $this->notes,
            'beneficiaries' => ZaProtectionBeneficiaryResource::collection($this->whenLoaded('beneficiaries')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
