<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Owning landlord (users with the owner role). Nullable: agency-managed stock.
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->enum('property_type', ['residential', 'commercial', 'mixed-use'])->default('residential');
            $table->string('address');
            $table->string('city', 100);
            $table->string('postal_code', 20)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'city']);
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
