<?php

use Illuminate\Support\Facades\Route;

// 動作確認用の画面（データは画面内の JavaScript から /api/tasks を呼び出して取得する）
Route::view('/', 'tasks')->name('tasks.page');
