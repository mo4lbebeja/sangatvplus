<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Server-side stream probe result.
        Schema::table('channels', function (Blueprint $table) {
            $table->string('health_status', 12)->default('unknown'); // ok | error | skipped | unknown
            $table->string('health_message')->nullable();
            $table->timestamp('health_checked_at')->nullable();
        });

        // Playback problems reported by TVs (failover to backup, failed, recovered).
        Schema::create('channel_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12);           // failover | failed | recovered
            $table->string('message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['channel_id', 'created_at']);
        });

        Schema::table('hotel_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('tv_start_volume')->nullable();          // % when the TV turns on; null = leave
            $table->foreignId('tv_start_channel_id')->nullable()->constrained('channels')->nullOnDelete(); // null = last watched
            $table->boolean('show_hdmi_inputs')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_profiles', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tv_start_channel_id');
            $t->dropColumn(['tv_start_volume', 'show_hdmi_inputs']);
        });
        Schema::dropIfExists('channel_reports');
        Schema::table('channels', fn (Blueprint $t) => $t->dropColumn(['health_status', 'health_message', 'health_checked_at']));
    }
};
