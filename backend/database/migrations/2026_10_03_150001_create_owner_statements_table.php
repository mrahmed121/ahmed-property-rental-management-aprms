<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_period_id')->constrained()->cascadeOnDelete();
            // The owner (User with owner role) this statement belongs to.
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('statement_number');
            $table->string('currency', 3)->default('PKR');
            // draft|review|approved|finalized
            $table->string('status')->default('draft');
            $table->decimal('gross_income', 14, 2)->default(0);
            $table->decimal('management_fee_percent', 5, 2)->default(0);
            $table->decimal('management_fee', 14, 2)->default(0);
            $table->decimal('owner_expenses', 14, 2)->default(0);
            $table->decimal('owner_maintenance', 14, 2)->default(0);
            $table->decimal('owner_utility_absorption', 14, 2)->default(0);
            $table->decimal('adjustments_total', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'statement_number']);
            $table->unique(['agency_id', 'owner_id', 'statement_period_id'], 'stmts_owner_period_unique');
            $table->index(['agency_id', 'owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_statements');
    }
};
