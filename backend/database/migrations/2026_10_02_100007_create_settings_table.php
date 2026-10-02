<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('group', 60)->default('general');
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|integer|boolean|json
            $table->timestamps();

            $table->unique(['agency_id', 'key']);
            $table->index(['agency_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
