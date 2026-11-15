<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->string('album', 64)->nullable();     // e.g. Kamar, Kolam Renang, Restoran
            $table->string('title')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('image_path');                 // ≤1920px, for the full-screen viewer
            $table->string('thumb_path')->nullable();     // 480px, for the grid (light for 1 GB TVs)
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
    }
};
