<?php

namespace App\Http\Controllers;

use App\Services\ProjectStatusService;
use Illuminate\Http\JsonResponse;

class ProjectStatusController extends Controller
{
    public function __invoke(ProjectStatusService $statuses): JsonResponse
    {
        return response()->json(['projects' => $statuses->all()]);
    }
}
