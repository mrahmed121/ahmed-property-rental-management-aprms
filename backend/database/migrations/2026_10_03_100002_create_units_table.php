<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            // Denormalized for direct property scoping (always matches building->property_id).
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('unit_number', 40);
            $table->integer('floor')->nullable();
            $table->enum('unit_type', ['apartment', 'office', 'shop', 'room', 'studio', 'warehouse', 'other'])
                ->default('apartment');
            $table->decimal('area_sqft', 10, 2)->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->enum('status', ['vacant', 'occupied', 'reserved', 'maintenance', 'inactive'])
                ->default('vacant');
            // Asking rent (P2 field only — no lease/billing logic until P3/P4).
            $table->decimal('market_rent', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unit identifiers are unique within their building.
            $table->unique(['building_id', 'unit_number']);
            $table->index(['agency_id', 'status']);
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
