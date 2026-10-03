<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\PaymentService;
use App\Domains\Leasing\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->payments->list($request->only([
            'tenant_id', 'lease_id', 'method', 'status', 'from', 'to',
            'min_amount', 'max_amount', 'search', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($p) => $this->payload($p)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->detailedPayload($this->payments->find($id))]);
    }

    /** Preview the waterfall allocation for an amount (no posting). */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        app(\App\Domains\Leasing\Services\TenantService::class)->ensureTenantAccess($tenant);

        return response()->json(['data' => $this->payments->preview($tenant, (float) $data['amount'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'lease_id' => 'nullable|integer|exists:leases,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'method' => 'required|in:cash,bank_transfer,online,card,other',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        $payment = $this->payments->record($tenant, $data);

        return response()->json(
            ['message' => 'Payment recorded and allocated.', 'data' => $this->detailedPayload($payment)],
            201
        );
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $payment = $this->payments->reverse($this->payments->find($id), $data['reason']);

        return response()->json(['message' => 'Payment reversed.', 'data' => $this->payload($payment)]);
    }

    private function payload($p): array
    {
        return [
            'id' => $p->id,
            'receipt_number' => $p->receipt_number,
            'tenant' => $p->tenant ? ['id' => $p->tenant->id, 'name' => $p->tenant->first_name.' '.$p->tenant->last_name] : null,
            'lease' => $p->lease ? ['id' => $p->lease->id, 'lease_number' => $p->lease->lease_number] : null,
            'payment_date' => $p->payment_date?->toDateString(),
            'amount' => (float) $p->amount,
            'method' => $p->method,
            'reference' => $p->reference,
            'status' => $p->status,
            'currency' => $p->currency,
        ];
    }

    private function detailedPayload($p): array
    {
        $data = $this->payload($p);
        $data['notes'] = $p->notes;
        $data['posted_by'] = $p->postedBy?->name;
        $data['allocations'] = $p->allocations->map(function ($a) {
            $t = $a->allocatable;
            return [
                'id' => $a->id,
                'bucket' => $a->bucket,
                'amount' => (float) $a->amount,
                'target' => $t instanceof \App\Domains\Billing\Models\LateFee
                    ? 'Late fee #'.$t->id
                    : 'Invoice '.$t->invoice_number,
            ];
        })->all();
        $data['allocated_total'] = $p->allocatedTotal();
        $data['unallocated'] = $p->unallocated();

        return $data;
    }
}
