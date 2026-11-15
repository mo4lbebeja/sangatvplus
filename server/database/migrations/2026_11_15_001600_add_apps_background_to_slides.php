<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            // This slide's image is also the background of the TV "Aplikasi" screen (only one at a time).
            $table->boolean('is_apps_background')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('slides', fn (Blueprint $table) => $table->dropColumn('is_apps_background'));
    }
};
