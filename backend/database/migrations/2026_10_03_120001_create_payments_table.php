<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number');
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('method'); // cash|bank_transfer|online|card|other
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 3)->default('PKR');
            $table->foreignId('posted_by')->constrained('users');
            $table->string('status')->default('posted'); // posted|reversed
            $table->string('idempotency_key');
            $table->timestamps();

            $table->unique(['agency_id', 'receipt_number']);
            $table->unique(['agency_id', 'idempotency_key']);
            $table->index(['agency_id', 'tenant_id', 'payment_date']);
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
