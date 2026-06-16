<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('challenge_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('badge_awarded')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'challenge_id']);
            $table->index('challenge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_enrollments');
    }
};
