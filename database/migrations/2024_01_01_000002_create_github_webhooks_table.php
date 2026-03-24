<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id')->nullable()->index();
            $table->string('delivery_id')->nullable()->unique();
            $table->string('event')->index();
            $table->string('action')->nullable();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->string('status')->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_webhooks');
    }
};
