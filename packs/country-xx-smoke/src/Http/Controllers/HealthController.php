<?php

declare(strict_types=1);

namespace Fynla\Packs\XXSmoke\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'pack' => 'xx-smoke',
            'version' => '0.0.1',
            'purpose' => 'ci-smoke-test',
        ]);
    }
}
