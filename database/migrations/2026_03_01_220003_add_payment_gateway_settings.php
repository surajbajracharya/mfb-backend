<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore([
            // ── Stripe ───────────────────────────────────────────────────────
            ['key' => 'pg_stripe_enabled',       'value' => '1',  'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_stripe_key',            'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_stripe_secret',         'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_stripe_webhook_secret', 'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_stripe_mode',           'value' => 'test', 'created_at' => $now, 'updated_at' => $now],

            // ── PayPal ────────────────────────────────────────────────────────
            ['key' => 'pg_paypal_enabled',        'value' => '0',  'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_paypal_client_id',      'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_paypal_client_secret',  'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_paypal_mode',           'value' => 'sandbox', 'created_at' => $now, 'updated_at' => $now],

            // ── eSewa ─────────────────────────────────────────────────────────
            ['key' => 'pg_esewa_enabled',         'value' => '0',  'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_esewa_merchant_code',   'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_esewa_secret_key',      'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_esewa_mode',            'value' => 'test', 'created_at' => $now, 'updated_at' => $now],

            // ── Khalti ────────────────────────────────────────────────────────
            ['key' => 'pg_khalti_enabled',        'value' => '0',  'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_khalti_public_key',     'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_khalti_secret_key',     'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_khalti_mode',           'value' => 'test', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'pg_stripe_enabled','pg_stripe_key','pg_stripe_secret','pg_stripe_webhook_secret','pg_stripe_mode',
            'pg_paypal_enabled','pg_paypal_client_id','pg_paypal_client_secret','pg_paypal_mode',
            'pg_esewa_enabled','pg_esewa_merchant_code','pg_esewa_secret_key','pg_esewa_mode',
            'pg_khalti_enabled','pg_khalti_public_key','pg_khalti_secret_key','pg_khalti_mode',
        ])->delete();
    }
};
