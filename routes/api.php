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
    Route::post('/schoolDetail', [studentController::class, 'schoolDetail']);
    Route::post('/categoryList', [studentController::class, 'categoryList']);
    Route::post('/courseList', [studentController::class, 'courseList']);
    Route::post('/courseDetail', [AdminController::class, 'courseDetail']);
    Route::get('/genderList', [studentController::class, 'genderList']);

    Route::post('/schoolDetail', [studentController::class, 'schoolDetail']);
    Route::get('/countryList', [studentController::class, 'countryList']);
    Route::get('/instituteType', [studentController::class, 'instituteType']);
    Route::get('/qualificationFilterList', [studentController::class, 'qualificationFilterList']);
    Route::get('/studyModeFilterlist', [studentController::class, 'studyModeFilterlist']);
    Route::post('/locationFilterList', [studentController::class, 'locationFilterList']);
    Route::get('/categoryFilterList', [studentController::class, 'categoryFilterList']);
    Route::get('/tuitionFeeFilterRange', [studentController::class, 'tuitionFeeFilterRange']);
    Route::get('/intakeFilterList', [studentController::class, 'intakeFilterList']);
    Route::post('/featuredInstituteList', [studentController::class, 'featuredInstituteList']);
    Route::post('/featuredCourseList', [studentController::class, 'featuredCourseList']);

    Route::get('/hotPickCategoryList', [studentController::class, 'hotPickCategoryList']);


    //student portal
    Route::middleware('auth:sanctum')->post('/studentDetail', [studentController::class, 'studentDetail']);
    Route::middleware('auth:sanctum')->post('/editStudentDetail', [studentController::class, 'editStudent']);
    Route::middleware('auth:sanctum')->post('/updateProfilePic', [studentController::class, 'updateProfilePic']);
    Route::middleware('auth:sanctum')->post('/subjectList', [studentController::class, 'subjectList']);
    Route::middleware('auth:sanctum')->post('/addEditTranscript', [studentController::class, 'addEditTranscript']);
    Route::middleware('auth:sanctum')->post('/addEditHigherTranscript', [studentController::class, 'addEditHigherTranscript']);
    Route::middleware('auth:sanctum')->post('/applyCourse', [studentController::class, 'applyCourse']);

    Route::middleware('auth:sanctum')->post('/addProgramCgpa', [studentController::class, 'addProgramCgpa']);
    Route::middleware('auth:sanctum')->post('/editProgramCgpa', [studentController::class, 'editProgramCgpa']);
    Route::middleware('auth:sanctum')->post('/programCgpaList', [studentController::class, 'programCgpaList']);


    Route::middleware('auth:sanctum')->get('/pendingAppList', [studentController::class, 'pendingAppList']);
    Route::middleware('auth:sanctum')->post('/withdrawApplicant', [studentController::class, 'withdrawApplicant']);

    Route::middleware('auth:sanctum')->get('/historyAppList', [studentController::class, 'historyAppList']);
    Route::middleware('auth:sanctum')->get('/courseCategoryList', [studentController::class, 'courseCategoryList']);

    //achievement
    Route::middleware('auth:sanctum')->post('/addAchievement', [studentController::class, 'addAchievement']);
    Route::middleware('auth:sanctum')->post('/editAchievement', [studentController::class, 'editAchievement']);
    Route::middleware('auth:sanctum')->post('/deleteAchievement', [studentController::class, 'deleteAchievement']);
    Route::middleware('auth:sanctum')->post('/achievementsList', [studentController::class, 'achievementsList']);

    Route::middleware('auth:sanctum')->post('/sendReminder', [studentController::class, 'sendReminder']);

    //transcript
    Route::middleware('auth:sanctum')->post('/transcriptCategoryList', [studentController::class, 'transcriptCategoryList']);
    Route::middleware('auth:sanctum')->post('/subjectListByCategory', [studentController::class, 'subjectListByCategory']);
    Route::middleware('auth:sanctum')->post('/mediaListByCategory', [studentController::class, 'mediaListByCategory']);
    Route::middleware('auth:sanctum')->post('/addTranscriptFile', [studentController::class, 'addTranscriptFile']);
    Route::middleware('auth:sanctum')->post('/editTranscriptFile', [studentController::class, 'editTranscriptFile']);
    Route::middleware('auth:sanctum')->post('/deleteTranscriptFile', [studentController::class, 'deleteTranscriptFile']);
    Route::middleware('auth:sanctum')->get('/achievementTypeList', [studentController::class, 'achievementTypeList']);
    Route::middleware('auth:sanctum')->get('/transcriptSubjectList', [studentController::class, 'transcriptSubjectList']);
    Route::middleware('auth:sanctum')->post('/higherTranscriptSubjectList', [studentController::class, 'higherTranscriptSubjectList']);


    //other cert
    Route::middleware('auth:sanctum')->post('/addOtherCertFile', [studentController::class, 'addOtherCertFile']);
    Route::middleware('auth:sanctum')->post('/editOtherCertFile', [studentController::class, 'editOtherCertFile']);
    Route::middleware('auth:sanctum')->post('/deleteOtherCertFile', [studentController::class, 'deleteOtherCertFile']);
    Route::middleware('auth:sanctum')->post('/otherFileCertList', [studentController::class, 'otherFileCertList']);

    //reset password
    Route::middleware('auth:sanctum')->post('/resetStudentPassword', [studentController::class, 'resetStudentPassword']);
    Route::middleware('auth:sanctum',)->post('/resetDummyAccountPassword', [studentController::class, 'resetDummyAccountPassword']);


    //co-curriculum
    Route::middleware('auth:sanctum')->post('/addCocurriculumList', [studentController::class, 'addCocurriculumList']);
    Route::middleware('auth:sanctum')->post('/editCocurriculum', [studentController::class, 'editCocurriculum']);
    Route::middleware('auth:sanctum')->post('/disableCocurriculum', [studentController::class, 'disableCocurriculum']);

    Route::middleware('auth:sanctum')->get('/co-curriculumList', [studentController::class, 'cocurriculumList']);
});

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::post('/adminList', [AdminController::class, 'adminList']);
    Route::post('/studentList', [AdminController::class, 'studentList']);
    Route::post('/studentListAdmin', [AdminController::class, 'studentListAdmin']);
    Route::post('/addStudent', [AdminController::class, 'addStudent']);
    Route::post('/editStudent', [AdminController::class, 'editStudent']);
    Route::post('/editStatus', [AdminController::class, 'editStudentStatus']);

    Route::post('/schoolList', [AdminController::class, 'schoolList']);
    Route::post('/addSchool', [AdminController::class, 'addSchool']);
    Route::post('/editSchool', [AdminController::class, 'editSchool']);
    Route::post('/editSchoolStatus', [AdminController::class, 'editSchoolStatus']);
    Route::post('/schoolDetail', [AdminController::class, 'schoolDetail']);
    Route::post('/editSchoolFeatured', [AdminController::class, 'editSchoolFeatured']);
    Route::post('/instituteCategoryList', [AdminController::class, 'instituteCategoryList']);
    Route::post('/accountTypeList', [AdminController::class, 'accountTypeList']);

    Route::post('/courseList', [AdminController::class, 'coursesList']);
    Route::post('/courseListAdmin', [AdminController::class, 'courseListAdmin']);
    Route::post('/courseDetail', [AdminController::class, 'courseDetail']);
    Route::post('/addCourses', [AdminController::class, 'addCourse']);
    Route::post('/editCourse', [AdminController::class, 'editCourse']);
    Route::post('/editCourseStatus', [AdminController::class, 'editCourseStatus']);
    Route::post('/editCoursesFeatured', [AdminController::class, 'editCoursesFeatured']);
    Route::post('/courseFeaturedList', [AdminController::class, 'courseFeaturedList']);
    Route::post('/universityFeaturedList', [AdminController::class, 'universityFeaturedList']);
    Route::post('/courseTag', [AdminController::class, 'courseTag']);

    Route::post('/addTag', [AdminController::class, 'addTag']);
    Route::post('/searchTag', [AdminController::class, 'searchTag']);

    Route::post('/categoryList', [AdminController::class, 'categoryList']);
    Route::post('/addCategory', [AdminController::class, 'addCategory']);
    Route::post('/addCategory', [AdminController::class, 'addCategory']);
    Route::post('/editCategory', [AdminController::class, 'editCategory']);
    Route::post('/editHotPick', [AdminController::class, 'editHotPick']);
    Route::post('/editCategoryStatus', [AdminController::class, 'editCategoryStatus']);
    Route::post('/categoryListAdmin', [AdminController::class, 'categoryListAdmin']);

    Route::post('/addSubject', [AdminController::class, 'addSubject']);
    Route::post('/editSubject', [AdminController::class, 'editSubject']);
    Route::post('/editSubjectStatus', [AdminController::class, 'editSubjectStatus']);
    Route::post('/subjectList', [AdminController::class, 'subjectList']);
    Route::post('/subjectListAdmin', [AdminController::class, 'subjectListAdmin']);

    Route::post('/applicantDetailInfo', [AdminController::class, 'applicantDetailInfo']);
    Route::post('/editApplicantStatus', [AdminController::class, 'editApplicantStatus']);
    Route::post('/editApplicantForm', [AdminController::class, 'editApplicantForm']);

    Route::post('/addPackage', [AdminController::class, 'addPackage']);
    Route::post('/editPackage', [AdminController::class, 'editPackage']);
    Route::post('/deletePackage', [AdminController::class, 'deletePackage']);
    Route::post('/packageList', [AdminController::class, 'packageList']);
    Route::post('/packageTypeList', [AdminController::class, 'packageTypeList']);

    Route::post('/addAdmin', [AdminController::class, 'addAdmin']);
    Route::post('/disableAdmin', [AdminController::class, 'disableAdmin']);
    Route::post('/adminList', [AdminController::class, 'adminList']);
    Route::post('/adminListAdmin', [AdminController::class, 'adminListAdmin']);
    Route::post('/editAdmin', [AdminController::class, 'editAdmin']);

    Route::post('/addBanner', [AdminController::class, 'addBanner']);
    Route::post('/editBanner', [AdminController::class, 'editBanner']);
    Route::post('/disableBanner', [AdminController::class, 'disableBanner']);
    Route::post('/bannerListAdmin', [AdminController::class, 'bannerListAdmin']);
    Route::post('/bannerFeaturedList', [AdminController::class, 'bannerFeaturedList']);

    Route::post('/resetAdminDummyPassword', [AdminController::class, 'resetAdminDummyPassword']);
    Route::post('/resetAdminPassword', [AdminController::class, 'resetAdminPassword']);

    Route::post('/dataList', [AdminController::class, 'dataList']);
    Route::get("/dataFilterList", [AdminController::class, 'dataFilterList']);
    Route::post("/addDataList", [AdminController::class, 'addDataList']);
    Route::post("/editData", [AdminController::class, 'editData']);
    Route::post("/editDataStatus", [AdminController::class, 'editDataStatus']);
});

