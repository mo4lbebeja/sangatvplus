<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OTA per app package (staging vs release builds never cross) and a per-TV "hold updates"
 * switch, so a TV nobody can attend to never gets an install dialog on its screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_releases', function (Blueprint $table) {
            $table->string('package_name', 100)->default('com.sangatv.plus.staging')->after('id');
            $table->dropUnique(['version_code']);
            $table->unique(['package_name', 'version_code']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('app_package', 100)->nullable();
            $table->boolean('hold_updates')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn(['app_package', 'hold_updates']));
        Schema::table('app_releases', function (Blueprint $table) {
            $table->dropUnique(['package_name', 'version_code']);
            $table->unique('version_code');
            $table->dropColumn('package_name');
        });
    }
};
