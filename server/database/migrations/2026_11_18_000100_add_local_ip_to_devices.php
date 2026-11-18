<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The TV's address on the hotel network (last_ip is the hotel's public address, the same for every TV). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->string('local_ip', 45)->nullable()->after('last_ip'));
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn('local_ip'));
    }
};
