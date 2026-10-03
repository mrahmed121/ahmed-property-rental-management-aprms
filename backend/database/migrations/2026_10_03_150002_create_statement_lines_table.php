<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_statement_id')->constrained()->cascadeOnDelete();
            // income|management_fee|expense|maintenance|utility|adjustment
            $table->string('line_type');
            // Polymorphic source: RentInvoice, Expense, MaintenanceQuote, UtilityAllocation, etc.
            $table->nullableMorphs('source');
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->date('line_date');
            // Signed amount: positive = owner credit, negative = owner debit.
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'owner_statement_id', 'line_type']);
            $table->index(['owner_statement_id', 'line_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_lines');
    }
};
