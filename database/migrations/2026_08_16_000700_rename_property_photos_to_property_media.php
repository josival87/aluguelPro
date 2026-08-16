<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('property_photos', 'property_media');

        Schema::table('property_media', function (Blueprint $table) {
            $table->renameColumn('photo_base64', 'media_base64');
        });
    }

    public function down(): void
    {
        Schema::table('property_media', function (Blueprint $table) {
            $table->renameColumn('media_base64', 'photo_base64');
        });

        Schema::rename('property_media', 'property_photos');
    }
};
