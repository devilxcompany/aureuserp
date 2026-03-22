<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('submissions_enabled')->default(true);
            $table->string('recipient_email')->nullable();
            $table->text('confirmation_message')->nullable();
            $table->string('redirect_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
