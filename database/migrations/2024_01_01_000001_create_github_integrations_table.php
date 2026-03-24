<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('github_user_id')->nullable();
            $table->string('github_username')->nullable();
            $table->string('github_email')->nullable();
            $table->text('access_token')->nullable();
            $table->string('token_type')->default('bearer');
            $table->text('scope')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('default_repo_owner')->nullable();
            $table->string('default_repo_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_integrations');
    }
};
