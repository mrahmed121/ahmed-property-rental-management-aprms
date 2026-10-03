<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('maintenance_tickets')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('maintenance_vendors')->nullOnDelete();
            $table->string('provider')->nullable(); // technician name or vendor label
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->decimal('materials_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            // owner|tenant — who bears the cost
            $table->string('attribution')->default('owner');
            $table->text('attribution_reason')->nullable();
            // pending|approved|rejected
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'ticket_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_quotes');
    }
};
