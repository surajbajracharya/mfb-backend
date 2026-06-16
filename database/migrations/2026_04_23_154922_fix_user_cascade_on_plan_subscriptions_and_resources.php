<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. user_plan_subscriptions — add proper FK with cascade
        Schema::table('user_plan_subscriptions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 2. resources — drop old FK (no cascade) and re-add with cascade
        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_plan_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
