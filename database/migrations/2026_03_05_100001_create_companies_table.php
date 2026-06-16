<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('domain')->unique();        // e.g. a.com
            $table->string('api_domain')->unique();    // e.g. api.a.com
            $table->string('admin_domain')->unique();  // e.g. admin.a.com
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('plan_type', 50)->default('standard');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
