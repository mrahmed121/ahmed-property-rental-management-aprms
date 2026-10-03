<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\DunningReminder;
use App\Domains\Billing\Services\DunningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DunningController extends Controller
{
    public function __construct(private DunningService $dunning) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->dunning->list($request->only([
            'tenant_id', 'invoice_id', 'stage', 'status', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($d) => [
                'id' => $d->id,
                'tenant' => $d->tenant ? $d->tenant->first_name.' '.$d->tenant->last_name : null,
                'invoice_number' => $d->invoice?->invoice_number,
                'stage' => $d->stage,
                'status' => $d->status,
                'channel' => $d->channel,
                'scheduled_at' => $d->scheduled_at?->toDateTimeString(),
                'sent_at' => $d->sent_at?->toDateTimeString(),
                'message' => $d->message,
            ]),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function process(): JsonResponse
    {
        $generated = $this->dunning->processOverdue();

        return response()->json([
            'message' => count($generated).' reminder(s) scheduled.',
            'data' => array_map(fn ($r) => ['id' => $r->id, 'stage' => $r->stage], $generated),
        ]);
    }

    public function markSent(int $id): JsonResponse
    {
        $reminder = $this->dunning->markSent(DunningReminder::findOrFail($id));

        return response()->json(['message' => 'Reminder marked as sent.', 'data' => ['id' => $reminder->id]]);
    }
}
