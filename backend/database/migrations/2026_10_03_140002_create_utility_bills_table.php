<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained('utility_meters')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('previous_reading', 12, 2);
            $table->decimal('current_reading', 12, 2);
            $table->decimal('consumption', 12, 2);
            $table->decimal('rate', 12, 4); // per unit
            $table->decimal('fixed_charge', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('PKR');
            // draft|finalized|reversed
            $table->string('status')->default('draft');
            // metered|equal_split|area_based|custom
            $table->string('allocation_method')->default('metered');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'bill_number']);
            $table->unique(['agency_id', 'meter_id', 'period_start'], 'bills_meter_period_unique');
            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bills');
    }
};
