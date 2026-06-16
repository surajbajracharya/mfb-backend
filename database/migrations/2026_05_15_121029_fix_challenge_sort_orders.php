<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $challenges = \App\Models\Challenge::orderByDesc('id')->get();
        foreach ($challenges as $i => $challenge) {
            $challenge->update(['sort_order' => $i]);
        }
    }

    public function down(): void {}
};
