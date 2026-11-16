<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Daily announcements: same message every day between two times (hotel time), optionally only on some weekdays. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->time('daily_from')->nullable()->after('ends_at');
            $table->time('daily_until')->nullable()->after('daily_from');
            $table->json('days')->nullable()->after('daily_until'); // ISO weekdays 1 (Mon) … 7 (Sun); null = every day
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['daily_from', 'daily_until', 'days']);
        });
    }
};
