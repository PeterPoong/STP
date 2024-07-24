<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\studentController;

Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::post('/admin/register', [AuthController::class, 'adminRegister']);

Route::post('/student/login', [AuthController::class, 'studentLogin']);
Route::post('/student/register', [AuthController::class, 'studentRegister']);

Route::post('/school/login', [AuthController::class, 'schoolLogin']);
Route::post('/school/register', [AuthController::class, 'schoolRegister']);

Route::post('/register', [AuthController::class, 'register']);

Route::prefix('student')->group(function () {
    Route::post('/schoolList', [studentController::class, 'schoolList']);
});

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // Route::post('test', [AdminController::class, 'editStudent']);
    Route::post('/studentList', [AdminController::class, 'studentList']);
    Route::post('/editStudent', [AdminController::class, 'editStudent']);
    Route::post('/editStatus', [AdminController::class, 'editStudentStatus']);

    Route::post('/schoolList', [AdminController::class, 'schoolList']);
    Route::post('/addSchool', [AdminController::class, 'addSchool']);
    Route::post('/editSchool', [AdminController::class, 'editSchool']);
    Route::post('/editSchoolStatus', [AdminController::class, 'editSchoolStatus']);
    Route::post('/schoolDetail', [AdminController::class, 'schoolDetail']);

    Route::post('/editSchoolFeatured', [AdminController::class, 'editSchoolFeatured']);
});

Route::prefix('school')->group(function () {
});

Route::middleware('auth:sanctum')->get('/test', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/test5', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/test6', [AdminController::class, 'studentList']);



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
