<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Subscription period + manual release: when the service ends, TVs hand the screen back to the TV's own launcher. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_profiles', function (Blueprint $table) {
            $table->timestamp('service_expires_at')->nullable(); // set by the vendor (php artisan sangatv:layanan)
            $table->timestamp('service_released_at')->nullable(); // "Lepaskan semua TV" pressed
        });
    }

    public function down(): void
    {
        Schema::table('hotel_profiles', fn (Blueprint $table) => $table->dropColumn(['service_expires_at', 'service_released_at']));
    }
};
