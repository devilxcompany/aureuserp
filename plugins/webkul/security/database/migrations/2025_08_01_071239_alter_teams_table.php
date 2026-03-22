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
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('creator_id')->nullable()->after('id');

            $table->foreign('creator_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropForeign(['creator_id']);
            });
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('creator_id');
        });
    }
};
