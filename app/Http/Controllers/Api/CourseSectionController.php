<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseSectionController extends Controller
{
    public function store(Request $request, string $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order'       => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['course_id'] = $course->id;

        if (!isset($data['order'])) {
            $data['order'] = $course->sections()->max('order') + 1;
        }

        $section = CourseSection::create($data);

        return response()->json($section->load('lectures'), 201);
    }

    public function update(Request $request, string $courseId, string $id): JsonResponse
    {
        $section = CourseSection::where('course_id', $courseId)->findOrFail($id);

        $section->update($request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order'       => ['sometimes', 'integer', 'min:0'],
        ]));

        return response()->json($section);
    }

    public function destroy(string $courseId, string $id): JsonResponse
    {
        CourseSection::where('course_id', $courseId)->findOrFail($id)->delete();
        return response()->json(['message' => 'Section deleted.']);
    }
}
