<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('rent_invoices')->cascadeOnDelete();
            $table->string('stage'); // day_3|day_7|day_15|day_30
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('channel')->default('system'); // no external provider yet
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'invoice_id', 'stage'], 'dunning_invoice_stage_unique');
            $table->index(['agency_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_reminders');
    }
};
