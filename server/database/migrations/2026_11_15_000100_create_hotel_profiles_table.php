<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table: hotel identity & guest-facing info.
        Schema::create('hotel_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('about')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('reception_phone', 32)->nullable();
            $table->string('reception_extension', 16)->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->text('wifi_password')->nullable(); // encrypted cast
            $table->string('check_in_time', 5)->default('14:00');
            $table->string('check_out_time', 5)->default('12:00');
            $table->text('check_out_info')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->jsonb('technician_pin')->nullable(); // {algo,iterations,salt,hash}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_profiles');
    }
};
