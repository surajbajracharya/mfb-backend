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
            // Drop the global unique constraint on email alone
            $table->dropUnique('users_email_unique');

            // Allow the same email across different companies
            $table->unique(['email', 'company_id'], 'users_email_company_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_company_unique');
            $table->unique('email', 'users_email_unique');
        });
    }
};
