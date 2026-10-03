<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('deposit_settlements')->nullOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained('move_out_inspections')->nullOnDelete();
            // damage|cleaning|unpaid_rent|other
            $table->string('category');
            // wear|damage — structured distinction
            $table->string('assessment')->default('damage');
            $table->text('description');
            $table->decimal('amount', 12, 2);
            // proposed|approved|rejected
            $table->string('status')->default('proposed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'deposit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_deductions');
    }
};
