<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Leasing\Services\LeaseRenewalService;
use App\Domains\Leasing\Services\LeaseService;
use App\Domains\Leasing\Services\LeaseTerminationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RenewLeaseRequest;
use App\Http\Requests\Api\V1\StoreLeaseRequest;
use App\Http\Requests\Api\V1\TerminateLeaseRequest;
use App\Http\Requests\Api\V1\UpdateLeaseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaseController extends Controller
{
    public function __construct(
        private LeaseService $leases,
        private LeaseRenewalService $renewals,
        private LeaseTerminationService $terminations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->leases->list($request->only([
            'tenant_id', 'unit_id', 'property_id', 'status', 'expiring_within_days', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($l) => $this->payload($l)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StoreLeaseRequest $request): JsonResponse
    {
        $lease = $this->leases->create($request->validated());

        return response()->json([
            'message' => 'Lease drafted. Activate it to set the unit occupied.',
            'data' => $this->payload($lease),
        ], 201);
    }

    public function show(int $lease): JsonResponse
    {
        $model = $this->leases->find($lease);

        return response()->json(['data' => [
            ...$this->payload($model),
            'terms' => $model->terms,
            'notes' => $model->notes,
            'application_id' => $model->application_id,
            'previous_lease' => $model->previousLease ? ['id' => $model->previousLease->id, 'lease_number' => $model->previousLease->lease_number, 'status' => $model->previousLease->status] : null,
            'successor_lease' => $model->successorLease ? ['id' => $model->successorLease->id, 'lease_number' => $model->successorLease->lease_number, 'status' => $model->successorLease->status] : null,
            'activated_at' => $model->activated_at?->toIso8601String(),
            'terminated_at' => $model->terminated_at?->toIso8601String(),
            'termination_reason' => $model->termination_reason,
            'created_by' => $model->createdBy ? ['id' => $model->createdBy->id, 'name' => $model->createdBy->name] : null,
            'inspection' => $model->inspection ? ['id' => $model->inspection->id, 'condition' => $model->inspection->condition, 'review_status' => $model->inspection->review_status] : null,
        ]]);
    }

    public function update(UpdateLeaseRequest $request, int $lease): JsonResponse
    {
        $model = $this->leases->find($lease);
        $updated = $this->leases->updateDraft($model, $request->validated());

        return response()->json([
            'message' => 'Draft lease updated.',
            'data' => $this->payload($updated),
        ]);
    }

    public function activate(int $lease): JsonResponse
    {
        $model = $this->leases->find($lease);
        $activated = $this->leases->activate($model);

        return response()->json([
            'message' => 'Lease activated. Unit is now occupied.',
            'data' => $this->payload($activated),
        ]);
    }

    public function renew(RenewLeaseRequest $request, int $lease): JsonResponse
    {
        $model = $this->leases->find($lease);
        $successor = $this->renewals->renew($model, $request->validated());

        return response()->json([
            'message' => 'Renewal drafted. Activate it to supersede the current lease.',
            'data' => $this->payload($successor),
        ], 201);
    }

    public function terminate(TerminateLeaseRequest $request, int $lease): JsonResponse
    {
        $model = $this->leases->find($lease);
        $data = $request->validated();
        $terminated = $this->terminations->terminate($model, $data['termination_date'], $data['reason']);

        return response()->json([
            'message' => 'Lease terminated. History preserved.',
            'data' => $this->payload($terminated),
        ]);
    }

    private function payload($l): array
    {
        return [
            'id' => $l->id,
            'lease_number' => $l->lease_number,
            'tenant' => $l->tenant ? ['id' => $l->tenant->id, 'name' => $l->tenant->fullName()] : ['id' => $l->tenant_id],
            'unit' => $l->unit ? ['id' => $l->unit->id, 'unit_number' => $l->unit->unit_number] : ['id' => $l->unit_id],
            'property' => $l->property ? ['id' => $l->property->id, 'name' => $l->property->name] : ['id' => $l->property_id],
            'start_date' => $l->start_date?->toDateString(),
            'end_date' => $l->end_date?->toDateString(),
            'monthly_rent' => $l->monthly_rent,
            'deposit_amount' => $l->deposit_amount,
            'status' => $l->status,
            'created_at' => $l->created_at?->toIso8601String(),
        ];
    }
}
