<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('screen_on')->nullable();          // reported by the TV (PowerManager.isInteractive)
            $table->timestamp('screen_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $t) => $t->dropColumn(['screen_on', 'screen_changed_at']));
    }
};
