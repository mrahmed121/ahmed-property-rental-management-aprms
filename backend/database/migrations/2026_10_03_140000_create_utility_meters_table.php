<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('meter_number');
            // electricity|gas|water|other
            $table->string('utility_type');
            $table->string('unit_of_measure')->default('kWh'); // kWh|m3|units
            $table->string('status')->default('active'); // active|inactive
            $table->date('installation_date')->nullable();
            $table->decimal('opening_reading', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'meter_number']);
            $table->index(['agency_id', 'property_id']);
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_meters');
    }
};
