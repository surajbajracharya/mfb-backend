<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_refunded')->default(false)->after('subtotal');
            $table->timestamp('refunded_at')->nullable()->after('is_refunded');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['is_refunded', 'refunded_at']);
        });
    }
};
