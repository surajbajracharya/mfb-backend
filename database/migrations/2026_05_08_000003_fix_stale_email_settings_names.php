<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $emailSettings = DB::table('email_settings')->get();

        foreach ($emailSettings as $row) {
            // Resolve the correct name for this company from the settings table
            $siteName = DB::table('settings')
                ->where('company_id', $row->company_id)
                ->where('key', 'site_name')
                ->value('value');

            if (!$siteName) {
                $siteName = DB::table('settings')
                    ->where('company_id', $row->company_id)
                    ->where('key', 'company_name')
                    ->value('value');
            }

            if (!$siteName) continue; // no site_name configured yet — leave as-is

            $updates = [];

            // Only overwrite if still the old hardcoded default
            if ($row->site_name === 'Meditation for Beginners') {
                $updates['site_name'] = $siteName;
            }
            if ($row->from_name === 'Meditation for Beginners') {
                $updates['from_name'] = $siteName;
            }

            if (!empty($updates)) {
                $updates['updated_at'] = now();
                DB::table('email_settings')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void {}
};
