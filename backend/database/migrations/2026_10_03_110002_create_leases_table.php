<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            // Denormalized for direct scoping (always consistent with the unit).
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('tenant_applications')->nullOnDelete();
            // Renewal chain: the lease this one supersedes (null for first leases).
            $table->foreignId('previous_lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->string('lease_number', 40);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('monthly_rent', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'renewed', 'terminated', 'expired'])
                ->default('draft');
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agency_id', 'lease_number']);
            $table->index(['agency_id', 'status']);
            $table->index(['unit_id', 'status']);
            $table->index('tenant_id');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
