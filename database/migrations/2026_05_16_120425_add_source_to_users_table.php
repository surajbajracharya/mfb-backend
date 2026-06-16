<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // self_registered: signed up via website
            // admin_created: added by admin via Add Customer form
            // csv_import: bulk imported via CSV upload
            $table->enum('source', ['self_registered', 'admin_created', 'csv_import'])
                  ->default('self_registered')
                  ->after('experience_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
