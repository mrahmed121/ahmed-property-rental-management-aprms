<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Leasing\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTenantRequest;
use App\Http\Requests\Api\V1\UpdateTenantRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private TenantService $tenants) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->tenants->list($request->only([
            'search', 'status', 'kyc_status', 'city', 'sort_by', 'sort_dir', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($t) => $this->payload($t)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenants->create($request->validated());

        return response()->json([
            'message' => 'Tenant created.',
            'data' => $this->payload($tenant),
        ], 201);
    }

    public function show(int $tenant): JsonResponse
    {
        return response()->json(['data' => $this->detailedPayload($this->tenants->find($tenant))]);
    }

    public function update(UpdateTenantRequest $request, int $tenant): JsonResponse
    {
        $model = $this->tenants->find($tenant);
        $updated = $this->tenants->update($model, $request->validated());

        return response()->json([
            'message' => 'Tenant updated.',
            'data' => $this->payload($updated),
        ]);
    }

    public function destroy(int $tenant): JsonResponse
    {
        $model = $this->tenants->find($tenant);
        $this->tenants->archive($model);

        return response()->json(['message' => 'Tenant archived.']);
    }

    private function payload($t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->fullName(),
            'first_name' => $t->first_name,
            'last_name' => $t->last_name,
            'email' => $t->email,
            'phone' => $t->phone,
            'city' => $t->city,
            'status' => $t->status,
            'kyc_status' => $t->kyc_status,
            'leases_count' => $t->leases_count ?? null,
            'applications_count' => $t->applications_count ?? null,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    private function detailedPayload($t): array
    {
        $data = $this->payload($t);
        $data['address'] = $t->address;
        // National ID is sensitive: expose masked form only. Raw value is
        // never returned by the API (it is write-only at creation).
        $data['national_id_masked'] = $t->national_id
            ? '****-'.substr(preg_replace('/\D/', '', $t->national_id), -4)
            : null;
        $data['emergency_contact_name'] = $t->emergency_contact_name;
        $data['emergency_contact_phone'] = $t->emergency_contact_phone;
        $data['notes'] = $t->notes;
        $data['user'] = $t->user ? ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email] : null;
        $data['leases'] = $t->leases->map(fn ($l) => [
            'id' => $l->id, 'lease_number' => $l->lease_number, 'status' => $l->status,
            'start_date' => $l->start_date?->toDateString(), 'end_date' => $l->end_date?->toDateString(),
        ])->all();

        return $data;
    }
}
