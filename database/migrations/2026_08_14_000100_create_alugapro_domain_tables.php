<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cnpj', 18)->unique();
            $table->string('phone', 20);
            $table->string('email');
            $table->longText('logo_base64')->nullable();
            $table->text('pix_key')->nullable();
            $table->timestamps();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('responsible_name');
            $table->string('phone', 20);
            $table->string('pix_key');
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('cpf', 14)->unique();
            $table->string('email')->nullable();
            $table->decimal('family_income', 12, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
        });

        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->default('identification');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->longText('document_base64');
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('type', 20)->default('residential');
            $table->decimal('usable_area', 10, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->default(0);
            $table->unsignedSmallInteger('bathrooms')->default(0);
            $table->unsignedSmallInteger('parking_spaces')->default(0);
            $table->string('street');
            $table->string('number', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood');
            $table->string('city');
            $table->char('state', 2);
            $table->string('postal_code', 9)->nullable();
            $table->decimal('rent_amount', 12, 2);
            $table->string('status', 30)->default('available');
            $table->boolean('has_solar_energy')->default(false);
            $table->timestamps();
            $table->index(['status', 'type', 'neighborhood']);
        });

        Schema::create('feature_property', function (Blueprint $table) {
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->primary(['feature_id', 'property_id']);
        });

        Schema::create('property_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('mime_type', 100);
            $table->longText('photo_base64');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('contract_months')->default(12);
            $table->unsignedSmallInteger('due_day')->default(10);
            $table->decimal('rent_amount', 12, 2);
            $table->string('status', 30)->default('awaiting_completion');
            $table->boolean('has_solar_energy')->default(false);
            $table->string('utility_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('solar_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('initial_reading', 12, 3);
            $table->decimal('price_per_kwh', 10, 4);
            $table->timestamps();
        });

        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->date('reference_month');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('open');
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->timestamps();
            $table->unique(['lease_id', 'type', 'reference_month']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('solar_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solar_config_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->date('reference_month');
            $table->decimal('previous_reading', 12, 3);
            $table->decimal('meter_reading', 12, 3);
            $table->decimal('consumption_kwh', 12, 3);
            $table->decimal('amount', 12, 2);
            $table->longText('photo_base64')->nullable();
            $table->string('photo_mime_type', 100)->nullable();
            $table->decimal('ocr_reading', 12, 3)->nullable();
            $table->decimal('ocr_confidence', 5, 4)->nullable();
            $table->string('ocr_status', 30)->default('manual');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['solar_config_id', 'reference_month']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->string('status', 30)->default('draft');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('signer_type', 20);
            $table->string('signer_name');
            $table->string('signer_document', 30)->nullable();
            $table->string('verification_channel', 30)->default('whatsapp_otp');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('evidence_hash', 64);
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['contract_id', 'signer_type']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('signer_type', 20);
            $table->string('phone', 20);
            $table->string('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'signer_type', 'expires_at']);
        });

        Schema::create('pix_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->string('txid', 35)->unique();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('fine_amount', 12, 2)->default(0);
            $table->decimal('interest_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->text('br_code');
            $table->string('provider', 30)->default('local_emv');
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('condominium_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->string('label');
            $table->string('route')->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('permission')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 20);
            $table->string('recipient_type', 20);
            $table->string('event', 50);
            $table->text('message');
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('condominium_rules');
        Schema::dropIfExists('pix_payments');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('contract_signatures');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('solar_readings');
        Schema::dropIfExists('charges');
        Schema::dropIfExists('solar_configs');
        Schema::dropIfExists('leases');
        Schema::dropIfExists('property_photos');
        Schema::dropIfExists('feature_property');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('features');
        Schema::dropIfExists('client_documents');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('companies');
    }
};
