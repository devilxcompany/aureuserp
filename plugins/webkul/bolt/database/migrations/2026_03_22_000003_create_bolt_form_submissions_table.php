<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bolt_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->json('data')->comment('Submitted field values');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->foreignId('form_id')
                ->constrained('bolt_forms')
                ->cascadeOnDelete();

            $table->foreignId('page_id')
                ->nullable()
                ->constrained('bolt_pages')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('bolt_form_submissions', function (Blueprint $table) {
                $table->dropForeign(['form_id']);
                $table->dropForeign(['page_id']);
            });
        }

        Schema::dropIfExists('bolt_form_submissions');

        Schema::enableForeignKeyConstraints();
    }
};
