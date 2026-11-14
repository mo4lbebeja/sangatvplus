<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the TV reports about its own auto-start / permission setup (via heartbeat). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('is_default_home')->nullable();
            $table->boolean('can_draw_overlays')->nullable();
            $table->boolean('can_install_updates')->nullable();
            $table->boolean('stock_launcher_active')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['is_default_home', 'can_draw_overlays', 'can_install_updates', 'stock_launcher_active']);
        });
    }
};
