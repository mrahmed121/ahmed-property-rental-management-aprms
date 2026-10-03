<?php

namespace App\Domains\Maintenance\Services;

use App\Domains\Maintenance\Models\MaintenanceQuote;
use App\Domains\Maintenance\Models\MaintenanceTicket;
use App\Domains\Maintenance\Models\MaintenanceVendor;
use App\Domains\Maintenance\Models\MaintenanceVerification;
use App\Domains\Maintenance\Models\MaintenanceWorkLog;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * MaintenanceWorkflowService — quotes, approvals, work logs, verification.
 *
 * - Quotes need approval before work proceeds (no auto-approval).
 * - Cost attribution: owner|tenant with a required reason.
 * - Verification is required before a ticket can be marked verified/closed.
 */
class MaintenanceWorkflowService extends DomainService
{
    public function createQuote(MaintenanceTicket $ticket, array $data): MaintenanceQuote
    {
        app(MaintenanceService::class)->ensureTicketAccess($ticket);

        return DB::transaction(function () use ($ticket, $data) {
            $ticket = MaintenanceTicket::where('id', $ticket->id)->lockForUpdate()->firstOrFail();

            if (! in_array($ticket->status, ['assigned', 'triaged'], true)) {
                abort(422, 'Quotes can only be added to triaged/assigned tickets.');
            }

            $labor = round((float) ($data['labor_cost'] ?? 0), 2);
            $materials = round((float) ($data['materials_cost'] ?? 0), 2);
            $total = round($labor + $materials, 2);
            if ($total <= 0) {
                abort(422, 'Quote total must be positive.');
            }

            if (! in_array($data['attribution'] ?? 'owner', MaintenanceQuote::ATTRIBUTIONS, true)) {
                abort(422, 'Attribution must be owner or tenant.');
            }
            if (trim($data['attribution_reason'] ?? '') === '') {
                abort(422, 'A reason is required for cost attribution.');
            }

            $vendorId = $data['vendor_id'] ?? null;
            if ($vendorId) {
                $vendor = MaintenanceVendor::findOrFail($vendorId);
                if ($vendor->agency_id !== $ticket->agency_id) {
                    abort(422, 'Vendor belongs to a different agency.');
                }
            }

            $quote = MaintenanceQuote::create([
                'agency_id' => $ticket->agency_id,
                'ticket_id' => $ticket->id,
                'vendor_id' => $vendorId,
                'provider' => $data['provider'] ?? null,
                'labor_cost' => $labor,
                'materials_cost' => $materials,
                'total' => $total,
                'notes' => $data['notes'] ?? null,
                'attribution' => $data['attribution'] ?? 'owner',
                'attribution_reason' => $data['attribution_reason'],
                'status' => 'pending',
                'created_by' => $this->actor()?->id,
            ]);

            $ticket->status = 'quoted';
            $ticket->save();

            // Move to approval_pending so the approval step is explicit.
            app(MaintenanceService::class)->transition($ticket->fresh(), 'approval_pending');

            $this->audit()->log('maintenance.quote_create', $quote, [
                'ticket_id' => $ticket->id, 'total' => $total,
            ]);

            return $quote;
        });
    }

