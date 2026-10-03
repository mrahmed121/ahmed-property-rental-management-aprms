<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('base_rent', 12, 2);
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->decimal('utilities', 12, 2)->default(0);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status')->default('issued'); // issued|partially_paid|paid|overdue|void
            $table->string('currency', 3)->default('PKR');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency_id', 'invoice_number']);
            $table->unique(['agency_id', 'lease_id', 'period_start'], 'invoices_lease_period_unique');
            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'tenant_id', 'status']);
            $table->index(['agency_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_invoices');
    }
};
