<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('maintenance_tickets')->cascadeOnDelete();
            $table->foreignId('verified_by')->constrained('users');
            $table->timestamp('verified_at');
            $table->text('notes')->nullable();
            // passed|failed
            $table->string('result')->default('passed');
            $table->timestamps();

            $table->unique(['agency_id', 'ticket_id'], 'verifications_ticket_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_verifications');
    }
};
