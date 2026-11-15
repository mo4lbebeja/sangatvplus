<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database (multi-hotel): the hotel list, routing indexes for TVs and PMS, plus sessions/cache/jobs,
 * which are read before the hotel is known. Run with: php artisan migrate --database=central --path=database/migrations/central
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        $s->create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique(); // "Kode Hotel" typed before login, e.g. napolin
            $table->string('name');
            $table->string('database', 64)->unique();
            $table->string('storage_dir', 80)->default(''); // public files under storage/app/public/<dir>; '' = root (first hotel)
            $table->string('status', 16)->default('active'); // active | suspended
            $table->timestamp('service_expires_at')->nullable(); // rental period (set by Sijalucom)
            $table->timestamp('service_released_at')->nullable(); // released by Sijalucom (contract ended)
            $table->json('features')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // TV registration codes are typed without a hotel: this index says which hotel issued a code.
        $s->create('hotel_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->timestamp('expires_at');
        });

        // PMS API key id → hotel (the key header arrives before the hotel database is open).
        $s->create('pms_key_index', function (Blueprint $table) {
            $table->string('key_id', 64)->primary();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
        });

        if (! $s->hasTable('sessions')) {
            $s->create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
        if (! $s->hasTable('cache')) {
            $s->create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
            $s->create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }
        if (! $s->hasTable('jobs')) {
            $s->create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedSmallInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
            $s->create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->dropIfExists('pms_key_index');
        $s->dropIfExists('hotel_codes');
        $s->dropIfExists('hotels');
    }
};
