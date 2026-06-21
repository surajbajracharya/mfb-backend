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
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->string('getresponse_api_key')->nullable()->after('mandatory_fields');
            $table->string('getresponse_list_id')->nullable()->after('getresponse_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            if (Schema::hasColumn('appointment_types', 'getresponse_api_key')) {
                $table->dropColumn('getresponse_api_key');
            }
            if (Schema::hasColumn('appointment_types', 'getresponse_list_id')) {
                $table->dropColumn('getresponse_list_id');
            }
        });
    }
};
