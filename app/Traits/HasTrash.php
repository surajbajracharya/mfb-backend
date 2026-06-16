<?php
namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Add to any controller that manages a soft-deletable model.
 * The controller must define: protected string $model = ModelClass::class;
 * Optional: protected array $trashedWith = []; (eager-load relations for trash list)
 */
trait HasTrash
{
    public function trashed(Request $request): JsonResponse
    {
        $query = $this->model::onlyTrashed();
        if (!empty($this->trashedWith)) {
            $query->with($this->trashedWith);
        }
        return response()->json(['data' => $query->orderByDesc('deleted_at')->get()]);
    }

    public function restore(string $id): JsonResponse
    {
        $record = $this->model::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json($record);
    }

    public function forceDelete(string $id): JsonResponse
    {
        $this->model::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Permanently deleted.']);
    }

    public function emptyTrash(): JsonResponse
    {
        $this->model::onlyTrashed()->forceDelete();
        return response()->json(['message' => 'Trash emptied.']);
    }
}
