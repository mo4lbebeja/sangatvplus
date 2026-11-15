<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Inbound integration: the hotel PMS/BMS pushes rooms & guest stays to SangaTV Plus. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key_id', 40)->unique();             // public identifier sent in X-SangaTV-Key
            $table->text('secret');                              // encrypted; HMAC key / bearer token
            $table->string('auth_mode', 16)->default('hmac');    // hmac | bearer
            $table->json('allowed_ips')->nullable();             // null/empty = any
            $table->boolean('auto_create_rooms')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 8);
            $table->string('path');
            $table->string('event', 40)->nullable();             // checkin | checkout | move | ...
            $table->string('reservation_id', 64)->nullable();
            $table->string('room_number', 16)->nullable();
            $table->unsignedSmallInteger('http_status');
            $table->string('status', 10);                        // ok | error
            $table->string('message')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pms_integration_id', 'created_at']);
            $table->index(['pms_integration_id', 'idempotency_key']);
            $table->index('reservation_id');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->timestamp('expected_checkout_at')->nullable()->after('checked_out_at');
            $table->string('source', 10)->default('cms')->after('external_ref'); // cms | pms
            $table->index('external_ref');
        });

        Schema::table('hotel_profiles', function (Blueprint $table) {
            $table->json('pms_checklist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_profiles', fn (Blueprint $t) => $t->dropColumn('pms_checklist'));
        Schema::table('guests', function (Blueprint $t) {
            $t->dropIndex(['external_ref']);
            $t->dropColumn(['expected_checkout_at', 'source']);
        });
        Schema::dropIfExists('pms_events');
        Schema::dropIfExists('pms_integrations');
    }
};
