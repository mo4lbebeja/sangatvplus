<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TV app releases managed by Sijalucom (copied into every targeted hotel's app_releases). */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('package_name', 64);
            $table->unsignedInteger('version_code');
            $table->string('version_name', 32);
            $table->string('apk_path');
            $table->string('sha256', 64);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('hotel_ids')->nullable(); // null = all hotels
            $table->foreignId('published_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['package_name', 'version_code']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('releases');
    }
};
