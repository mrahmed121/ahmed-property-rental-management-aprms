<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number');
            $table->string('category'); // plumbing|electrical|carpentry|painting|appliance|general...
            $table->text('description');
            $table->string('priority')->default('normal'); // low|normal|high|urgent
            // open|triaged|assigned|quoted|approval_pending|approved|in_progress|completed|verified|closed|cancelled|rejected
            $table->string('status')->default('open');
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'ticket_number']);
            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'priority', 'status']);
            $table->index(['agency_id', 'assigned_to', 'status']);
            $table->index(['agency_id', 'sla_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
