<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Fynla\Packs\Za\Models\ZaProtectionBeneficiary
 */
class ZaProtectionBeneficiaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_id' => $this->policy_id,
            'beneficiary_type' => $this->beneficiary_type,
            'name' => $this->name,
            'relationship' => $this->relationship,
            'allocation_percentage' => (float) $this->allocation_percentage,
            'id_number' => $this->id_number,
            'is_dutiable' => (bool) $this->is_dutiable,
        ];
    }
}
