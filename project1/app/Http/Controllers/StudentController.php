<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Student::query()->latest('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = Student::create($this->validated($request));

        return response()->json(['data' => $student], 201);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $student->update($this->validated($request, $student));

        return response()->json(['data' => $student->fresh()]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('students')->ignore($student)],
            'program' => ['required', 'string', 'max:100'],
        ]);
    }
}
