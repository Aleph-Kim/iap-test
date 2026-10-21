<?php

use App\Http\Controllers\AppleIapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/iap/apple/verify', [AppleIapController::class, 'verify'])->middleware('auth:sanctum');
