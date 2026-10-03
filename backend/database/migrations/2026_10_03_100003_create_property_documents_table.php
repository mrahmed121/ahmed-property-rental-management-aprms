<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Polymorphic parent: Property | Building | Unit.
            $table->string('documentable_type', 120);
            $table->unsignedBigInteger('documentable_id');
            $table->string('name');
            $table->enum('document_type', ['deed', 'noc', 'floor_plan', 'photo', 'agreement', 'other'])
                ->default('other');
            // Relative path inside the documents disk (never a URL, never absolute).
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'documentable_type', 'documentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_documents');
    }
};
