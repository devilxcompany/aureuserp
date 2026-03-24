<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id')->nullable()->index();
            $table->string('direction')->default('erp_to_github');
            $table->string('resource_type')->index();
            $table->string('resource_id')->nullable();
            $table->string('github_url')->nullable();
            $table->string('status')->default('pending');
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_sync_logs');
    }
};
