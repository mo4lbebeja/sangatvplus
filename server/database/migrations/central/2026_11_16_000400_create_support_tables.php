<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Console extras: support tickets, announcements to hotel CMS, contract documents, payment proofs. */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        $s->create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('subject');
            $table->string('category', 24)->default('technical'); // technical | billing | content | request | other
            $table->string('priority', 12)->default('normal'); // low | normal | high | urgent
            $table->string('status', 16)->default('open')->index(); // open | in_progress | waiting | resolved | closed
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();
            $table->string('last_reply_by', 8)->nullable(); // hotel | vendor
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        $s->create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('author_type', 8); // hotel | vendor
            $table->string('author_name');
            $table->foreignId('vendor_user_id')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->boolean('internal')->default(false); // vendor-only note
            $table->timestamp('created_at')->useCurrent();
        });

        $s->create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('level', 16)->default('info'); // info | warning | maintenance | success
            $table->json('hotel_ids')->nullable(); // null = all hotels
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->timestamps();
        });

        $s->create('hotel_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 16)->default('contract'); // contract | mou | bast | invoice | other
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->date('signed_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->timestamps();
        });

        $s->table('invoices', function (Blueprint $table) {
            $table->string('payment_proof')->nullable()->after('payment_ref');
        });
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->table('invoices', fn (Blueprint $t) => $t->dropColumn('payment_proof'));
        $s->dropIfExists('hotel_documents');
        $s->dropIfExists('notices');
        $s->dropIfExists('ticket_messages');
        $s->dropIfExists('tickets');
    }
};
