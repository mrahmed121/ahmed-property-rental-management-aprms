<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Maintenance\Models\MaintenanceQuote;
use App\Domains\Maintenance\Models\MaintenanceTicket;
use App\Domains\Maintenance\Models\MaintenanceVendor;
use App\Domains\Maintenance\Services\MaintenanceDashboardService;
use App\Domains\Maintenance\Services\MaintenanceService;
use App\Domains\Maintenance\Services\MaintenanceWorkflowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function __construct(
        private MaintenanceService $tickets,
        private MaintenanceWorkflowService $workflow,
        private MaintenanceDashboardService $dashboard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->tickets->list($request->only([
            'status', 'priority', 'category', 'property_id', 'unit_id',
            'assigned_to', 'tenant_id', 'breached', 'search', 'per_page',
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

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->detailedPayload($this->tickets->find($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'category' => 'required|in:plumbing,electrical,carpentry,painting,appliance,hvac,general',
            'description' => 'required|string|min:10|max:2000',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'notes' => 'nullable|string|max:1000',
        ]);

        $ticket = $this->tickets->create($data);

        return response()->json(
            ['message' => 'Ticket created.', 'data' => $this->payload($ticket)],
            201
        );
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        $ticket = $this->tickets->transition(
            $this->tickets->find($id), $data['to'], $data['notes'] ?? null
        );

        return response()->json(['message' => "Ticket moved to {$ticket->status}.", 'data' => $this->payload($ticket)]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'technician_id' => 'required|integer|exists:users,id',
        ]);

        $ticket = $this->tickets->assign($this->tickets->find($id), $data['technician_id']);

        return response()->json(['message' => 'Ticket assigned.', 'data' => $this->payload($ticket)]);
    }

    // --- Quotes ---

    public function createQuote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => 'nullable|integer|exists:maintenance_vendors,id',
            'provider' => 'nullable|string|max:255',
            'labor_cost' => 'nullable|numeric|min:0',
            'materials_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'attribution' => 'nullable|in:owner,tenant',
            'attribution_reason' => 'required|string|min:5|max:1000',
        ]);

        $quote = $this->workflow->createQuote($this->tickets->find($id), $data);

        return response()->json(
            ['message' => 'Quote submitted for approval.', 'data' => $this->quotePayload($quote)],
            201
        );
    }

    public function decideQuote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
        ]);

        $quote = $this->workflow->decideQuote(
            MaintenanceQuote::findOrFail($id), $data['decision']
        );

        return response()->json(['message' => "Quote {$data['decision']}.", 'data' => $this->quotePayload($quote)]);
    }

    // --- Work logs ---

    public function logWork(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date|after_or_equal:started_at',
            'notes' => 'nullable|string|max:2000',
            'parts_materials' => 'nullable|string|max:1000',
            'labor_notes' => 'nullable|string|max:1000',
        ]);

        $log = $this->workflow->logWork($this->tickets->find($id), $data);

        return response()->json([
            'message' => 'Work logged.',
            'data' => [
                'id' => $log->id, 'ticket_id' => $log->ticket_id,
                'technician' => $log->technician?->name,
                'started_at' => $log->started_at?->toDateTimeString(),
                'notes' => $log->notes,
            ],
        ], 201);
    }

    // --- Verification ---

    public function verify(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'result' => 'nullable|in:passed,failed',
            'notes' => 'nullable|string|max:1000',
        ]);

        $verification = $this->workflow->verify($this->tickets->find($id), $data);

        return response()->json([
            'message' => "Verification {$verification->result}.",
            'data' => [
                'id' => $verification->id,
                'result' => $verification->result,
                'verified_by' => $verification->verifiedBy?->name,
            ],
        ]);
    }

    // --- Vendors ---

    public function vendors(Request $request): JsonResponse
    {
        $result = $this->workflow->listVendors($request->only(['status', 'category', 'search', 'per_page']));

        return response()->json([
            'data' => $result->map(fn ($v) => [
                'id' => $v->id, 'name' => $v->name,
                'contact_person' => $v->contact_person, 'phone' => $v->phone,
                'category' => $v->category, 'status' => $v->status,
            ]),
            'meta' => ['current_page' => $result->currentPage(), 'total' => $result->total()],
        ]);
    }

    public function createVendor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'category' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $vendor = $this->workflow->createVendor($data);

        return response()->json(
            ['message' => 'Vendor created.', 'data' => ['id' => $vendor->id, 'name' => $vendor->name]],
            201
        );
    }

    // --- Dashboard ---

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->metrics()]);
    }

    private function payload($t): array
    {
        return [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'property' => $t->property ? ['id' => $t->property->id, 'name' => $t->property->name] : null,
            'unit' => $t->unit ? ['id' => $t->unit->id, 'unit_number' => $t->unit->unit_number] : null,
            'tenant' => $t->tenant ? ['id' => $t->tenant->id, 'name' => $t->tenant->first_name.' '.$t->tenant->last_name] : null,
            'category' => $t->category,
            'priority' => $t->priority,
            'status' => $t->status,
            'assigned_to' => $t->assignedTo ? ['id' => $t->assignedTo->id, 'name' => $t->assignedTo->name] : null,
            'sla_due_at' => $t->sla_due_at?->toDateTimeString(),
            'breached' => $t->isBreached(),
            'created_at' => $t->created_at?->toDateTimeString(),
        ];
    }

    private function detailedPayload($t): array
    {
        $data = $this->payload($t);
        $data['description'] = $t->description;
        $data['notes'] = $t->notes;
        $data['reported_by'] = $t->reportedBy?->name;
        $data['completed_at'] = $t->completed_at?->toDateTimeString();
        $data['closed_at'] = $t->closed_at?->toDateTimeString();
        $data['quotes'] = $t->quotes->map(fn ($q) => $this->quotePayload($q))->all();
        $data['work_logs'] = $t->workLogs->map(fn ($w) => [
            'id' => $w->id,
            'technician' => $w->technician?->name,
            'started_at' => $w->started_at?->toDateTimeString(),
            'completed_at' => $w->completed_at?->toDateTimeString(),
            'notes' => $w->notes,
            'parts_materials' => $w->parts_materials,
            'labor_notes' => $w->labor_notes,
        ])->all();
        $data['verification'] = $t->verification ? [
            'result' => $t->verification->result,
            'verified_by' => $t->verification->verifiedBy?->name,
            'verified_at' => $t->verification->verified_at?->toDateTimeString(),
            'notes' => $t->verification->notes,
        ] : null;
        $data['documents'] = $t->documents->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name,
        ])->all();

        return $data;
    }

    private function quotePayload($q): array
    {
        return [
            'id' => $q->id,
            'ticket_id' => $q->ticket_id,
            'vendor' => $q->vendor ? ['id' => $q->vendor->id, 'name' => $q->vendor->name] : null,
            'provider' => $q->provider,
            'labor_cost' => (float) $q->labor_cost,
            'materials_cost' => (float) $q->materials_cost,
            'total' => (float) $q->total,
            'attribution' => $q->attribution,
            'attribution_reason' => $q->attribution_reason,
            'status' => $q->status,
            'created_by' => $q->createdBy?->name,
            'approved_by' => $q->approvedBy?->name,
            'notes' => $q->notes,
        ];
    }
}
