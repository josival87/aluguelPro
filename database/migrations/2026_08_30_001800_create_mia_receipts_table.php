<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mia_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('charge_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('mia_client_id');
            $table->string('external_id', 120)->unique();
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('mia_receipt_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mia_receipts');
    }
};
