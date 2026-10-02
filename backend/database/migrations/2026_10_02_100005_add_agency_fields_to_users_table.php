<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // NULL agency => platform-level user (Super Admin). Everyone else belongs to an agency.
            $table->foreignId('agency_id')->nullable()->after('id')
                ->constrained('agencies')->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->softDeletes()->after('remember_token');

            $table->index(['agency_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropColumn(['phone', 'is_active', 'deleted_at']);
        });
    }
};
