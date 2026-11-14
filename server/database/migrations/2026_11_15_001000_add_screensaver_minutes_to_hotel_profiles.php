<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_profiles', function (Blueprint $table) {
            // Idle minutes before the TV's own screensaver starts. 0 = leave the TV's setting alone.
            $table->unsignedSmallInteger('screensaver_minutes')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_profiles', function (Blueprint $table) {
            $table->dropColumn('screensaver_minutes');
        });
    }
};
