<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('avatar');
            $table->string('social_provider')->nullable()->after('bio');
            $table->string('social_id')->nullable()->after('social_provider');
            $table->string('stripe_customer_id')->nullable()->after('social_id');
            $table->boolean('gdpr_consent')->default(false)->after('stripe_customer_id');
            $table->timestamp('gdpr_consent_at')->nullable()->after('gdpr_consent');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar', 'bio', 'social_provider', 'social_id', 'stripe_customer_id', 'gdpr_consent', 'gdpr_consent_at']);
        });
    }
};
