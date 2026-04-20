<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the engine's 4-category coverage-gap payload for the API.
 * Wrapped around the full array returned by calculateAggregateCoverageGap().
 * Uses the standard JsonResource constructor (resource = mixed) — no
 * custom constructor signature, consistent with WS 1.4d resources.
 */
class ZaCoverageGapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, array{recommended_cover: int, minimum_cover: int, existing_cover: int, shortfall: int, rationale: string, missing_inputs: array<int,string>}> $payload */
        $payload = $this->resource;

        return collect($payload)
            ->map(fn (array $cat, string $key) => [
                'category' => $key,
                'recommended_cover_minor' => $cat['recommended_cover'],
                'recommended_cover_major' => round($cat['recommended_cover'] / 100, 2),
                'minimum_cover_minor' => $cat['minimum_cover'],
                'minimum_cover_major' => round($cat['minimum_cover'] / 100, 2),
                'existing_cover_minor' => $cat['existing_cover'],
                'existing_cover_major' => round($cat['existing_cover'] / 100, 2),
                'shortfall_minor' => $cat['shortfall'],
                'shortfall_major' => round($cat['shortfall'] / 100, 2),
                'rationale' => $cat['rationale'],
                'missing_inputs' => $cat['missing_inputs'],
            ])
            ->values()
            ->all();
    }
}
