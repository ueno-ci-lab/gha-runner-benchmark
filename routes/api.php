<?php

use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('tasks/summary', [TaskController::class, 'summary'])->name('tasks.summary');
Route::apiResource('tasks', TaskController::class)->whereNumber('task');
