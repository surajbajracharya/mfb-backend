<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    // Public: list published pages
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Page::where('status', 'published')->latest()->get(),
        ]);
    }

    // Public: single page by slug
    public function show(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->where('status', 'published')->firstOrFail();
        return response()->json($page);
    }

    // Admin: all pages (scoped by X-Company-ID when provided)
    public function adminIndex(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $query = Page::latest();
        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }
        return response()->json(['data' => $query->get()]);
    }

    // Admin: single page by ID
    public function adminShow(string $id): JsonResponse
    {
        return response()->json(Page::findOrFail($id));
    }

    public function trashed(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $query = Page::onlyTrashed()->latest('deleted_at');
        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }
        return response()->json(['data' => $query->get()]);
    }

    public function restore(string $id): JsonResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->restore();
        return response()->json($page);
    }

    public function forceDelete(string $id): JsonResponse
    {
        Page::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Permanently deleted.']);
    }

    public function emptyTrash(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $query = Page::onlyTrashed();
        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }
        $query->forceDelete();
        return response()->json(['message' => 'Bin emptied.']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => ['required', 'string', 'max:255'],
            'template'         => ['sometimes', 'nullable', 'in:left-sidebar,right-sidebar,both-sidebars,narrow,full-width'],
            'status'           => ['sometimes', 'in:draft,published'],
            'content'          => ['nullable', 'string'],
            'sidebar_left'     => ['nullable', 'string'],
            'sidebar_right'    => ['nullable', 'string'],
            'featured_image'   => ['nullable', 'string'],
            'gallery'          => ['nullable', 'array'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],
            'og_image'         => ['nullable', 'string'],
            'canonical_url'    => ['nullable', 'string', 'max:255'],
            'robots'           => ['nullable', 'string', 'max:100'],
            'schema_markup'    => ['nullable', 'string'],
        ]);

        $companyId = $request->header('X-Company-ID');
        if ($companyId) {
            $data['company_id'] = (int) $companyId;
        }

        return response()->json(Page::create($data), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $page = Page::findOrFail($id);

        $data = $request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'slug'             => ['sometimes', 'string', 'max:255'],
            'template'         => ['sometimes', 'nullable', 'in:left-sidebar,right-sidebar,both-sidebars,narrow,full-width'],
            'status'           => ['sometimes', 'in:draft,published'],
            'content'          => ['nullable', 'string'],
            'sidebar_left'     => ['nullable', 'string'],
            'sidebar_right'    => ['nullable', 'string'],
            'featured_image'   => ['nullable', 'string'],
            'gallery'          => ['nullable', 'array'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],
            'og_image'         => ['nullable', 'string'],
            'canonical_url'    => ['nullable', 'string', 'max:255'],
            'robots'           => ['nullable', 'string', 'max:100'],
            'schema_markup'    => ['nullable', 'string'],
        ]);

        $page->update($data);
        return response()->json($page->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        Page::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
