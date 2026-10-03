<?php

namespace Database\Seeders;

use App\Domains\Maintenance\Models\MaintenanceTicket;
use App\Domains\Maintenance\Services\MaintenanceService;
use App\Domains\Maintenance\Services\MaintenanceWorkflowService;
use App\Domains\Property\Models\Property;
use App\Domains\Shared\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic P5 maintenance demo data (local development only).
 */
class MaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        if (MaintenanceTicket::where('ticket_number', 'like', 'MT-%')->exists()) {
            return; // idempotent
        }

        $admin = User::where('email', 'admin@ahmedestates.local')->firstOrFail();
        Auth::login($admin);

        $tickets = app(MaintenanceService::class);
        $workflow = app(MaintenanceWorkflowService::class);

        $property = Property::firstOrFail();
        $tech = User::where('email', 'technician@ahmedestates.local')->first();

        // Vendor.
        $vendor = $workflow->createVendor([
            'name' => 'Demo Fix-It Services',
            'contact_person' => 'Demo Vendor',
            'phone' => '0300-0000000',
            'category' => 'plumbing',
        ]);

        // 1. Urgent ticket, assigned, breached SLA (reported 2 days ago).
        $t1 = $tickets->create([
            'property_id' => $property->id,
            'category' => 'plumbing',
            'description' => 'Demo: burst pipe in kitchen, water leaking onto floor.',
            'priority' => 'urgent',
        ]);
        $t1->update(['created_at' => now()->subDays(2), 'sla_due_at' => now()->subDays(2)->addHours(4)]);
        if ($tech) $tickets->assign($t1->fresh(), $tech->id);

        // 2. Ticket with quote pending approval.
        $t2 = $tickets->create([
            'property_id' => $property->id,
            'category' => 'electrical',
            'description' => 'Demo: bedroom ceiling fan not working, sparking switch.',
            'priority' => 'high',
        ]);
        if ($tech) $tickets->assign($t2->fresh(), $tech->id);
        $workflow->createQuote($t2->fresh(), [
            'vendor_id' => $vendor->id,
            'labor_cost' => 2500,
            'materials_cost' => 4500,
            'attribution' => 'owner',
            'attribution_reason' => 'Demo: normal wear of fixture, owner bears cost.',
        ]);

        // 3. Completed + verified + closed ticket.
        $t3 = $tickets->create([
            'property_id' => $property->id,
            'category' => 'carpentry',
            'description' => 'Demo: wardrobe door hinge broken.',
            'priority' => 'normal',
        ]);
        if ($tech) {
            $tickets->assign($t3->fresh(), $tech->id);
            $q = $workflow->createQuote($t3->fresh(), [
                'provider' => 'In-house technician',
                'labor_cost' => 1500,
                'materials_cost' => 800,
                'attribution' => 'tenant',
                'attribution_reason' => 'Demo: tenant-caused damage, tenant bears cost.',
            ]);
            $workflow->decideQuote($q, 'approved');
            $workflow->logWork($t3->fresh(), [
                'notes' => 'Demo: hinge replaced, door realigned.',
                'parts_materials' => 'Hinge set × 2',
            ]);
            $tickets->transition($t3->fresh(), 'completed');
            $workflow->verify($t3->fresh(), ['result' => 'passed', 'notes' => 'Demo: verified OK.']);
            $tickets->transition($t3->fresh(), 'closed');
        }

        // 4. Open ticket (tenant-reported feel).
        $tickets->create([
            'property_id' => $property->id,
            'category' => 'painting',
            'description' => 'Demo: peeling paint in living room wall near window.',
            'priority' => 'low',
        ]);

        Auth::logout();
    }
}
