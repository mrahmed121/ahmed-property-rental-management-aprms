<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Services\TenantLedgerService;
use App\Domains\Leasing\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct(private TenantLedgerService $ledger) {}

    public function show(Request $request, int $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        app(\App\Domains\Leasing\Services\TenantService::class)->ensureTenantAccess($tenant);

        $statement = $this->ledger->statement($tenant, $request->only(['from', 'to', 'entry_type']));

        return response()->json(['data' => [
            'tenant_id' => $statement['tenant_id'],
            'opening_balance' => $statement['opening_balance'],
            'entries' => $statement['entries']->map(fn ($e) => [
                'id' => $e->id,
                'entry_type' => $e->entry_type,
                'entry_date' => $e->entry_date?->toDateString(),
                'description' => $e->description,
                'debit' => (float) $e->debit,
                'credit' => (float) $e->credit,
                'balance_after' => (float) $e->balance_after,
                'created_by' => $e->createdBy?->name,
            ])->all(),
            'total_debit' => $statement['total_debit'],
            'total_credit' => $statement['total_credit'],
            'closing_balance' => $statement['closing_balance'],
            'currency' => 'PKR',
        ]]);
    }

    public function balance(int $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        app(\App\Domains\Leasing\Services\TenantService::class)->ensureTenantAccess($tenant);

        return response()->json(['data' => [
            'tenant_id' => $tenant->id,
            'balance' => $this->ledger->balance($tenant),
            'currency' => 'PKR',
        ]]);
    }
}
