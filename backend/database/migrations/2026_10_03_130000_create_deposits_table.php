<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('deposit_amount', 12, 2);   // required/agreed amount
            $table->decimal('held_amount', 12, 2);      // currently held
            $table->string('status')->default('required'); // required|held|partially_released|settled|closed
            $table->date('received_date')->nullable();
            $table->date('release_date')->nullable();
            $table->string('currency', 3)->default('PKR');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency_id', 'lease_id'], 'deposits_lease_unique');
            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
