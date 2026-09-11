<?php

namespace App\Http\Controllers;

use App\Services\SchoolOverviewService;
use Illuminate\Http\JsonResponse;

class SchoolOverviewController extends Controller
{
    public function __invoke(SchoolOverviewService $overview): JsonResponse
    {
        return response()->json(['sections' => $overview->all()]);
    }
}
