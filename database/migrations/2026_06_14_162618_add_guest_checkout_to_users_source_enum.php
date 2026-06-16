<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN source ENUM('self_registered','admin_created','csv_import','guest_checkout') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN source ENUM('self_registered','admin_created','csv_import') NULL DEFAULT NULL");
    }
};
