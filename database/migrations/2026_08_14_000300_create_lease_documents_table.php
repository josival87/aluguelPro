<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40)->default('other');
            $table->string('original_name');
            $table->string('mime_type', 150)->default('application/octet-stream');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('description')->nullable();
            $table->longText('document_base64');
            $table->timestamps();

            $table->index(['lease_id', 'created_at']);
            $table->index(['lease_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_documents');
    }
};
