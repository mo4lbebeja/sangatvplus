<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->string('kicker')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32)->default('facility'); // facility | dining
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable(); // e.g. "Lantai 4"
            $table->string('hours')->nullable(); // e.g. "06.00 – 22.00"
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('app_whitelist', function (Blueprint $table) {
            $table->id();
            $table->string('package_name')->unique();
            $table->string('label');
            $table->string('icon_path')->nullable();
            $table->boolean('clear_on_checkout')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_code')->unique();
            $table->string('version_name', 32);
            $table->string('apk_path');
            $table->string('sha256', 64);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
        Schema::dropIfExists('app_whitelist');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('slides');
    }
};
