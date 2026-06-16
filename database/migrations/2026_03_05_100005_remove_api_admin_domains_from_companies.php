<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['api_domain']);
            $table->dropUnique(['admin_domain']);
            $table->dropColumn(['api_domain', 'admin_domain']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('api_domain')->nullable()->unique()->after('domain');
            $table->string('admin_domain')->nullable()->unique()->after('api_domain');
        });
    }
};
