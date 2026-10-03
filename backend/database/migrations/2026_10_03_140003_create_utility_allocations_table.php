<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utility_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            // metered|equal_split|area_based|custom|vacant_owner
            $table->string('allocation_type');
            $table->decimal('consumption_share', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'utility_bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_allocations');
    }
};
