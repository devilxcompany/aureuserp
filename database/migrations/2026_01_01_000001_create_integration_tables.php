<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('integration')->index(); // github, pabbly, supabase, bolt_cms
            $table->string('event_type')->index(); // webhook_received, sync_started, sync_completed, error
            $table->string('source')->nullable(); // originating system
            $table->string('destination')->nullable(); // target system
            $table->string('status')->default('pending'); // pending, processing, success, failed
            $table->json('payload')->nullable(); // event data
            $table->json('response')->nullable(); // response data
            $table->text('error_message')->nullable();
            $table->string('trace_id')->nullable()->index(); // correlate related events
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index(); // github, pabbly, bolt_cms
            $table->string('event_type')->index();
            $table->string('delivery_id')->nullable()->unique(); // external delivery ID
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->string('status')->default('received'); // received, processing, processed, failed, skipped
            $table->string('handler')->nullable(); // which handler processed it
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_queue', function (Blueprint $table) {
            $table->id();
            $table->string('integration')->index();
            $table->string('action'); // sync_orders, sync_products, sync_customers, etc.
            $table->json('data')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed, paused
            $table->integer('priority')->default(5); // 1=highest, 10=lowest
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_configs', function (Blueprint $table) {
            $table->id();
            $table->string('integration')->index(); // github, pabbly, supabase, bolt_cms, master
            $table->string('key')->index();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'key']);
        });

        Schema::create('sync_records', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type')->index(); // order, product, customer, invoice
            $table->string('local_id')->index();
            $table->string('source_system')->index(); // erp, supabase, bolt_cms, github
            $table->string('external_id')->nullable();
            $table->string('external_system')->nullable();
            $table->string('sync_status')->default('synced'); // synced, pending, conflict, failed
            $table->json('checksums')->nullable(); // checksums per system for conflict detection
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['entity_type', 'local_id', 'source_system', 'external_system'], 'sync_records_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_records');
        Schema::dropIfExists('integration_configs');
        Schema::dropIfExists('integration_queue');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('integration_logs');
    }
};
