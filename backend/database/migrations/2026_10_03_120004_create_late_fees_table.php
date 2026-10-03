<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('rent_invoices')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('accrued'); // accrued|waived|paid
            $table->date('accrued_date');
            $table->json('rule_snapshot'); // the settings used at accrual time
            $table->timestamps();

            $table->unique(['agency_id', 'invoice_id'], 'latefees_invoice_unique');
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fees');
    }
};
