<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Shared\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $query = AuditLog::with('actor:id,name,email')
            ->orderByDesc('id');

        if (! $actor->isSuperAdmin()) {
            $query->where('agency_id', $actor->agency_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        $logs = $query->paginate(25);

        return response()->json([
            'data' => $logs->map(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'actor' => $l->actor ? ['id' => $l->actor->id, 'name' => $l->actor->name] : null,
                'entity_type' => $l->entity_type ? class_basename($l->entity_type) : null,
                'entity_id' => $l->entity_id,
                'context' => $l->context,
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
