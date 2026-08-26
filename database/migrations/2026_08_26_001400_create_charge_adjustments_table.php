<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('previous_amount', 12, 2);
            $table->decimal('new_amount', 12, 2);
            $table->string('action', 30);
            $table->timestamps();
            $table->index(['charge_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_adjustments');
    }
};
