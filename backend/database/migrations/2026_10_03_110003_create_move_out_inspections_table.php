<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('move_out_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // One inspection per lease (foundation — P4+ owns deposit settlement).
            $table->foreignId('lease_id')->unique()->constrained('leases')->cascadeOnDelete();
            $table->date('inspection_date');
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor', 'damaged'])
                ->default('good');
            $table->text('notes')->nullable();
            $table->text('damage_observations')->nullable();
            $table->enum('review_status', ['pending', 'reviewed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('move_out_inspections');
    }
};
