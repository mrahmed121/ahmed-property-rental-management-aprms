<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\DunningReminder;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Shared\Models\Setting;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * DunningService — reminder cadence for overdue invoices.
 *
 * Stages (from settings notifications.reminder_days = [3,7,15,30]):
 *   day_3, day_7, day_15, day_30 after the due date.
 *
 * Idempotent: UNIQUE(agency_id, invoice_id, stage).
 * No external provider is configured: reminders are recorded as
 * system-channel events (pending → sent is a state transition, not
 * a claim that an SMS/email was delivered).
 */
class DunningService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = DunningReminder::with(['tenant:id,first_name,last_name', 'invoice:id,invoice_number,total'])
            ->orderByDesc('scheduled_at');

        $actor = $this->actor();
        if ($actor) {
            $tenantIds = TenantAccess::accessibleTenantIds($actor);
            if (is_array($tenantIds)) {
                $query->whereIn('tenant_id', $tenantIds);
            }
        }

        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['invoice_id'])) $query->where('invoice_id', $filters['invoice_id']);
        if (! empty($filters['stage'])) $query->where('stage', $filters['stage']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Generate due reminders for overdue invoices.
     * Idempotent: existing (invoice, stage) rows are skipped.
     */
    public function processOverdue(?int $agencyId = null): array
    {
        $agencyId ??= $this->agencyId();
        $stages = $this->stages($agencyId);
        $generated = [];

        $invoices = RentInvoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('due_date', '<', now()->toDateString())
            ->whereRaw('total > paid_amount')
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->get();

        foreach ($invoices as $invoice) {
            $daysOverdue = (int) $invoice->due_date->diffInDays(now());

            foreach ($stages as $days => $stage) {
                if ($daysOverdue < $days) continue;

                $exists = DunningReminder::where('agency_id', $invoice->agency_id)
                    ->where('invoice_id', $invoice->id)
                    ->where('stage', $stage)
                    ->exists();

                if ($exists) continue;

                $reminder = DunningReminder::create([
                    'agency_id' => $invoice->agency_id,
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $invoice->id,
                    'stage' => $stage,
                    'status' => 'pending',
                    'scheduled_at' => now(),
                    'channel' => 'system',
                    'message' => "Rent invoice {$invoice->invoice_number} is {$daysOverdue} days overdue. ".
                        "Outstanding: ₨".number_format($invoice->outstanding(), 2).".",
                ]);

                $generated[] = $reminder;

                $this->audit()->log('dunning.schedule', $reminder, [
                    'invoice_id' => $invoice->id, 'stage' => $stage,
                ]);
            }
        }

        return $generated;
    }

    /** Mark a reminder as sent (records the state transition). */
    public function markSent(DunningReminder $reminder): DunningReminder
    {
        $this->ensureAgencyAccess($reminder->agency_id);

        return DB::transaction(function () use ($reminder) {
            $reminder = DunningReminder::where('id', $reminder->id)->lockForUpdate()->firstOrFail();

            if ($reminder->status === 'sent') {
                return $reminder; // idempotent
            }

            $reminder->update(['status' => 'sent', 'sent_at' => now()]);

            $this->audit()->log('dunning.sent', $reminder, [
                'invoice_id' => $reminder->invoice_id, 'stage' => $reminder->stage,
            ]);

            return $reminder;
        });
    }

    /** Stage map: days overdue => stage key, from settings. */
    private function stages(?int $agencyId): array
    {
        $days = [3, 7, 15, 30];
        if ($agencyId) {
            $s = Setting::where('agency_id', $agencyId)->where('key', 'reminder_days')->first();
            if ($s && is_array($s->typedValue())) {
                $days = $s->typedValue();
            }
        }

        $map = [];
        foreach ($days as $d) {
            $map[(int) $d] = 'day_'.$d;
        }

        return $map;
    }
}
