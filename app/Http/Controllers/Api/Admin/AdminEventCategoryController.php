<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\EventCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEventCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => EventCategory::orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        return response()->json(EventCategory::create($data), 201);
    }

    public function destroy(string $id): JsonResponse
    {
        EventCategory::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
