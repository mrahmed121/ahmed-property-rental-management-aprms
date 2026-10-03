<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Leasing\Services\ApplicationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreApplicationRequest;
use App\Http\Requests\Api\V1\TransitionApplicationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $applications) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->applications->list($request->only([
            'tenant_id', 'property_id', 'status', 'search', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($a) => $this->payload($a)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $application = $this->applications->create($request->validated());

        return response()->json([
            'message' => 'Application created as draft.',
            'data' => $this->payload($application),
        ], 201);
    }

    public function show(int $application): JsonResponse
    {
        $model = $this->applications->find($application);

        return response()->json(['data' => [
            ...$this->payload($model),
            'screening_notes' => $model->screening_notes,
            'screened_at' => $model->screened_at?->toIso8601String(),
            'screened_by' => $model->screenedBy ? ['id' => $model->screenedBy->id, 'name' => $model->screenedBy->name] : null,
            'kyc_status' => $model->kyc_status,
            'notes' => $model->notes,
            'decision_notes' => $model->decision_notes,
            'reviewed_at' => $model->reviewed_at?->toIso8601String(),
            'reviewed_by' => $model->reviewedBy ? ['id' => $model->reviewedBy->id, 'name' => $model->reviewedBy->name] : null,
            'allowed_transitions' => \App\Domains\Leasing\Models\TenantApplication::TRANSITIONS[$model->status] ?? [],
        ]]);
    }

    /** Move the application through its workflow (submit/review/approve/reject). */
    public function transition(TransitionApplicationRequest $request, int $application): JsonResponse
    {
        $model = $this->applications->find($application);
        $updated = $this->applications->transition($model, $request->validated('status'), $request->validated());

        return response()->json([
            'message' => "Application {$updated->status}.",
            'data' => $this->payload($updated),
        ]);
    }

    private function payload($a): array
    {
        return [
            'id' => $a->id,
            'tenant' => $a->tenant ? ['id' => $a->tenant->id, 'name' => $a->tenant->fullName()] : ['id' => $a->tenant_id],
            'property' => $a->property ? ['id' => $a->property->id, 'name' => $a->property->name] : ['id' => $a->property_id],
            'unit' => $a->unit ? ['id' => $a->unit->id, 'unit_number' => $a->unit->unit_number] : null,
            'status' => $a->status,
            'screening_status' => $a->screening_status,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
