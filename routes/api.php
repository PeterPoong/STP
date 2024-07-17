<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->get('/test', function (Request $request) {
    return 'test';
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
