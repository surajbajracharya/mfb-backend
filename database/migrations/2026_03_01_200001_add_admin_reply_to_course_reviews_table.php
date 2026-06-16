<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('course_reviews', function (Blueprint $table) {
            $table->text('admin_reply')->nullable()->after('is_approved');
            $table->timestamp('admin_reply_at')->nullable()->after('admin_reply');
        });
    }
    public function down(): void {
        Schema::table('course_reviews', function (Blueprint $table) {
            $table->dropColumn(['admin_reply', 'admin_reply_at']);
        });
    }
};
