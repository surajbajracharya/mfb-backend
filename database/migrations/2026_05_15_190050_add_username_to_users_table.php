<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Back-fill existing users with a slug derived from their name
        DB::table('users')->orderBy('id')->get()->each(function ($user) {
            $base = Str::slug($user->name ?: 'user') ?: 'user';
            $slug = $base;
            $i    = 1;
            while (DB::table('users')->where('username', $slug)->where('id', '!=', $user->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $slug]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
