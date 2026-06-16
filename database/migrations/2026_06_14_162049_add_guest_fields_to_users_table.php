<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('time_of_birth')->nullable()->after('date_of_birth');
            $table->string('place_of_birth')->nullable()->after('time_of_birth');
            $table->boolean('is_guest')->default(false)->after('place_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'time_of_birth', 'place_of_birth', 'is_guest']);
        });
    }
};
