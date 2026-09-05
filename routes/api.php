<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        'translations/export',
        [TranslationController::class, 'export']
    );

    Route::apiResource('translations', TranslationController::class);
});