<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Course::query()->latest('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $course = Course::create($this->validated($request));

        return response()->json(['data' => $course], 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $course->update($this->validated($request, $course));

        return response()->json(['data' => $course->fresh()]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('courses')->ignore($course)],
            'title' => ['required', 'string', 'max:150'],
            'instructor' => ['required', 'string', 'max:100'],
        ]);
    }
}
