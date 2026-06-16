<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        foreach (['courses', 'events', 'resources', 'appointment_types', 'challenges'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('robots', 50)->nullable()->after('schema_markup');
            });
        }
    }

    public function down(): void {
        foreach (['courses', 'events', 'resources', 'appointment_types', 'challenges'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('robots');
            });
        }
    }
};
