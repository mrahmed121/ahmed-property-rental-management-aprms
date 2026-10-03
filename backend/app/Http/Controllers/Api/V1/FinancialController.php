<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Services\FinancialDashboardService;
use App\Domains\Billing\Services\FinancialPeriodService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    public function __construct(
        private FinancialDashboardService $dashboard,
        private FinancialPeriodService $periods,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->metrics()]);
    }

    public function arrears(Request $request): JsonResponse
    {
        $result = $this->dashboard->arrears($request->only(['tenant_id', 'property_id', 'per_page']));

        return response()->json([
            'data' => $result->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'tenant_id' => $i->tenant_id,
                'tenant' => $i->tenant ? $i->tenant->first_name.' '.$i->tenant->last_name : null,
                'unit' => $i->unit?->unit_number,
                'property' => $i->property?->name,
                'due_date' => $i->due_date?->toDateString(),
                'days_overdue' => max(0, (int) $i->due_date->diffInDays(now())),
                'total' => (float) $i->total,
                'paid_amount' => (float) $i->paid_amount,
                'outstanding' => $i->outstanding(),
                'status' => $i->status,
            ]),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function periods(): JsonResponse
    {
        $result = $this->periods->list();

        return response()->json([
            'data' => $result->map(fn ($p) => [
                'id' => $p->id, 'period' => $p->period, 'status' => $p->status,
                'locked_at' => $p->locked_at?->toDateTimeString(),
                'locked_by' => $p->lockedBy?->name,
            ]),
            'meta' => ['current_page' => $result->currentPage(), 'total' => $result->total()],
        ]);
    }

    public function lockPeriod(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => 'required|regex:/^\d{4}-\d{2}$/']);
        $period = $this->periods->lock($data['period']);

        return response()->json(['message' => "Period {$period->period} locked.", 'data' => ['period' => $period->period, 'status' => $period->status]]);
    }

    public function unlockPeriod(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => 'required|regex:/^\d{4}-\d{2}$/']);
        $period = $this->periods->unlock($data['period']);

        return response()->json(['message' => "Period {$period->period} unlocked.", 'data' => ['period' => $period->period, 'status' => $period->status]]);
    }
}
