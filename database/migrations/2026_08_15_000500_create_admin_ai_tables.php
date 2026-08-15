<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('admin_ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('admin_ai_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('admin_ai_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('admin_ai_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('admin_ai_messages')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->json('parameters');
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('status', 30);
            $table->json('result')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->index(['action', 'status', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_ai_actions');
        Schema::dropIfExists('admin_ai_messages');
        Schema::dropIfExists('admin_ai_conversations');
    }
};
