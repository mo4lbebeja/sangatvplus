<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('channel_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('signing', 32)->default('none'); // none | hmac_query
            $table->text('credentials')->nullable(); // encrypted:array, never sent to TVs
            $table->unsignedInteger('token_ttl_seconds')->default(600);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->string('name');
            $table->foreignId('channel_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 32); // local_multicast | local_hls | provider_hls | provider_dash
            $table->text('url');
            $table->text('backup_url')->nullable();
            $table->jsonb('drm')->nullable(); // {scheme:"widevine", license_url, headers{}}
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
        Schema::dropIfExists('channel_providers');
        Schema::dropIfExists('channel_categories');
    }
};
