<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing 'music' rows to 'audio'
        DB::table('resources')->where('type', 'music')->update(['type' => 'audio']);

        // Update the enum column
        DB::statement("ALTER TABLE resources MODIFY COLUMN type ENUM('blog', 'video', 'audio', 'pdf') NOT NULL DEFAULT 'blog'");
    }

    public function down(): void
    {
        DB::table('resources')->where('type', 'audio')->update(['type' => 'music']);
        DB::statement("ALTER TABLE resources MODIFY COLUMN type ENUM('blog', 'video', 'music', 'pdf') NOT NULL DEFAULT 'blog'");
    }
};
