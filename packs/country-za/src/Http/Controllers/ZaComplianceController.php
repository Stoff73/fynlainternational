<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Controllers;

use Fynla\Core\Http\Controller;
use Fynla\Packs\Za\Support\ZaComplianceContent;
use Illuminate\Http\JsonResponse;

/**
 * Serves the SA regulatory compliance disclosures (FAIS + POPIA) for the SA
 * frontend's disclosure surfaces. Thin proxy over ZaComplianceContent.
 */
class ZaComplianceController extends Controller
{
    public function __construct(
        private readonly ZaComplianceContent $content,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->content->all(),
        ]);
    }
}
