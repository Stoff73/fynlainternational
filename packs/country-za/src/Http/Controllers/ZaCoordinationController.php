<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Controllers;

use Fynla\Core\Http\Controller;
use Fynla\Packs\Za\Coordination\ZaPositionAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP adapter over the ZA cross-module coordination view (SA Coordination v1).
 *
 * Thin proxy over ZaPositionAggregator. Returns the authenticated user's SA
 * position summed into buckets. Mirrors the other ZA controllers.
 */
class ZaCoordinationController extends Controller
{
    public function __construct(
        private readonly ZaPositionAggregator $aggregator,
    ) {}

    /**
     * The user's cross-module SA position (retirement / tax-free / offshore).
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->aggregator->summarise($request->user()->id),
        ]);
    }
}
