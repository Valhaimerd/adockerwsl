<?php

use App\Http\Controllers\FacultyController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/faculty', FacultyController::class)->only(['index', 'store', 'update', 'destroy']);
