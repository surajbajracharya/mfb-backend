<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('modules_enabled')->nullable()->after('plan_type');
        });

        // Default: all 4 modules enabled for existing companies
        \App\Models\Company::query()->update([
            'modules_enabled' => json_encode(['courses', 'events', 'appointments', 'resources']),
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('modules_enabled');
        });
    }
};
