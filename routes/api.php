<?php

use App\Http\Controllers\SchoolController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\countryController;
use App\Http\Controllers\serviceFunctionController;
use App\Http\Controllers\studentController;

Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::post('/admin/register', [AuthController::class, 'adminRegister']);

Route::post('/student/login', [AuthController::class, 'studentLogin']);
Route::post('/student/register', [AuthController::class, 'studentRegister']);

Route::post('/school/login', [AuthController::class, 'schoolLogin']);
Route::post('/school/register', [AuthController::class, 'schoolRegister']);

Route::post('/register', [AuthController::class, 'register']);
Route::get('/countryCode', [countryController::class, 'countryCode']);

Route::prefix('student')->group(function () {
    Route::post('/hpFeaturedSchoolList', [studentController::class, 'hpFeaturedSchoolList']);
    Route::post('/hpFeaturedCoursesList', [studentController::class, 'hpFeaturedCoursesList']);
    Route::post('/schoolList', [studentController::class, 'schoolList']);
    Route::post('/categoryList', [studentController::class, 'categoryList']);
    Route::post('/courseList', [studentController::class, 'courseList']);
    Route::post('/courseDetail', [AdminController::class, 'courseDetail']);
    Route::post('/schoolDetail', [AdminController::class, 'schoolDetail']);
    Route::get('/countryList', [studentController::class, 'countryList']);
    Route::get('/instituteType', [studentController::class, 'instituteType']);
    Route::get('/qualificationFilterList', [studentController::class, 'qualificationFilterList']);
    Route::get('/studyModeFilterlist', [studentController::class, 'studyModeFilterlist']);
    Route::post('/locationFilterList', [studentController::class, 'locationFilterList']);
    Route::get('/categoryFilterList', [studentController::class, 'categoryFilterList']);
    Route::get('/tuitionFeeFilterRange', [studentController::class, 'tuitionFeeFilterRange']);


    Route::middleware('auth:sanctum')->post('/studentDetail', [studentController::class, 'studentDetail']);
    Route::middleware('auth:sanctum')->post('/editDetail', [studentController::class, 'editStudent']);
    Route::middleware('auth:sanctum')->post('/updateProfilePic', [studentController::class, 'updateProfilePic']);
    Route::middleware('auth:sanctum')->post('/subjectList', [studentController::class, 'subjectList']);
    Route::middleware('auth:sanctum')->post('/addEditTranscript', [studentController::class, 'addEditTranscript']);
    Route::middleware('auth:sanctum')->post('/addEditHigherTranscript', [studentController::class, 'addEditHigherTranscript']);
    Route::middleware('auth:sanctum')->post('/applyCourse', [studentController::class, 'applyCourse']);
    Route::middleware('auth:sanctum')->get('/pendingAppList', [studentController::class, 'pendingAppList']);
    Route::middleware('auth:sanctum')->get('/historyAppList', [studentController::class, 'historyAppList']);
});

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::post('/adminList', [AdminController::class, 'adminList']);
    Route::post('/studentList', [AdminController::class, 'studentList']);
    Route::post('/editStudent', [AdminController::class, 'editStudent']);
    Route::post('/editStatus', [AdminController::class, 'editStudentStatus']);

    Route::post('/schoolList', [AdminController::class, 'schoolList']);
    Route::post('/addSchool', [AdminController::class, 'addSchool']);
    Route::post('/editSchool', [AdminController::class, 'editSchool']);
    Route::post('/editSchoolStatus', [AdminController::class, 'editSchoolStatus']);
    Route::post('/schoolDetail', [AdminController::class, 'schoolDetail']);
    Route::post('/editSchoolFeatured', [AdminController::class, 'editSchoolFeatured']);

    Route::post('/courseList', [AdminController::class, 'coursesList']);
    Route::post('/courseDetail', [AdminController::class, 'courseDetail']);
    Route::post('/addCourses', [AdminController::class, 'addCourse']);
    Route::post('/editCourse', [AdminController::class, 'editCourse']);
    Route::post('/editCourseStatus', [AdminController::class, 'editCourseStatus']);
    Route::post('/editCoursesFeatured', [AdminController::class, 'editCoursesFeatured']);
    Route::post('/courseTag', [AdminController::class, 'courseTag']);

    Route::post('/addTag', [AdminController::class, 'addTag']);
    Route::post('/searchTag', [AdminController::class, 'searchTag']);

    Route::post('/categoryList', [AdminController::class, 'categoryList']);
    Route::post('/addCategory', [AdminController::class, 'addCategory']);
    Route::post('/addCategory', [AdminController::class, 'addCategory']);
    Route::post('/editCategory', [AdminController::class, 'editCategory']);
    Route::post('/editHotPick', [AdminController::class, 'editHotPick']);
    Route::post('/editCategoryStatus', [AdminController::class, 'editCategoryStatus']);

    Route::post('/addSubject', [AdminController::class, 'addSubject']);
    Route::post('/editSubject', [AdminController::class, 'editSubject']);
    Route::post('/editSubjectStatus', [AdminController::class, 'editSubjectStatus']);
    Route::post('/subjectList', [AdminController::class, 'subjectList']);
});

Route::prefix('school')->middleware('auth:sanctum')->group(function () {
    Route::post('/courseList', [SchoolController::class, 'coursesList']);
    Route::post('/addCourses', [SchoolController::class, 'addCourse']);
    Route::post('/editCourses', [SchoolController::class, 'editCourse']);
    Route::post('/editCourseStatus', [SchoolController::class, 'editCourseStatus']);
    Route::post('/editSchool', [SchoolController::class, 'editSchoolDetail']);
    Route::post('/resetSchoolPassword', [SchoolController::class, 'resetSchoolPassword']);
});

Route::post('/sendOtp', [serviceFunctionController::class, 'sendingOtp']);
Route::post('/validateOtp', [serviceFunctionController::class, 'validateOtp']);

Route::middleware('auth:sanctum')->get('/test', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/test5', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/test6', [AdminController::class, 'studentList']);



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
