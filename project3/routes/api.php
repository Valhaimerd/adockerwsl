<?php

use App\Http\Controllers\CourseController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/courses', CourseController::class)->only(['index', 'store', 'update', 'destroy']);
