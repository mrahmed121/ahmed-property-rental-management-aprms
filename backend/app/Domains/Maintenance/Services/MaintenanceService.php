<?php

namespace App\Domains\Maintenance\Services;

use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Maintenance\Models\MaintenanceTicket;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Services\DomainService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MaintenanceService — ticket lifecycle.
 *
 * Status machine is enforced via MaintenanceTicket::TRANSITIONS.
 * SLA due = reported_at + priority hours. Technicians see only
 * assigned tickets; tenants see only their own.
 */
class MaintenanceService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = MaintenanceTicket::with([
            'property:id,name', 'unit:id,unit_number',
            'tenant:id,first_name,last_name', 'assignedTo:id,name',
        ])->orderByDesc('created_at');

        $this->applyScope($query);

        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('priority', $filters['priority']);
        if (! empty($filters['category'])) $query->where('category', $filters['category']);
        if (! empty($filters['property_id'])) $query->where('property_id', $filters['property_id']);
        if (! empty($filters['unit_id'])) $query->where('unit_id', $filters['unit_id']);
        if (! empty($filters['assigned_to'])) $query->where('assigned_to', $filters['assigned_to']);
        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['breached'])) {
            $query->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['closed', 'cancelled', 'verified']);
        }
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ticket_number', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): MaintenanceTicket
    {
        $ticket = MaintenanceTicket::with([
            'property', 'building', 'unit', 'tenant', 'reportedBy:id,name',
            'assignedTo:id,name', 'quotes.vendor', 'quotes.createdBy:id,name',
            'workLogs.technician:id,name', 'verification.verifiedBy:id,name',
            'documents',
        ])->findOrFail($id);
        $this->ensureTicketAccess($ticket);

        return $ticket;
    }

    public function create(array $data): MaintenanceTicket
    {
        return DB::transaction(function () use ($data) {
            $actor = $this->actor();

            $property = Property::findOrFail($data['property_id']);
            $this->ensureAgencyAccess($property->agency_id);

            // Tenant reporters: the ticket must attach to their own tenancy.
            $tenantId = $data['tenant_id'] ?? null;
            if ($actor && $actor->hasRole('tenant')) {
                $tenantIds = TenantAccess::accessibleTenantIds($actor);
                if ($tenantId && ! in_array((int) $tenantId, $tenantIds ?? [], true)) {
                    abort(422, 'You can only report maintenance for your own tenancy.');
                }
                $tenantId = $tenantIds[0] ?? null;
            }

            $unitId = $data['unit_id'] ?? null;
            if ($unitId) {
                $unit = Unit::findOrFail($unitId);
                if ($unit->agency_id !== $property->agency_id) {
                    abort(422, 'Unit does not belong to this property/agency.');
                }
            }

            if (! in_array($data['priority'] ?? 'normal', MaintenanceTicket::PRIORITIES, true)) {
                abort(422, 'Invalid priority.');
            }
            if (! in_array($data['category'], MaintenanceTicket::CATEGORIES, true)) {
                abort(422, 'Invalid category.');
            }

            $priority = $data['priority'] ?? 'normal';
            $slaHours = MaintenanceTicket::SLA_HOURS[$priority];

            $ticket = MaintenanceTicket::create([
                'agency_id' => $property->agency_id,
                'property_id' => $property->id,
                'building_id' => $data['building_id'] ?? null,
                'unit_id' => $unitId,
                'tenant_id' => $tenantId,
                'reported_by' => $actor?->id,
                'ticket_number' => $this->nextTicketNumber($property->agency_id),
                'category' => $data['category'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => 'open',
                'sla_due_at' => Carbon::now()->addHours($slaHours),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit()->log('maintenance.create', $ticket, [
                'ticket_number' => $ticket->ticket_number,
                'priority' => $priority, 'category' => $ticket->category,
            ]);

            return $ticket;
        });
    }

    /** Move the ticket along the status machine. */
    public function transition(MaintenanceTicket $ticket, string $to, ?string $notes = null): MaintenanceTicket
    {
        $this->ensureTicketAccess($ticket);

        return DB::transaction(function () use ($ticket, $to, $notes) {
            $ticket = MaintenanceTicket::where('id', $ticket->id)->lockForUpdate()->firstOrFail();

            if (! MaintenanceTicket::canTransition($ticket->status, $to)) {
                abort(422, "Cannot move ticket from {$ticket->status} to {$to}.");
            }

            if ($to === 'verified' && ! $ticket->verification) {
                abort(422, 'Ticket must be verified before marking verified.');
            }
            if ($to === 'closed') {
                if ($ticket->status !== 'verified') {
                    abort(422, 'Only verified tickets can be closed.');
                }
                $ticket->closed_at = now();
            }
            if ($to === 'completed') {
                $ticket->completed_at = now();
            }

            $from = $ticket->status;
            $ticket->status = $to;
            if ($notes) {
                $ticket->notes = trim(($ticket->notes ? $ticket->notes."\n" : '').$notes);
            }
            $ticket->save();

            $this->audit()->log('maintenance.transition', $ticket, [
                'from' => $from, 'to' => $to,
            ]);

            return $ticket->fresh();
        });
    }

    /** Assign a ticket to a technician. */
    public function assign(MaintenanceTicket $ticket, int $technicianId): MaintenanceTicket
    {
        $this->ensureTicketAccess($ticket);

        return DB::transaction(function () use ($ticket, $technicianId) {
            $ticket = MaintenanceTicket::where('id', $ticket->id)->lockForUpdate()->firstOrFail();

            $tech = \App\Domains\Shared\Models\User::findOrFail($technicianId);
            if ($tech->agency_id !== $ticket->agency_id) {
                abort(422, 'Technician belongs to a different agency.');
            }
            if (! $tech->hasRole('technician')) {
                abort(422, 'Assignee must have the technician role.');
            }

            if (! in_array($ticket->status, ['open', 'triaged', 'assigned'], true)) {
                abort(422, 'Ticket cannot be assigned in its current status.');
            }

            $ticket->assigned_to = $technicianId;
            if ($ticket->status === 'open') $ticket->status = 'triaged';
            if ($ticket->status === 'triaged') $ticket->status = 'assigned';
            $ticket->save();

            $this->audit()->log('maintenance.assign', $ticket, [
                'assigned_to' => $technicianId,
            ]);

            return $ticket->fresh();
        });
    }

    public function ensureTicketAccess(MaintenanceTicket $ticket): void
    {
        $this->ensureAgencyAccess($ticket->agency_id);

        $actor = $this->actor();
        if (! $actor) return;

        if ($actor->hasRole('technician')) {
            // Technicians see only their assigned tickets.
            if ((int) $ticket->assigned_to !== (int) $actor->id) {
                abort(404);
            }
            return;
        }

        if ($actor->hasRole('tenant')) {
            $tenantIds = TenantAccess::accessibleTenantIds($actor);
            if (! in_array($ticket->tenant_id, $tenantIds ?? [], true)) {
                abort(404);
            }
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds) && ! in_array($ticket->property_id, $propertyIds, true)) {
                abort(404);
            }
        }
    }

    private function applyScope($query): void
    {
        $actor = $this->actor();
        if (! $actor) return;

        if ($actor->hasRole('technician')) {
            $query->where('assigned_to', $actor->id);
            return;
        }

        $tenantIds = TenantAccess::accessibleTenantIds($actor);
        if (is_array($tenantIds)) {
            $query->whereIn('tenant_id', $tenantIds);
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds)) {
                $query->whereIn('property_id', $propertyIds);
            }
        }
    }

    private function nextTicketNumber(int $agencyId): string
    {
        $year = now()->format('Y');
        $last = MaintenanceTicket::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->where('ticket_number', 'like', "MT-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) substr($last->ticket_number, -6)) + 1 : 1;

        return sprintf('MT-%s-%06d', $year, $seq);
    }
}
