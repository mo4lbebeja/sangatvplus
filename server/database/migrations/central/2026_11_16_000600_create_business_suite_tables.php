<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business suite: online payments & reminders, auto-suspend, churn, map, client portal, leads pipeline,
 * fleet uptime snapshots, field jobs, asset inventory, login log, console notifications.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        $s->table('hotels', function (Blueprint $t) {
            $t->string('suspended_reason', 24)->nullable(); // manual | overdue
            $t->string('churn_reason')->nullable();
            $t->timestamp('churned_at')->nullable();
            $t->string('source', 40)->nullable(); // where the client came from
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('portal_token', 64)->nullable()->unique();
            $t->json('content_template')->nullable();
        });

        $s->table('invoices', function (Blueprint $t) {
            $t->string('kind', 12)->default('rental'); // rental | one_off
            $t->string('gateway', 16)->nullable(); // xendit | midtrans
            $t->string('gateway_ref', 120)->nullable()->index();
            $t->text('payment_url')->nullable();
            $t->string('tax_number', 40)->nullable(); // nomor seri faktur pajak
            $t->json('reminders')->nullable(); // reminder keys already sent
        });

        $s->create('leads', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('city', 80)->nullable();
            $t->string('contact_name')->nullable();
            $t->string('contact_phone', 32)->nullable();
            $t->string('contact_email')->nullable();
            $t->unsignedInteger('rooms')->nullable();
            $t->string('stage', 16)->default('new'); // new | contacted | demo | negotiation | won | lost
            $t->unsignedBigInteger('estimated_value')->nullable(); // IDR / month
            $t->string('source', 40)->nullable();
            $t->date('next_follow_up')->nullable();
            $t->string('lost_reason')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('vendor_users')->nullOnDelete();
            $t->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete();
            $t->timestamps();
        });

        $s->create('fleet_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $t->timestamp('taken_at')->index();
            $t->unsignedInteger('tvs');
            $t->unsignedInteger('online');
            $t->index(['hotel_id', 'taken_at']);
        });

        $s->create('field_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $t->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $t->string('type', 16)->default('repair'); // install | repair | maintenance | survey | training
            $t->string('title');
            $t->timestamp('scheduled_at')->index();
            $t->unsignedSmallInteger('duration_minutes')->default(120);
            $t->foreignId('technician_id')->nullable()->constrained('vendor_users')->nullOnDelete();
            $t->string('status', 16)->default('scheduled'); // scheduled | in_progress | done | cancelled
            $t->text('notes')->nullable();
            $t->text('result')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        $s->create('assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete(); // null = gudang Sijalucom
            $t->string('type', 16)->default('tv'); // tv | stb | remote | router | other
            $t->string('brand', 60)->nullable();
            $t->string('model', 80)->nullable();
            $t->string('serial', 80)->nullable()->unique();
            $t->string('status', 16)->default('in_stock'); // in_stock | installed | repair | retired
            $t->string('location', 80)->nullable(); // room number / shelf
            $t->date('purchased_at')->nullable();
            $t->date('warranty_until')->nullable();
            $t->unsignedBigInteger('price')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $s->create('login_logs', function (Blueprint $t) {
            $t->id();
            $t->string('guard', 10); // vendor | web
            $t->foreignId('hotel_id')->nullable()->constrained('hotels')->cascadeOnDelete();
            $t->string('email')->nullable();
            $t->boolean('success');
            $t->boolean('new_ip')->default(false);
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        if (! $s->hasTable('notifications')) {
            $s->create('notifications', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('type');
                $t->morphs('notifiable');
                $t->json('data');
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        foreach (['login_logs', 'assets', 'field_jobs', 'fleet_snapshots', 'leads'] as $table) {
            $s->dropIfExists($table);
        }
        $s->table('invoices', fn (Blueprint $t) => $t->dropColumn(['kind', 'gateway', 'gateway_ref', 'payment_url', 'tax_number', 'reminders']));
        $s->table('hotels', fn (Blueprint $t) => $t->dropColumn(['suspended_reason', 'churn_reason', 'churned_at', 'source', 'latitude', 'longitude', 'portal_token', 'content_template']));
    }
};
