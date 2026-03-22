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
        Schema::create('bolt_content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->comment('hero, testimonial, cta, text, image, etc.');
            $table->json('content')->nullable()->comment('Block content as JSON');
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('page_id')
                ->nullable()
                ->constrained('bolt_pages')
                ->nullOnDelete();

            $table->foreignId('creator_id')
                ->nullable()
                ->constrained('users')
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
            Schema::table('bolt_content_blocks', function (Blueprint $table) {
                $table->dropForeign(['page_id']);
                $table->dropForeign(['creator_id']);
            });
        }

        Schema::dropIfExists('bolt_content_blocks');

        Schema::enableForeignKeyConstraints();
    }
};
