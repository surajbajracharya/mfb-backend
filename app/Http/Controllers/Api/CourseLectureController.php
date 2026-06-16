<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseSection;
use App\Models\CourseLecture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseLectureController extends Controller
{
    public function store(Request $request, string $sectionId): JsonResponse
    {
        $section = CourseSection::findOrFail($sectionId);

        $data = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'type'             => ['required', 'in:video,audio,pdf,text'],
            'content_url'      => ['nullable', 'string'],
            'duration_seconds' => ['sometimes', 'integer', 'min:0'],
            'is_preview'       => ['sometimes', 'boolean'],
            'resources'        => ['sometimes', 'array'],
            'order'            => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['section_id'] = $section->id;

        if (!isset($data['order'])) {
            $data['order'] = $section->lectures()->max('order') + 1;
        }

        $lecture = CourseLecture::create($data);

        return response()->json($lecture, 201);
    }

    public function update(Request $request, string $sectionId, string $id): JsonResponse
    {
        $lecture = CourseLecture::where('section_id', $sectionId)->findOrFail($id);

        $lecture->update($request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'type'             => ['sometimes', 'in:video,audio,pdf,text'],
            'content_url'      => ['nullable', 'string'],
            'duration_seconds' => ['sometimes', 'integer', 'min:0'],
            'is_preview'       => ['sometimes', 'boolean'],
            'resources'        => ['sometimes', 'array'],
            'order'            => ['sometimes', 'integer', 'min:0'],
        ]));

        return response()->json($lecture);
    }

    public function destroy(string $sectionId, string $id): JsonResponse
    {
        CourseLecture::where('section_id', $sectionId)->findOrFail($id)->delete();
        return response()->json(['message' => 'Lecture deleted.']);
    }
}