Route::prefix('school')->middleware('auth:sanctum')->group(function () {
    Route::get('/schoolDetail', [SchoolController::class, 'schoolDetail']);
    Route::post('/courseList', [SchoolController::class, 'coursesList']);
    Route::post('/addCourses', [SchoolController::class, 'addCourse']);
    Route::post('/courseDetail', [SchoolController::class, 'courseDetail']);
    Route::post('/editCourses', [SchoolController::class, 'editCourse']);
    Route::post('/editCourseStatus', [SchoolController::class, 'editCourseStatus']);

    Route::post('/editSchool', [SchoolController::class, 'editSchoolDetail']);
    Route::post('/editPersonInCharge', [SchoolController::class, 'editPersonInCharge']);
    Route::post('/applicantDetailInfo', [SchoolController::class, 'applicantDetailInfo']);
    Route::post('/applicantDetailCocurriculum', [SchoolController::class, 'applicantDetailCocurriculum']);
    Route::post('/applicantDetailAchievement', [SchoolController::class, 'applicantDetailAchievement']);
    Route::post('/applicantDetailAcademic', [SchoolController::class, 'applicantDetailAcademic']);
    Route::post('/applicantResultSlip', [SchoolController::class, 'applicantResultSlip']);
    Route::post('/editApplicantStatus', [SchoolController::class, 'editApplicantStatus']);
    Route::post('/applicantDetailRelatedDocument', [SchoolController::class, 'applicantDetailRelatedDocument']);

    Route::get('/instituteType', [studentController::class, 'instituteType']);
    Route::get('/countryList', [studentController::class, 'countryList']);

    //cover
    Route::post('/updateSchoolCover', [SchoolController::class, 'updateSchoolCover']);
    Route::get('/getSchoolCover', [SchoolController::class, 'getSchoolCover']);
    Route::get('/disableSchoolCover', [SchoolController::class, 'disableSchoolCover']);

    //photo
    Route::post('/uploadSchoolPhoto', [SchoolController::class, 'uploadSchoolPhoto']);
    Route::get('/getSchoolPhoto', [SchoolController::class, 'getSchoolPhoto']);
    Route::post('/deleteSchoolPhoto', [SchoolController::class, 'removeSchoolPhoto']);

    //applicant filter
    Route::get('/dropDownCourseList', [SchoolController::class, 'filterCourseList']);
    Route::get('/schoolApplicantList', [SchoolController::class, 'schoolApplicantList']);

    Route::post('/updateSchoolLogo', [SchoolController::class, 'updateSchoolLogo']);
    Route::post('/resetSchoolPassword', [SchoolController::class, 'resetSchoolPassword']);
    Route::post('/resetDummySchoolPassword', [SchoolController::class, 'resetDummySchoolPassword']);
});


Route::post('/importCountry', [serviceFunctionController::class, 'importCountry']);
Route::post('/importState', [serviceFunctionController::class, 'importState']);
Route::post('/importCity', [serviceFunctionController::class, 'importCity']);

Route::post('/getState', [serviceFunctionController::class, 'getState']);
Route::post('/getCities', [serviceFunctionController::class, 'getCities']);
Route::get('/getMonth', [serviceFunctionController::class, 'getMonth']);



Route::post('/sendOtp', [serviceFunctionController::class, 'sendingOtp']);
Route::post('/validateOtp', [serviceFunctionController::class, 'validateOtp']);
Route::post('/resetPassword', [serviceFunctionController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->get('validateToken', function () {
    return response()->json([
        'success' => true,
        'message' => 'Token is valid'
    ]);
});




Route::middleware('auth:sanctum')->get('/test', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/test5', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/test6', [AdminController::class, 'studentList']);




Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
