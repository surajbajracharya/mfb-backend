<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Course;
use App\Models\Order;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Appointment;
use App\Models\Resource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = [
            "total_users"          => User::count(),
            "total_revenue"        => (float) Order::where("status", "paid")->sum("total"),
            "total_courses"        => Course::count(),
            "total_enrollments"    => Enrollment::count(),
            "total_events"         => Event::count(),
            "total_appointments"   => Appointment::count(),
            "total_resources"      => Resource::count(),
            "active_plans"         => Plan::where("is_active", true)->count(),
            "pending_appointments" => Appointment::where("status", "pending")->count(),
            "revenue_this_month"   => (float) Order::where("status", "paid")
                ->whereMonth("created_at", now()->month)
                ->whereYear("created_at", now()->year)
                ->sum("total"),
            "new_users_this_month" => User::whereMonth("created_at", now()->month)
                ->whereYear("created_at", now()->year)
                ->count(),
        ];

        // Last 12 months — revenue + new users + enrollments per month
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        $revenueByMonth = Order::where("status", "paid")
            ->where("created_at", ">=", now()->subMonths(11)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw("SUM(total) as total"))
            ->groupBy("month")
            ->pluck("total", "month");

        $usersByMonth = User::where("created_at", ">=", now()->subMonths(11)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw("COUNT(*) as total"))
            ->groupBy("month")
            ->pluck("total", "month");

        $enrollmentsByMonth = Enrollment::where("created_at", ">=", now()->subMonths(11)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw("COUNT(*) as total"))
            ->groupBy("month")
            ->pluck("total", "month");

        $chartData = $months->map(fn ($m) => [
            "month"       => $m->format("M"),
            "revenue"     => round((float) ($revenueByMonth[$m->format("Y-m")] ?? 0) / 100, 2),
            "users"       => (int) ($usersByMonth[$m->format("Y-m")] ?? 0),
            "enrollments" => (int) ($enrollmentsByMonth[$m->format("Y-m")] ?? 0),
        ])->values();

        // Appointment status breakdown
        $apptStatus = Appointment::select("status", DB::raw("COUNT(*) as count"))
            ->groupBy("status")
            ->pluck("count", "status");

        $recentOrders = Order::with("user:id,name,email")
            ->latest()
            ->take(10)
            ->get();

        $recentUsers = User::with("roles")
            ->latest()
            ->take(5)
            ->get(["id", "name", "email", "avatar", "created_at"]);

        $recentAppointments = Appointment::with(["user:id,name,email,avatar", "type:id,title,images"])
            ->latest("scheduled_at")
            ->take(5)
            ->get();

        return response()->json([
            "stats"               => $stats,
            "chart_data"          => $chartData,
            "appt_status"         => $apptStatus,
            "recent_orders"       => $recentOrders,
            "recent_users"        => $recentUsers,
            "recent_appointments" => $recentAppointments,
        ]);
    }

    public function stats(): JsonResponse
    {
        return $this->index();
    }
}
