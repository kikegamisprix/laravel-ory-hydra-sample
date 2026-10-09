<?php

use App\Http\Controllers\Hydra\ConsentController;
use App\Http\Controllers\Hydra\LoginController;
use App\Http\Controllers\Hydra\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'index']);
Route::post('/logout', [SessionController::class, 'logout']);

// Hydra の urls.login / urls.consent に対応する
Route::get('/login', [LoginController::class, 'show']);
Route::post('/login', [LoginController::class, 'submit'])->middleware('throttle:5,1'); // 1 分に 5 回まで
Route::get('/consent', [ConsentController::class, 'show']);
Route::post('/consent', [ConsentController::class, 'submit']);
