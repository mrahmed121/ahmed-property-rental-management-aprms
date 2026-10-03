<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Leasing\Services\ApplicationService;
use App\Domains\Leasing\Services\ScreeningService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ScreeningDecisionRequest;
use Illuminate\Http\JsonResponse;

class ScreeningController extends Controller
{
    public function __construct(
        private ScreeningService $screening,
        private ApplicationService $applications,
    ) {}

    public function start(int $application): JsonResponse
    {
        $model = $this->applications->find($application);
        $updated = $this->screening->start($model);

        return response()->json([
            'message' => 'Screening started.',
            'data' => ['id' => $updated->id, 'status' => $updated->status, 'screening_status' => $updated->screening_status],
        ]);
    }

    public function decide(ScreeningDecisionRequest $request, int $application): JsonResponse
    {
        $model = $this->applications->find($application);
        $data = $request->validated();
        $updated = $this->screening->decide($model, (bool) $data['clear'], $data['notes'] ?? null, $data['kyc_status'] ?? null);

        return response()->json([
            'message' => $updated->screening_status === 'clear' ? 'Screening cleared.' : 'Screening flagged.',
            'data' => ['id' => $updated->id, 'screening_status' => $updated->screening_status, 'kyc_status' => $updated->kyc_status],
        ]);
    }
}
