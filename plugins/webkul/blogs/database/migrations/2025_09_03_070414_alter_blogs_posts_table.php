
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
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('blogs_posts', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable(false)->change();
            });
        } else {
            Schema::table('blogs_posts', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->unsignedBigInteger('category_id')->nullable(false)->change();

                $table->foreign('category_id')
                    ->references('id')
                    ->on('blogs_categories')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('blogs_posts', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->change();
            });
        } else {
            Schema::table('blogs_posts', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->unsignedBigInteger('category_id')->nullable()->change();

                $table->foreign('category_id')
                    ->references('id')
                    ->on('blogs_categories')
                    ->nullOnDelete();
            });
        }
    }
};
