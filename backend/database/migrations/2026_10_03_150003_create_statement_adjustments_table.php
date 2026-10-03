<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_statement_id')->constrained()->cascadeOnDelete();
            // Signed amount: positive = owner credit, negative = owner debit.
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'owner_statement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_adjustments');
    }
};
