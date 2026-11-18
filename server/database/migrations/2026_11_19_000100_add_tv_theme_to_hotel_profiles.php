<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TV design chosen by the hotel (CMS → Profil Hotel → Tema TV). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_profiles', fn (Blueprint $table) => $table->string('tv_theme', 24)->default('ocean'));
    }

    public function down(): void
    {
        Schema::table('hotel_profiles', fn (Blueprint $table) => $table->dropColumn('tv_theme'));
    }
};