    /** Approve or reject a quote. No auto-approval. */
    public function decideQuote(MaintenanceQuote $quote, string $decision): MaintenanceQuote
    {
        app(MaintenanceService::class)->ensureTicketAccess($quote->ticket);

        return DB::transaction(function () use ($quote, $decision) {
            $quote = MaintenanceQuote::where('id', $quote->id)->lockForUpdate()->firstOrFail();

            if ($quote->status !== 'pending') {
                abort(422, 'Only pending quotes can be decided.');
            }
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                abort(422, 'Decision must be approved or rejected.');
            }

            $quote->update([
                'status' => $decision,
                'approved_by' => $this->actor()?->id,
                'decided_at' => now(),
            ]);

            $ticket = $quote->ticket;
            if ($decision === 'approved') {
                app(MaintenanceService::class)->transition($ticket, 'approved');
            } else {
                app(MaintenanceService::class)->transition($ticket, 'rejected');
            }

            $this->audit()->log('maintenance.quote_decide', $quote, [
                'decision' => $decision, 'ticket_id' => $ticket->id,
            ]);

            return $quote->fresh();
        });
    }

    /** Technician work log. */
    public function logWork(MaintenanceTicket $ticket, array $data): MaintenanceWorkLog
    {
        app(MaintenanceService::class)->ensureTicketAccess($ticket);

        $actor = $this->actor();
        if ($actor && $actor->hasRole('technician')
            && (int) $ticket->assigned_to !== (int) $actor->id) {
            abort(403, 'You can only log work on your assigned tickets.');
        }

        if (! in_array($ticket->status, ['approved', 'in_progress'], true)) {
            abort(422, 'Work can only be logged on approved/in-progress tickets.');
        }

        $log = MaintenanceWorkLog::create([
            'agency_id' => $ticket->agency_id,
            'ticket_id' => $ticket->id,
            'technician_id' => $actor?->id,
            'started_at' => $data['started_at'] ?? now(),
            'completed_at' => $data['completed_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'parts_materials' => $data['parts_materials'] ?? null,
            'labor_notes' => $data['labor_notes'] ?? null,
        ]);

        if ($ticket->status === 'approved') {
            app(MaintenanceService::class)->transition($ticket->fresh(), 'in_progress');
        }

        $this->audit()->log('maintenance.work_log', $log, ['ticket_id' => $ticket->id]);

        return $log;
    }

    /** Verify completed work. */
    public function verify(MaintenanceTicket $ticket, array $data): MaintenanceVerification
    {
        app(MaintenanceService::class)->ensureTicketAccess($ticket);

        return DB::transaction(function () use ($ticket, $data) {
            $ticket = MaintenanceTicket::where('id', $ticket->id)->lockForUpdate()->firstOrFail();

            if ($ticket->status !== 'completed') {
                abort(422, 'Only completed tickets can be verified.');
            }

            if (! in_array($data['result'] ?? 'passed', MaintenanceVerification::RESULTS, true)) {
                abort(422, 'Result must be passed or failed.');
            }

            $verification = MaintenanceVerification::create([
                'agency_id' => $ticket->agency_id,
                'ticket_id' => $ticket->id,
                'verified_by' => $this->actor()?->id,
                'verified_at' => now(),
                'notes' => $data['notes'] ?? null,
                'result' => $data['result'] ?? 'passed',
            ]);

            if ($verification->result === 'passed') {
                app(MaintenanceService::class)->transition($ticket->fresh(), 'verified');
            } else {
                // Failed verification sends the ticket back to in-progress.
                app(MaintenanceService::class)->transition($ticket->fresh(), 'in_progress',
                    'Verification failed — rework required.');
            }

            $this->audit()->log('maintenance.verify', $verification, [
                'ticket_id' => $ticket->id, 'result' => $verification->result,
            ]);

            return $verification;
        });
    }

    /** Vendor CRUD (agency-scoped). */
    public function listVendors(array $filters = [])
    {
        $query = MaintenanceVendor::orderBy('name');
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['category'])) $query->where('category', $filters['category']);
        if (! empty($filters['search'])) $query->where('name', 'like', "%{$filters['search']}%");

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function createVendor(array $data): MaintenanceVendor
    {
        $vendor = MaintenanceVendor::create([
            'agency_id' => $this->agencyId() ?? abort(422, 'Agency context required.'),
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'category' => $data['category'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit()->log('maintenance.vendor_create', $vendor, ['name' => $vendor->name]);

        return $vendor;
    }

    public function ensureVendorAccess(MaintenanceVendor $vendor): void
    {
        $this->ensureAgencyAccess($vendor->agency_id);
    }
}
