<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Channel features: EPG, room-type packages, guest language, adult lock,
 * announcements, viewing statistics, uptime history and guest favourites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('epg_id', 191)->nullable()->index(); // XMLTV <channel id="…">
            $table->json('room_types')->nullable(); // null/[] = every room
            $table->string('language', 5)->nullable(); // shown first for guests with this language
            $table->boolean('is_adult')->default(false); // needs the adult PIN on the TV
        });

        Schema::table('hotel_profiles', function (Blueprint $table) {
            $table->text('epg_urls')->nullable(); // one XMLTV URL per line (.xml or .xml.gz)
            $table->timestamp('epg_synced_at')->nullable();
            $table->string('epg_status', 255)->nullable();
            $table->json('adult_pin')->nullable(); // PBKDF2 hash, like technician_pin
            $table->text('report_emails')->nullable(); // weekly uptime report recipients
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->json('favorite_channel_ids')->nullable(); // gone with the stay at check-out
        });

        Schema::create('epg_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 120)->nullable();
            $table->index(['channel_id', 'starts_at']);
            $table->index('ends_at');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('message');
            $table->string('style', 16)->default('ticker'); // ticker | fullscreen
            $table->string('level', 16)->default('info'); // info | warning | emergency
            $table->foreignId('force_channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->json('floors')->nullable(); // null/[] + no rooms = every room
            $table->json('room_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('channel_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->unsignedInteger('seconds');
            $table->index(['started_at', 'channel_id']);
        });

        Schema::create('channel_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16); // ok | error | skipped
            $table->string('message', 255)->nullable();
            $table->timestamp('checked_at');
            $table->index(['checked_at', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_checks');
        Schema::dropIfExists('channel_views');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('epg_programmes');
        Schema::table('guests', fn (Blueprint $table) => $table->dropColumn('favorite_channel_ids'));
        Schema::table('hotel_profiles', fn (Blueprint $table) => $table->dropColumn(['epg_urls', 'epg_synced_at', 'epg_status', 'adult_pin', 'report_emails']));
        Schema::table('channels', function (Blueprint $table) {
            $table->dropIndex(['epg_id']);
            $table->dropColumn(['epg_id', 'room_types', 'language', 'is_adult']);
        });
    }
};
