<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pix_payments', function (Blueprint $table) {
            $table->foreignId('generated_by_user_id')->nullable()->after('charge_id')->constrained('users')->nullOnDelete();
            $table->string('generated_by_type', 20)->nullable()->after('generated_by_user_id');
            $table->string('generated_by_name')->nullable()->after('generated_by_type');
            $table->foreignId('receipt_uploaded_by_user_id')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->string('receipt_original_name')->nullable()->after('receipt_uploaded_by_user_id');
            $table->string('receipt_mime_type', 100)->nullable()->after('receipt_original_name');
            $table->unsignedBigInteger('receipt_size_bytes')->nullable()->after('receipt_mime_type');
            $table->string('receipt_checksum_sha256', 64)->nullable()->after('receipt_size_bytes');
            $table->longText('receipt_document_base64')->nullable()->after('receipt_checksum_sha256');
            $table->timestamp('receipt_uploaded_at')->nullable()->after('receipt_document_base64');
        });
    }

    public function down(): void
    {
        Schema::table('pix_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_uploaded_by_user_id');
            $table->dropConstrainedForeignId('generated_by_user_id');
            $table->dropColumn([
                'generated_by_type',
                'generated_by_name',
                'receipt_original_name',
                'receipt_mime_type',
                'receipt_size_bytes',
                'receipt_checksum_sha256',
                'receipt_document_base64',
                'receipt_uploaded_at',
            ]);
        });
    }
};
