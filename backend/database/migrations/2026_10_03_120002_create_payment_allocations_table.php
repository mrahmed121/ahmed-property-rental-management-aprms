<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            // Polymorphic charge: rent invoice or late fee.
            $table->string('allocatable_type');
            $table->unsignedBigInteger('allocatable_id');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'allocatable_type', 'allocatable_id'], 'alloc_payment_charge_unique');
            $table->index(['allocatable_type', 'allocatable_id']);
            $table->index(['agency_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
