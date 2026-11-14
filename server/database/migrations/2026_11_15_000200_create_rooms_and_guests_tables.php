<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('number', 16)->unique();
            $table->unsignedSmallInteger('floor');
            $table->string('type', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('salutation', 32)->nullable(); // Bapak / Ibu / Mr. / Mrs.
            $table->string('name');
            $table->string('language', 5)->default('id');
            $table->string('status', 16)->default('in_house'); // in_house | checked_out
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('external_ref')->nullable(); // reserved for PMS VHP
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
        Schema::dropIfExists('rooms');
    }
};
