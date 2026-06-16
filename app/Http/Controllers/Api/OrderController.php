<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->orders()->with([
                "items.purchasable",
                "items.appointment:id,order_item_id,status,scheduled_at,timezone,consent_pdf_path,consented_at",
            ])->latest()->paginate(50)
        );
    }
    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $request->user()->orders()->with([
                "items.purchasable",
                "items.appointment:id,order_item_id,status,scheduled_at,timezone,consent_pdf_path,consented_at",
            ])->findOrFail($id)
        );
    }
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Order::with(["user:id,name,email,avatar", "items", "company:id,name"]);

        if ($request->status)         { $query->where("status", $request->status); }
        if ($request->payment_method) { $query->where("payment_method", $request->payment_method); }
        if ($request->from_date)      { $query->whereDate("created_at", ">=", $request->from_date); }
        if ($request->to_date)        { $query->whereDate("created_at", "<=", $request->to_date); }
        if ($request->min_total)      { $query->where("total", ">=", $request->min_total); }
        if ($request->max_total)      { $query->where("total", "<=", $request->max_total); }
        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where("order_number", "like", "%{$s}%")
                  ->orWhereHas("user", fn ($u) => $u->where("name", "like", "%{$s}%")
                                                     ->orWhere("email", "like", "%{$s}%"));
            });
        }

        return response()->json($query->latest()->paginate(25));
    }
    public function adminShow(string $id): JsonResponse
    {
        $order = Order::with([
            "user:id,name,email,created_at,avatar",
            "items.purchasable",
            "items.appointment:id,order_item_id,user_id,appointment_type_id,status,scheduled_at,timezone,duration_minutes,meeting_link,notes,cancellation_reason,cancelled_at,consent_pdf_path,consented_at",
            "items.eventTicket:id,order_item_id,status,ticket_code,quantity",
        ])->findOrFail($id);
        return response()->json($order);
    }
    public function refund(string $id): JsonResponse
    {
        $order = Order::with(['user', 'items'])->findOrFail($id);
        if (!in_array($order->status, ["paid", "cancelled"])) {
            return response()->json(["message" => "Only paid or cancelled orders can be refunded."], 422);
        }
        $order->update(["status" => "refunded"]);
        $order->items()->update(['is_refunded' => true, 'refunded_at' => now()]);

        if ($order->user) {
            $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
            EmailService::send($order->user->email, 'order_refunded', [
                '{username}'      => $order->user->name,
                '{email}'         => $order->user->email,
                '{order_id}'      => (string) $order->id,
                '{order_total}'   => '$' . number_format($order->total / 100, 2),
                '{dashboard_url}' => $frontendUrl . '/dashboard',
                '{site_name}'     => AppModelsSetting::getValue('site_name', config('app.name')),
            ]);
        }

        return response()->json(["message" => "Order refunded.", "order" => $order]);
    }

    public function cancel(string $id): JsonResponse
    {
        $order = Order::with(['items', 'user'])->findOrFail($id);
        if (!in_array($order->status, ["paid", "pending"])) {
            return response()->json(["message" => "Only paid or pending orders can be cancelled."], 422);
        }
        $order->update(["status" => "cancelled"]);

        // Cancel any linked appointments so their time slots are freed
        foreach ($order->items as $item) {
            if ($item->purchasable_type === \App\Models\AppointmentType::class) {
                \App\Models\Appointment::where('order_item_id', $item->id)
                    ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
                    ->update(['status' => 'cancelled']);
            }
        }

        if ($order->user) {
            $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
            EmailService::send($order->user->email, 'order_cancelled', [
                '{username}'      => $order->user->name,
                '{email}'         => $order->user->email,
                '{order_id}'      => (string) $order->id,
                '{dashboard_url}' => $frontendUrl . '/dashboard',
                '{site_name}'     => AppModelsSetting::getValue('site_name', config('app.name')),
            ]);
        }

        return response()->json(["message" => "Order cancelled.", "order" => $order]);
    }
    public function markPaid(string $id): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);
        if ($order->status === 'paid') {
            return response()->json(["message" => "Order is already paid."], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $order->loadMissing('items');
            foreach ($order->items as $item) {
                app(\App\Http\Controllers\Api\WebhookController::class)->fulfillItem($item, $order->user_id);
            }
        });

        return response()->json(["message" => "Order marked as paid.", "order" => $order]);
    }

    public function flag(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        // Mark with a failed status and store flag note in the order
        $order->update(["status" => "failed"]);
        return response()->json(["message" => "Order flagged as spam.", "order" => $order]);
    }
}