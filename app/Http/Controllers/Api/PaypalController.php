<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Event;
use App\Models\AppointmentType;
use App\Models\Plan;
use App\Models\Resource;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalController extends Controller
{
    private function credentials(): array
    {
        return [
            'client_id' => Setting::getValue('pg_paypal_client_id') ?: config('services.paypal.client_id', ''),
            'secret'    => Setting::getValue('pg_paypal_client_secret') ?: config('services.paypal.secret', ''),
            'mode'      => Setting::getValue('pg_paypal_mode') ?: 'sandbox',
        ];
    }

    private function baseUrl(string $mode): string
    {
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function getAccessToken(string $clientId, string $secret, string $mode): string
    {
        $res = Http::withBasicAuth($clientId, $secret)
            ->asForm()
            ->post($this->baseUrl($mode) . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (!$res->successful()) {
            abort(503, 'PayPal authentication failed. Check your PayPal credentials in Admin → Settings → Payment Gateways.');
        }

        return $res->json()['access_token'];
    }

    // ── Create PayPal Order ───────────────────────────────────────────────────
    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.type'         => ['required', 'in:course,event,appointment,appointment_type,resource,plan'],
            'items.*.id'           => ['required', 'integer'],
            'items.*.quantity'     => ['sometimes', 'integer', 'min:1'],
            'items.*.scheduled_at' => ['sometimes', 'nullable', 'date'],
            'items.*.consent_data' => ['sometimes', 'nullable', 'array'],
            'items.*.consented_at' => ['sometimes', 'nullable', 'date'],
        ]);

        ['client_id' => $clientId, 'secret' => $secret, 'mode' => $mode] = $this->credentials();

        if (empty($clientId) || empty($secret)) {
            return response()->json(['message' => 'PayPal is not configured. Please add your PayPal credentials in Admin → Settings → Payment Gateways.'], 503);
        }

        $user       = $request->user();
        $orderItems = [];

        foreach ($request->items as $item) {
            $qty = $item['quantity'] ?? 1;

            if ($item['type'] === 'course') {
                $model = Course::findOrFail($item['id']);
                if ($model->type === 'free' || (float) $model->price === 0.0) {
                    return response()->json(['message' => "'{$model->title}' is a free course — use the Enroll for Free button instead."], 422);
                }
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->title, 'price' => $model->price];
            } elseif ($item['type'] === 'event') {
                $model = Event::findOrFail($item['id']);
                if ($model->isSoldOut()) {
                    return response()->json(['message' => "Event '{$model->title}' is sold out."], 422);
                }
                $orderItems[] = ['model' => $model, 'qty' => $qty, 'name' => $model->title, 'price' => $model->price];
            } elseif ($item['type'] === 'resource') {
                $model = Resource::findOrFail($item['id']);
                if ($model->is_free) {
                    return response()->json(['message' => "'{$model->title}' is a free resource."], 422);
                }
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->title, 'price' => $model->price];
            } elseif (in_array($item['type'], ['appointment', 'appointment_type'])) {
                $model = AppointmentType::findOrFail($item['id']);
                $meta  = array_filter([
                    'scheduled_at' => $item['scheduled_at'] ?? null,
                    'consent_data' => $item['consent_data'] ?? null,
                    'consented_at' => $item['consented_at'] ?? null,
                ], fn ($v) => $v !== null);
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->title, 'price' => $model->price, 'meta' => $meta ?: null];
            } elseif ($item['type'] === 'plan') {
                $model = Plan::withoutCompanyScope()->findOrFail($item['id']);
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->name, 'price' => $model->price];
            }
        }

        $subtotal = collect($orderItems)->sum(fn ($i) => $i['price'] * $i['qty']);

        // Create pending order in DB first
        $order = Order::create([
            'order_number'   => 'ORD-' . strtoupper(uniqid()),
            'user_id'        => $user->id,
            'subtotal'       => $subtotal,
            'tax'            => 0,
            'total'          => $subtotal,
            'status'         => 'pending',
            'payment_method' => 'paypal',
        ]);

        foreach ($orderItems as $item) {
            $order->items()->create([
                'purchasable_type' => get_class($item['model']),
                'purchasable_id'   => $item['model']->id,
                'name'             => $item['name'],
                'price'            => $item['price'],
                'quantity'         => $item['qty'],
                'subtotal'         => $item['price'] * $item['qty'],
                'meta'             => $item['meta'] ?? null,
            ]);
        }

        // Create PayPal order
        $token    = $this->getAccessToken($clientId, $secret, $mode);
        $frontend = config('app.frontend_url');

        $ppItems = collect($orderItems)->map(fn ($i) => [
            'name'       => $i['name'],
            'quantity'   => (string) $i['qty'],
            'unit_amount' => [
                'currency_code' => 'USD',
                'value'         => number_format((float) $i['price'], 2, '.', ''),
            ],
        ])->values()->toArray();

        $res = Http::withToken($token)
            ->post($this->baseUrl($mode) . '/v2/checkout/orders', [
                'intent'          => 'CAPTURE',
                'purchase_units'  => [[
                    'reference_id'  => (string) $order->id,
                    'description'   => 'Order #' . $order->order_number,
                    'items'         => $ppItems,
                    'amount'        => [
                        'currency_code' => 'USD',
                        'value'         => number_format($subtotal, 2, '.', ''),
                        'breakdown'     => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value'         => number_format($subtotal, 2, '.', ''),
                            ],
                        ],
                    ],
                ]],
                'application_context' => [
                    'return_url'          => $frontend . '/checkout/paypal/capture?order_id=' . $order->id,
                    'cancel_url'          => $frontend . '/checkout/cancel',
                    'brand_name'          => config('app.name'),
                    'user_action'         => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                ],
            ]);

        if (!$res->successful()) {
            $order->delete();
            Log::error('PayPal create order failed', ['response' => $res->json()]);
            return response()->json(['message' => 'Failed to create PayPal order. Please try again.'], 502);
        }

        $ppOrder   = $res->json();
        $approveUrl = collect($ppOrder['links'])->firstWhere('rel', 'approve')['href'] ?? null;

        if (!$approveUrl) {
            $order->delete();
            return response()->json(['message' => 'No PayPal approval URL returned.'], 502);
        }

        $order->update(['paypal_order_id' => $ppOrder['id']]);

        return response()->json([
            'url'         => $approveUrl,
            'checkout_url' => $approveUrl,
            'order_id'    => $order->id,
            'paypal_order_id' => $ppOrder['id'],
        ]);
    }

    // ── Capture PayPal Payment ────────────────────────────────────────────────
    public function captureOrder(Request $request): JsonResponse
    {
        $request->validate([
            'paypal_order_id' => ['required', 'string'],
            'order_id'        => ['required', 'integer'],
        ]);

        $order = Order::withoutCompanyScope()->findOrFail($request->order_id);

        if ($order->status === 'paid') {
            return response()->json(['message' => 'Payment already confirmed.', 'order' => $order]);
        }

        ['client_id' => $clientId, 'secret' => $secret, 'mode' => $mode] = $this->credentials();
        $token = $this->getAccessToken($clientId, $secret, $mode);

        $res = Http::withToken($token)
            ->withBody('{}', 'application/json')
            ->post($this->baseUrl($mode) . "/v2/checkout/orders/{$request->paypal_order_id}/capture");

        if (!$res->successful()) {
            Log::error('PayPal capture failed', ['response' => $res->json()]);
            return response()->json(['message' => 'PayPal capture failed. Please contact support.'], 502);
        }

        $captured = $res->json();

        if (($captured['status'] ?? '') !== 'COMPLETED') {
            return response()->json(['message' => 'Payment not completed.'], 402);
        }

        $capture    = $captured['purchase_units'][0]['payments']['captures'][0] ?? [];
        $paymentInfo = [
            'paypal_capture_id'   => $capture['id'] ?? null,
            'paypal_payer_email'  => $captured['payer']['email_address'] ?? null,
            'paypal_payer_id'     => $captured['payer']['payer_id'] ?? null,
        ];

        $this->fulfillOrder($order, $paymentInfo);

        return response()->json(['message' => 'Payment confirmed.', 'order' => $order->fresh()]);
    }

    // ── PayPal IPN / Webhook ──────────────────────────────────────────────────
    public function webhook(Request $request): \Illuminate\Http\Response
    {
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('PayPal webhook rejected: invalid signature');
            return response('Invalid signature.', 400);
        }

        $eventType = $request->input('event_type');
        Log::info('PayPal webhook', ['event_type' => $eventType]);

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource    = $request->input('resource') ?? [];
            $referenceId = $resource['custom_id']
                ?? ($resource['purchase_units'][0]['reference_id'] ?? null);

            if ($referenceId) {
                $order = Order::withoutCompanyScope()->find((int) $referenceId);
                if ($order && $order->status !== 'paid') {
                    $paymentInfo = [
                        'paypal_order_id'    => $resource['supplementary_data']['related_ids']['order_id'] ?? ($resource['id'] ?? null),
                        'paypal_capture_id'  => $resource['id'] ?? null,
                        'paypal_payer_email' => $resource['payer']['email_address'] ?? null,
                        'paypal_payer_id'    => $resource['payer']['payer_id'] ?? null,
                    ];
                    $this->fulfillOrder($order, array_filter($paymentInfo));
                }
            }
        }

        return response('OK', 200);
    }

    // ── Verify PayPal webhook signature ──────────────────────────────────────
    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = Setting::getValue('pg_paypal_webhook_id') ?: config('services.paypal.webhook_id', '');

        if (empty($webhookId)) {
            Log::warning('PayPal webhook received but pg_paypal_webhook_id is not configured — rejecting.');
            return false;
        }

        ['client_id' => $clientId, 'secret' => $secret, 'mode' => $mode] = $this->credentials();

        if (empty($clientId) || empty($secret)) {
            return false;
        }

        try {
            $token = $this->getAccessToken($clientId, $secret, $mode);
        } catch (\Throwable) {
            return false;
        }

        $res = Http::withToken($token)
            ->post($this->baseUrl($mode) . '/v1/notifications/verify-webhook-signature', [
                'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url'          => $request->header('PAYPAL-CERT-URL'),
                'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id'        => $webhookId,
                'webhook_event'     => $request->json()->all(),
            ]);

        if (!$res->successful()) {
            Log::error('PayPal webhook signature check failed', ['response' => $res->json()]);
            return false;
        }

        return ($res->json('verification_status') === 'SUCCESS');
    }

    // ── Shared fulfillment logic ──────────────────────────────────────────────
    private function fulfillOrder(Order $order, array $paymentInfo = []): void
    {
        if ($order->company_id) {
            $company = \App\Models\Company::find($order->company_id);
            app(\App\Services\TenantContext::class)->setCompany($company);
        }

        $order->update(array_merge([
            'status'  => 'paid',
            'paid_at' => now(),
        ], $paymentInfo));

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            app(\App\Http\Controllers\Api\WebhookController::class)->fulfillItem($item, $order->user_id);
        }

        $order->loadMissing('user');
        if ($order->user) {
            $frontendUrl = config('app.frontend_url', url('/'));
            \App\Services\EmailService::send($order->user->email, 'order_confirmed', [
                '{username}'      => $order->user->name,
                '{email}'         => $order->user->email,
                '{order_id}'      => (string) $order->id,
                '{order_total}'   => '$' . number_format($order->total, 2),
                '{order_date}'    => now()->format('F j, Y'),
                '{dashboard_url}' => $frontendUrl . '/dashboard',
            ]);
        }
    }
}
