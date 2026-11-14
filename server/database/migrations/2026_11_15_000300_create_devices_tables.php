<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_uid', 128)->unique();
            $table->string('model')->nullable();
            $table->string('android_version', 32)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->unsignedInteger('app_version_code')->nullable();
            $table->string('status', 16)->default('active'); // active | revoked
            $table->boolean('kiosk_enabled')->default(false);
            $table->boolean('device_owner')->default(false);
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('provisioning_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique(); // sha256 of the code
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // clear_app_data | refresh | reboot
            $table->jsonb('payload')->nullable();
            $table->string('status', 16)->default('pending'); // pending | delivered | done | failed
            $table->jsonb('result')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
        Schema::dropIfExists('provisioning_codes');
        Schema::dropIfExists('devices');
    }
};
