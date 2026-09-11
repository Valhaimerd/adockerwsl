<?php

use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\SchoolOverviewController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::get('/api/projects/status', ProjectStatusController::class);
Route::get('/api/school/overview', SchoolOverviewController::class);

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');

        return response()->json([
            'status' => 'ok',
            'service' => 'unify',
            'database' => 'connected',
        ]);
    } catch (Throwable) {
        return response()->json([
            'status' => 'error',
            'service' => 'unify',
            'database' => 'unavailable',
        ], 503);
    }
});
