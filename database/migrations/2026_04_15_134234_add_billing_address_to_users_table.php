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
            $table->string('billing_first_name')->nullable()->after('phone');
            $table->string('billing_last_name')->nullable()->after('billing_first_name');
            $table->string('billing_address_line1')->nullable()->after('billing_last_name');
            $table->string('billing_address_line2')->nullable()->after('billing_address_line1');
            $table->string('billing_suburb')->nullable()->after('billing_address_line2');
            $table->string('billing_state', 100)->nullable()->after('billing_suburb');
            $table->string('billing_postcode', 20)->nullable()->after('billing_state');
            $table->string('billing_country', 2)->nullable()->after('billing_postcode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'billing_first_name', 'billing_last_name',
                'billing_address_line1', 'billing_address_line2',
                'billing_suburb', 'billing_state', 'billing_postcode', 'billing_country',
            ]);
        });
    }
};
