<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // settings: change unique(key) → unique(company_id, key)
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_key_unique');
            $table->unique(['company_id', 'key'], 'settings_company_key_unique');
        });

        // email_templates: change unique(key) → unique(company_id, key)
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique('email_templates_key_unique');
            $table->unique(['company_id', 'key'], 'email_templates_company_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_company_key_unique');
            $table->unique('key', 'settings_key_unique');
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique('email_templates_company_key_unique');
            $table->unique('key', 'email_templates_key_unique');
        });
    }
};
