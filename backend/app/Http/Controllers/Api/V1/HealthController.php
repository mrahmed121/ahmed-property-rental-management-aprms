<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbLatencyMs = null;
        $dbOk = false;

        try {
            $start = microtime(true);
            DB::select('select 1');
            $dbLatencyMs = round((microtime(true) - $start) * 1000, 2);
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        return response()->json([
            'status' => $dbOk ? 'ok' : 'degraded',
            'service' => 'aprms-api',
            'version' => '1.0.0-p1',
            'database' => [
                'ok' => $dbOk,
                'latency_ms' => $dbLatencyMs,
                'driver' => config('database.default'),
            ],
            'timestamp' => now()->toIso8601String(),
        ], $dbOk ? 200 : 503);
    }
}
