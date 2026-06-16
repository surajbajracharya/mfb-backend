<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image');
        });

        // Migrate existing single image → images array
        DB::statement("
            UPDATE appointment_types
            SET images = JSON_ARRAY(image)
            WHERE image IS NOT NULL AND image != ''
        ");

        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->string('image')->nullable()->after('images');
        });

        DB::statement("
            UPDATE appointment_types
            SET image = JSON_UNQUOTE(JSON_EXTRACT(images, '\$[0]'))
            WHERE images IS NOT NULL AND JSON_LENGTH(images) > 0
        ");

        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
