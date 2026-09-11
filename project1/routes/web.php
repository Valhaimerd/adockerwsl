<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app', ['project' => config('project')]);

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');

        return response()->json([
            'status' => 'ok',
            'service' => config('project.id'),
            'database' => 'connected',
        ]);
    } catch (Throwable) {
        return response()->json([
            'status' => 'error',
            'service' => config('project.id'),
            'database' => 'unavailable',
        ], 503);
    }
});
