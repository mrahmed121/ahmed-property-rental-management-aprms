<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Optional link to a login (users with the tenant role) for the tenant portal.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            // Optional national ID — collected only when the agency needs it for KYC.
            $table->string('national_id', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->enum('status', ['prospective', 'active', 'inactive'])->default('prospective');
            $table->enum('kyc_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'kyc_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
