<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_profiles', function (Blueprint $table) {
            $table->string('home_music_path')->nullable();
            $table->unsignedTinyInteger('home_music_volume')->default(30); // 0–100
            $table->boolean('home_music_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_profiles', function (Blueprint $table) {
            $table->dropColumn(['home_music_path', 'home_music_volume', 'home_music_enabled']);
        });
    }
};
