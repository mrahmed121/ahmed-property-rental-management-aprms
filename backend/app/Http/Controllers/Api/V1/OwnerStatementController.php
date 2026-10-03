<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Shared\Models\User;
use App\Domains\Statements\Models\OwnerStatement;
use App\Domains\Statements\Models\StatementPeriod;
use App\Domains\Statements\Services\OwnerReportingService;
use App\Domains\Statements\Services\StatementGenerationService;
use App\Domains\Statements\Services\StatementWorkflowService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerStatementController extends Controller
{
    public function __construct(
        private StatementGenerationService $generation,
        private StatementWorkflowService $workflow,
        private OwnerReportingService $reporting,
    ) {}

    // --- Periods ---

    public function indexPeriods(): JsonResponse
    {
        $periods = StatementPeriod::orderByDesc('start_date')->paginate(15);

        return response()->json([
            'data' => $periods->map(fn ($p) => $this->periodPayload($p)),
            'meta' => ['current_page' => $periods->currentPage(), 'total' => $periods->total()],
        ]);
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $period = StatementPeriod::create([
            'agency_id' => $request->user()->agency_id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(
            ['message' => 'Statement period created.', 'data' => $this->periodPayload($period)],
            201
        );
    }

    public function lockPeriod(int $id): JsonResponse
    {
        $period = StatementPeriod::findOrFail($id);
        $this->workflow->ensureStatementAccess(new OwnerStatement(['agency_id' => $period->agency_id]));

        $period = $this->workflow->lockPeriod($period);

        return response()->json(['message' => 'Period locked.', 'data' => $this->periodPayload($period)]);
    }

    // --- Statements ---

    public function index(Request $request): JsonResponse
    {
        $query = OwnerStatement::with(['owner:id,name', 'period:id,start_date,end_date'])
            ->orderByDesc('created_at');

        $this->applyScope($query, $request->user());

        if ($request->filled('owner_id')) $query->where('owner_id', $request->integer('owner_id'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('period_id')) $query->where('statement_period_id', $request->integer('period_id'));

        $result = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $result->map(fn ($s) => $this->statementPayload($s)),
            'meta' => ['current_page' => $result->currentPage(), 'total' => $result->total()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $statement = OwnerStatement::with([
            'owner:id,name', 'period',
            'lines.property:id,name', 'lines.unit:id,unit_number',
            'adjustments.createdBy:id,name',
            'approvedBy:id,name', 'finalizedBy:id,name',
        ])->findOrFail($id);

        $this->workflow->ensureStatementAccess($statement);

        $data = $this->statementPayload($statement);
        $data['lines'] = $statement->lines->map(fn ($l) => [
            'id' => $l->id,
            'line_type' => $l->line_type,
            'description' => $l->description,
            'line_date' => $l->line_date->toDateString(),
            'amount' => (float) $l->amount,
            'property' => $l->property?->name,
            'unit' => $l->unit?->unit_number,
            'reference' => $l->reference,
            'source' => $l->source_type ? class_basename($l->source_type).'#'.$l->source_id : null,
        ])->all();
        $data['adjustments'] = $statement->adjustments->map(fn ($a) => [
            'id' => $a->id,
            'amount' => (float) $a->amount,
            'reason' => $a->reason,
            'created_by' => $a->createdBy?->name,
            'created_at' => $a->created_at->toDateTimeString(),
        ])->all();

        return response()->json(['data' => $data]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_id' => 'required|integer|exists:users,id',
            'period_id' => 'required|integer|exists:statement_periods,id',
        ]);

        $owner = User::findOrFail($data['owner_id']);
        $period = StatementPeriod::findOrFail($data['period_id']);

        if ((int) $period->agency_id !== (int) $request->user()->agency_id) abort(404);

        $calc = $this->generation->preview($owner, $period);

        return response()->json(['data' => $calc]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_id' => 'required|integer|exists:users,id',
            'period_id' => 'required|integer|exists:statement_periods,id',
        ]);

        $owner = User::findOrFail($data['owner_id']);
        $period = StatementPeriod::findOrFail($data['period_id']);

        if ((int) $period->agency_id !== (int) $request->user()->agency_id) abort(404);
        if (! $owner->hasRole('owner')) abort(422, 'Selected user is not an owner.');

        $statement = $this->generation->generate($owner, $period);

        return response()->json(
            ['message' => 'Statement generated.', 'data' => $this->statementPayload($statement)],
            201
        );
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['to' => 'required|string']);

        $user = $request->user();
        $to = $data['to'];
        if (in_array($to, ['review'], true) && ! $user->hasPermission('statements.review')) abort(403);
        if (in_array($to, ['approved'], true) && ! $user->hasPermission('statements.approve')) abort(403);
        if (in_array($to, ['finalized'], true) && ! $user->hasPermission('statements.finalize')) abort(403);

        $statement = $this->workflow->transition(
            OwnerStatement::findOrFail($id), $to
        );

        return response()->json(['message' => "Statement {$statement->status}.", 'data' => $this->statementPayload($statement)]);
    }

    public function adjust(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $statement = $this->workflow->adjust(
            OwnerStatement::findOrFail($id), (float) $data['amount'], $data['reason']
        );

        return response()->json(['message' => 'Adjustment added.', 'data' => $this->statementPayload($statement)]);
    }

    public function pdf(int $id)
    {
        $statement = OwnerStatement::with([
            'owner:id,name', 'period',
            'lines.property:id,name', 'lines.unit:id,unit_number',
        ])->findOrFail($id);

        $this->workflow->ensureStatementAccess($statement);

        $html = view('statements.owner-statement', [
            'statement' => $statement,
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->download(
            $statement->statement_number.'.pdf'
        );
    }

    // --- Reports ---

    public function portfolio(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);

        return response()->json(['data' => $this->reporting->portfolio($owner)]);
    }

    public function profitability(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);
        $period = $request->filled('period_id')
            ? StatementPeriod::findOrFail($request->integer('period_id'))
            : null;

        return response()->json(['data' => $this->reporting->profitability($owner, $period)]);
    }

    public function trend(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);

        return response()->json(['data' => $this->reporting->trend($owner)]);
    }

    // --- Helpers ---

    private function resolveOwner(Request $request): User
    {
        $user = $request->user();

        if ($user->hasRole('owner')) {
            return $user;
        }

        $ownerId = $request->integer('owner_id');
        if (! $ownerId) abort(422, 'owner_id is required.');

        $owner = User::findOrFail($ownerId);
        if ((int) $owner->agency_id !== (int) $user->agency_id) abort(404);

        return $owner;
    }

    private function applyScope($query, $user): void
    {
        if ($user->hasRole('owner')) {
            $query->where('owner_id', $user->id);
        }
    }

    private function periodPayload($p): array
    {
        return [
            'id' => $p->id,
            'start_date' => $p->start_date->toDateString(),
            'end_date' => $p->end_date->toDateString(),
            'status' => $p->status,
            'locked_at' => $p->locked_at?->toDateTimeString(),
            'statements_count' => $p->statements()->count(),
        ];
    }

    private function statementPayload($s): array
    {
        return [
            'id' => $s->id,
            'statement_number' => $s->statement_number,
            'owner' => $s->owner ? ['id' => $s->owner->id, 'name' => $s->owner->name] : null,
            'period' => $s->period ? [
                'id' => $s->period->id,
                'start_date' => $s->period->start_date->toDateString(),
                'end_date' => $s->period->end_date->toDateString(),
                'status' => $s->period->status,
            ] : null,
            'currency' => $s->currency,
            'status' => $s->status,
            'gross_income' => (float) $s->gross_income,
            'management_fee_percent' => (float) $s->management_fee_percent,
            'management_fee' => (float) $s->management_fee,
            'owner_expenses' => (float) $s->owner_expenses,
            'owner_maintenance' => (float) $s->owner_maintenance,
            'owner_utility_absorption' => (float) $s->owner_utility_absorption,
            'adjustments_total' => (float) $s->adjustments_total,
            'net_amount' => (float) $s->net_amount,
            'approved_by' => $s->approvedBy?->name,
            'approved_at' => $s->approved_at?->toDateTimeString(),
            'finalized_by' => $s->finalizedBy?->name,
            'finalized_at' => $s->finalized_at?->toDateTimeString(),
        ];
    }
}
