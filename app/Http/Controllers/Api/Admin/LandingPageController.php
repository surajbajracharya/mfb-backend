<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function adminIndex(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $query = LandingPage::with('company:id,name')->latest();

        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(LandingPage::with('company:id,name')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => ['required', 'string', 'unique:landing_pages,slug'],
            'hero_image'       => ['nullable', 'string'],
            'excerpt'          => ['nullable', 'string'],
            'content'          => ['nullable', 'array'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'og_title'         => ['nullable', 'string', 'max:255'],
            'og_description'   => ['nullable', 'string'],
            'og_image'         => ['nullable', 'string'],
            'canonical_url'    => ['nullable', 'string', 'max:500'],
            'robots'           => ['nullable', 'string', 'max:100'],
            'head_code'        => ['nullable', 'string'],
            'body_start_code'  => ['nullable', 'string'],
            'body_end_code'    => ['nullable', 'string'],
            'button_text'      => ['nullable', 'string', 'max:100'],
            'button_url'       => ['nullable', 'string', 'max:500'],
            'status'           => ['sometimes', 'in:draft,published'],
            'show_header'      => ['sometimes', 'boolean'],
            'show_footer'      => ['sometimes', 'boolean'],
        ]);

        $companyId = $request->header('X-Company-ID');
        if ($companyId) {
            $data['company_id'] = (int) $companyId;
        }

        $page = LandingPage::create($data);

        return response()->json($page, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $data = $request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'slug'             => ['sometimes', 'string', 'unique:landing_pages,slug,' . $id],
            'hero_image'       => ['nullable', 'string'],
            'excerpt'          => ['nullable', 'string'],
            'content'          => ['nullable', 'array'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'og_title'         => ['nullable', 'string', 'max:255'],
            'og_description'   => ['nullable', 'string'],
            'og_image'         => ['nullable', 'string'],
            'canonical_url'    => ['nullable', 'string', 'max:500'],
            'robots'           => ['nullable', 'string', 'max:100'],
            'head_code'        => ['nullable', 'string'],
            'body_start_code'  => ['nullable', 'string'],
            'body_end_code'    => ['nullable', 'string'],
            'button_text'      => ['nullable', 'string', 'max:100'],
            'button_url'       => ['nullable', 'string', 'max:500'],
            'status'           => ['sometimes', 'in:draft,published'],
            'show_header'      => ['sometimes', 'boolean'],
            'show_footer'      => ['sometimes', 'boolean'],
        ]);

        $page->update($data);

        return response()->json($page);
    }

    public function destroy(string $id): JsonResponse
    {
        LandingPage::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function publish(string $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);
        $page->update(['status' => 'published']);

        return response()->json($page);
    }

    public function trashed(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $query = LandingPage::onlyTrashed()->latest('deleted_at');

        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function restore(string $id): JsonResponse
    {
        $page = LandingPage::onlyTrashed()->findOrFail($id);
        $page->restore();

        return response()->json($page);
    }

    public function forceDelete(string $id): JsonResponse
    {
        LandingPage::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['message' => 'Permanently deleted.']);
    }

    public function emptyTrash(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $query = LandingPage::onlyTrashed();

        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }

        $query->forceDelete();

        return response()->json(['message' => 'Bin emptied.']);
    }

    public function publicIndex(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $query = LandingPage::where('status', 'published')->select('slug', 'og_image', 'updated_at');
        if ($companyId) { $query->where('company_id', (int) $companyId); }
        return response()->json(['data' => $query->orderBy('updated_at', 'desc')->get()]);
    }

    public function publicShow(Request $request, string $slug): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $query = LandingPage::where('slug', $slug)->where('status', 'published');

        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json($query->firstOrFail());
    }
}
