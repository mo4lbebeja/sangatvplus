<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vendor console: plans, monthly subscriptions, invoices, activity log, company settings, staff roles. */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        $s->create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_price')->default(0); // IDR per month
            $table->unsignedBigInteger('price_per_tv')->default(0); // IDR per TV per month
            $table->unsignedInteger('included_tvs')->default(0); // TVs covered by the base price
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $s->table('hotels', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('status')->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('tv_quota')->nullable()->after('plan_id'); // contracted TVs (null = unlimited)
            $table->unsignedTinyInteger('billing_months')->default(1)->after('tv_quota'); // cycle: 1, 3, 6, 12
            $table->unsignedBigInteger('custom_monthly_price')->nullable()->after('billing_months'); // overrides the plan
            $table->decimal('discount_percent', 5, 2)->default(0)->after('custom_monthly_price');
            $table->boolean('auto_invoice')->default(true)->after('discount_percent');
            $table->timestamp('activated_at')->nullable()->after('auto_invoice'); // first activation
            $table->string('city', 80)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('billing_name')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('npwp', 32)->nullable();
        });

        $s->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('status', 16)->default('unpaid')->index(); // draft | unpaid | paid | void
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedTinyInteger('months');
            $table->json('items');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total');
            $table->date('issued_at');
            $table->date('due_at')->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_ref', 120)->nullable();
            $table->timestamp('applied_at')->nullable(); // rental extended by this invoice
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->timestamps();
        });

        $s->create('vendor_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_user_id')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained('hotels')->cascadeOnDelete();
            $table->string('action', 60)->index();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $s->create('vendor_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value')->nullable();
        });

        $s->table('vendor_users', function (Blueprint $table) {
            $table->string('role', 16)->default('owner')->after('email'); // owner | finance | support
        });
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->table('vendor_users', fn (Blueprint $t) => $t->dropColumn('role'));
        $s->dropIfExists('vendor_settings');
        $s->dropIfExists('vendor_activities');
        $s->dropIfExists('invoices');
        $s->table('hotels', function (Blueprint $t) {
            $t->dropConstrainedForeignId('plan_id');
            $t->dropColumn(['tv_quota', 'billing_months', 'custom_monthly_price', 'discount_percent', 'auto_invoice', 'activated_at', 'city', 'contact_phone', 'billing_name', 'billing_address', 'billing_email', 'npwp']);
        });
        $s->dropIfExists('plans');
    }
};
