<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('challenge_day_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_day_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();
            $table->unique(['user_id', 'challenge_day_id']);
            $table->index('challenge_day_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_day_progress');
    }
};
