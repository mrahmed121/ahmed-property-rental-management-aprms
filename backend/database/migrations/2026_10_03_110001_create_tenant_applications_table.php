<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Preferred property / unit (may change before approval).
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'screening', 'approved', 'rejected'])
                ->default('draft');
            // Screening foundation (ScreeningService owns these transitions).
            $table->enum('screening_status', ['not_started', 'in_progress', 'clear', 'flagged'])
                ->default('not_started');
            $table->text('screening_notes')->nullable();
            $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('screened_at')->nullable();
            $table->enum('kyc_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            // Review workflow.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index('tenant_id');
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_applications');
    }
};
