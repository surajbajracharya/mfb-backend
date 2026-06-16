<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\AppointmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAppointmentCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => AppointmentCategory::orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);
        $category = AppointmentCategory::create($data);
        return response()->json($category, 201);
    }

    public function destroy(string $id): JsonResponse
    {
        AppointmentCategory::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
