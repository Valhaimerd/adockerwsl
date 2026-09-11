<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacultyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Faculty::query()->latest('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $faculty = Faculty::create($this->validated($request));

        return response()->json(['data' => $faculty], 201);
    }

    public function update(Request $request, Faculty $faculty): JsonResponse
    {
        $faculty->update($this->validated($request, $faculty));

        return response()->json(['data' => $faculty->fresh()]);
    }

    public function destroy(Faculty $faculty): JsonResponse
    {
        $faculty->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Faculty $faculty = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('faculty')->ignore($faculty)],
            'department' => ['required', 'string', 'max:100'],
        ]);
    }
}
