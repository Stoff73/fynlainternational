<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Fynla\Packs\Gb\Tax\IncomeDefinitionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeDefinitionsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly IncomeDefinitionsService $service
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            $data = $this->service->calculate($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating income definitions');
        }
    }
}
