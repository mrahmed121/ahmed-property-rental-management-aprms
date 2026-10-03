<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('maintenance_vendors')->nullOnDelete();
            $table->string('expense_number');
            // maintenance|utilities|repairs|cleaning|security|tax_fee|insurance|management|supplies|other
            $table->string('category');
            $table->text('description');
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            // draft|submitted|approved|rejected|posted|reversed
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'expense_number']);
            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'property_id', 'expense_date']);
            $table->index(['agency_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
