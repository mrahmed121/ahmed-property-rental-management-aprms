<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropUnique('alloc_payment_charge_unique');
            // A payment may allocate to the same charge under different
            // waterfall buckets (e.g. utilities, then arrears).
            $table->unique(
                ['payment_id', 'allocatable_type', 'allocatable_id', 'bucket'],
                'alloc_payment_charge_bucket_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropUnique('alloc_payment_charge_bucket_unique');
            $table->unique(['payment_id', 'allocatable_type', 'allocatable_id'], 'alloc_payment_charge_unique');
        });
    }
};
