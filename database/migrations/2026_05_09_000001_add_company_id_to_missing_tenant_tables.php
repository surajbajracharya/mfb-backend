<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. resource_views — add company_id ────────────────────────────────
        Schema::table('resource_views', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->nullable()->after('id');
            $t->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $t->index('company_id');
        });

        // Back-fill from the resource's own company_id so existing rows stay correct
        DB::statement('
            UPDATE resource_views rv
            INNER JOIN resources r ON rv.resource_id = r.id
            SET rv.company_id = r.company_id
            WHERE rv.company_id IS NULL
        ');

        // ── 2. plan_items — add company_id ────────────────────────────────────
        Schema::table('plan_items', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->nullable()->after('id');
            $t->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $t->index('company_id');
        });

        // Back-fill from the parent plan's company_id
        DB::statement('
            UPDATE plan_items pi
            INNER JOIN plans p ON pi.plan_id = p.id
            SET pi.company_id = p.company_id
            WHERE pi.company_id IS NULL
        ');

        // ── 3. user_plan_subscriptions — add FK + index (column already exists) ──
        Schema::table('user_plan_subscriptions', function (Blueprint $t) {
            $t->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $t->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_plan_subscriptions', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropIndex(['company_id']);
        });

        Schema::table('plan_items', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropIndex(['company_id']);
            $t->dropColumn('company_id');
        });

        Schema::table('resource_views', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropIndex(['company_id']);
            $t->dropColumn('company_id');
        });
    }
};
