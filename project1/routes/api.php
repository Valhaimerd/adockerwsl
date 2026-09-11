<?php

use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/students', StudentController::class)->only(['index', 'store', 'update', 'destroy']);
