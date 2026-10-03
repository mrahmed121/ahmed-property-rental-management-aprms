<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referenced by deposit_deductions; created first for FK ordering.
        Schema::create('deposit_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_id')->constrained('move_out_inspections')->cascadeOnDelete();
            $table->decimal('gross_deposit', 12, 2);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('applied_to_balance', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->default(0);
            // draft|finalized
            $table->string('status')->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'deposit_id'], 'settlements_deposit_unique');
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_settlements');
    }
};
