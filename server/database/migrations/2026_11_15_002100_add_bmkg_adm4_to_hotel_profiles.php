<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_profiles', function (Blueprint $table) {
            // BMKG village code (kode wilayah tingkat IV, e.g. 32.05.13.2001) for the official weather forecast.
            $table->string('bmkg_adm4', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_profiles', fn (Blueprint $table) => $table->dropColumn('bmkg_adm4'));
    }
};
