<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_plan_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active, cancelled, expired
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plan_subscriptions');
    }
};
