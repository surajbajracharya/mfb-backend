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
        Schema::table('users', function (Blueprint $table) {
            $table->text('interests')->nullable()->after('bio');
            $table->enum('experience_level', ['beginner', 'intermediate', 'experienced'])
                  ->default('beginner')
                  ->after('interests');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['interests', 'experience_level']);
        });
    }
};
