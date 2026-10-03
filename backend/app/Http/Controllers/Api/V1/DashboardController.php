<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Property\Services\PropertyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private PropertyService $properties) {}

    /**
     * Real, query-backed dashboard numbers.
     * Agency-scoped via AgencyScope; portfolio-scoped for owners/tenants.
     * Never fabricated — zeros are honest zeros.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => $this->properties->stats(),
        ]);
    }
}
