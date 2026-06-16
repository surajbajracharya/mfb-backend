<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->with(['course:id,title,slug,thumbnail,type'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();

        $upcomingAppointments = $user->appointments()
            ->with('type:id,title,duration_minutes')
            ->where('scheduled_at', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();

        $recentOrders = $user->orders()
            ->with('items')
            ->where('status', 'paid')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'enrollments_count'       => $enrollments->count(),
            'appointments_count'      => $user->appointments()->where('scheduled_at', '>=', now())->where('status', '!=', 'cancelled')->count(),
            'orders_count'            => $user->orders()->count(),
            'recent_enrollments'      => $enrollments->take(5)->values(),
            'upcoming_appointments'   => $upcomingAppointments,
            'recent_orders'           => $recentOrders,
        ]);
    }
}