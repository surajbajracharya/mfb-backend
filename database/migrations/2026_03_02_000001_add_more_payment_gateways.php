<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore([
            // ── Afterpay (Australia / NZ / US / UK – BNPL) ───────────────────
            ['key' => 'pg_afterpay_enabled',        'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_afterpay_merchant_id',    'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_afterpay_secret_key',     'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_afterpay_mode',           'value' => 'sandbox', 'created_at' => $now, 'updated_at' => $now],

            // ── Zip / Zip Pay (Australia / NZ / US – BNPL) ───────────────────
            ['key' => 'pg_zip_enabled',             'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_zip_public_key',          'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_zip_private_key',         'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_zip_mode',                'value' => 'sandbox', 'created_at' => $now, 'updated_at' => $now],

            // ── eWAY (Australia / NZ) ─────────────────────────────────────────
            ['key' => 'pg_eway_enabled',            'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_eway_api_key',            'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_eway_api_password',       'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_eway_mode',               'value' => 'sandbox', 'created_at' => $now, 'updated_at' => $now],

            // ── Square (Global / AU / US / UK / CA / JP) ─────────────────────
            ['key' => 'pg_square_enabled',          'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_square_app_id',           'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_square_access_token',     'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_square_location_id',      'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_square_mode',             'value' => 'sandbox', 'created_at' => $now, 'updated_at' => $now],

            // ── Razorpay (India) ──────────────────────────────────────────────
            ['key' => 'pg_razorpay_enabled',        'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_razorpay_key_id',         'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_razorpay_key_secret',     'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_razorpay_mode',           'value' => 'test',    'created_at' => $now, 'updated_at' => $now],

            // ── Mollie (Europe / Global) ──────────────────────────────────────
            ['key' => 'pg_mollie_enabled',          'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_mollie_api_key',          'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_mollie_mode',             'value' => 'test',    'created_at' => $now, 'updated_at' => $now],

            // ── Paytm (India) ─────────────────────────────────────────────────
            ['key' => 'pg_paytm_enabled',           'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_paytm_merchant_id',       'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_paytm_merchant_key',      'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_paytm_mode',              'value' => 'test',    'created_at' => $now, 'updated_at' => $now],

            // ── FonePay (Nepal) ───────────────────────────────────────────────
            ['key' => 'pg_fonepay_enabled',         'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_fonepay_merchant_code',   'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_fonepay_secret_key',      'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_fonepay_mode',            'value' => 'test',    'created_at' => $now, 'updated_at' => $now],

            // ── ConnectIPS (Nepal) ────────────────────────────────────────────
            ['key' => 'pg_connectips_enabled',      'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_connectips_merchant_id',  'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_connectips_app_id',       'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_connectips_password',     'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_connectips_mode',         'value' => 'test',    'created_at' => $now, 'updated_at' => $now],

            // ── Flutterwave (Africa / Global) ─────────────────────────────────
            ['key' => 'pg_flutterwave_enabled',     'value' => '0',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_flutterwave_public_key',  'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_flutterwave_secret_key',  'value' => null,      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pg_flutterwave_mode',        'value' => 'test',    'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'pg_afterpay_enabled','pg_afterpay_merchant_id','pg_afterpay_secret_key','pg_afterpay_mode',
            'pg_zip_enabled','pg_zip_public_key','pg_zip_private_key','pg_zip_mode',
            'pg_eway_enabled','pg_eway_api_key','pg_eway_api_password','pg_eway_mode',
            'pg_square_enabled','pg_square_app_id','pg_square_access_token','pg_square_location_id','pg_square_mode',
            'pg_razorpay_enabled','pg_razorpay_key_id','pg_razorpay_key_secret','pg_razorpay_mode',
            'pg_mollie_enabled','pg_mollie_api_key','pg_mollie_mode',
            'pg_paytm_enabled','pg_paytm_merchant_id','pg_paytm_merchant_key','pg_paytm_mode',
            'pg_fonepay_enabled','pg_fonepay_merchant_code','pg_fonepay_secret_key','pg_fonepay_mode',
            'pg_connectips_enabled','pg_connectips_merchant_id','pg_connectips_app_id','pg_connectips_password','pg_connectips_mode',
            'pg_flutterwave_enabled','pg_flutterwave_public_key','pg_flutterwave_secret_key','pg_flutterwave_mode',
        ])->delete();
    }
};
