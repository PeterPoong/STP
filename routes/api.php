<?php

use App\Http\Controllers\SchoolController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\countryController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\serviceFunctionController;
use App\Http\Controllers\studentController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\EnquiryController;
use GuzzleHttp\Client;


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

    Route::post('/courseDetail', [studentController::class, 'courseDetail']);

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

    Route::post('/listingFilterList', [studentController::class, 'listingFilterList']);


    Route::get('/hotPickCategoryList', [studentController::class, 'hotPickCategoryList']);
    Route::get('/enquirySubjectList', [EnquiryController::class, 'subjectList']);
    Route::post('/createEnquiry', [EnquiryController::class, 'createEnquiry']);
    Route::post('/enquiryList', [EnquiryController::class, 'enquiryList']);
    Route::post('/enquiryListAdmin', [EnquiryController::class, 'enquiryListAdmin']);
    Route::post('/enquiryDetail', [EnquiryController::class, 'enquiryDetail']);

    Route::post('/advertisementList', [studentController::class, 'advertisementList']);




    //student portal
    Route::middleware('auth:sanctum')->post('/checkTermsAgreement', [studentController::class, 'checkTermsAgreement']);
    Route::middleware('auth:sanctum')->post('/agreeTerms', [studentController::class, 'agreeTerms']);
    Route::middleware('auth:sanctum')->post('/studentDetail', [studentController::class, 'studentDetail']);
    Route::middleware('auth:sanctum')->post('/editStudentDetail', [studentController::class, 'editStudent']);
    Route::middleware('auth:sanctum')->post('/updateProfilePic', [studentController::class, 'updateProfilePic']);
    Route::middleware('auth:sanctum')->post('/subjectList', [studentController::class, 'subjectList']);
    Route::middleware('auth:sanctum')->post('/addEditTranscript', [studentController::class, 'addEditTranscript']);
    Route::middleware('auth:sanctum')->post('/addEditHigherTranscript', [studentController::class, 'addEditHigherTranscript']);
    Route::middleware('auth:sanctum')->post('/applyCourse', [studentController::class, 'applyCourse']);

    //interested course
    Route::middleware('auth:sanctum')->post('/addInterestedCourse', [studentController::class, 'addInterestedCourse']);
    Route::middleware('auth:sanctum')->post('/removeInterestedCourse', [studentController::class, 'removeInterestedCourse']);
    Route::middleware('auth:sanctum')->get('/interestedCourseList', [studentController::class, 'interestedCourseList']);



    //cgpa
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

    Route::middleware('auth:sanctum')->post('/resetTranscript', [studentController::class, 'resetTranscript']);
    Route::middleware('auth:sanctum')->post('/applyCourseTranscript', [studentController::class, 'applyCourseTranscript']);

    Route::middleware('auth:sanctum')->get('/personalityQuestionList', [studentController::class, 'personalityQuestionList']);
    Route::middleware('auth:sanctum')->post('/submitTestResult', [studentController::class, 'submitTestResult']);
    Route::middleware('auth:sanctum')->get('/getTestResult', [studentController::class, 'getTestResult']);
    Route::middleware('auth:sanctum')->post('/riasecCourseCategory', [studentController::class, 'riasecCourseCategory']);
});

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::post('/adminList', [AdminController::class, 'adminList']);
    Route::post('/studentList', [AdminController::class, 'studentList']);
    Route::post('/studentListAdmin', [AdminController::class, 'studentListAdmin']);
    Route::post('/addStudent', [AdminController::class, 'addStudent']);
    Route::post('/studentDetail', [AdminController::class, 'studentDetail']);
    Route::post('/editStudent', [AdminController::class, 'editStudent']);
    Route::post('/editStatus', [AdminController::class, 'editStudentStatus']);


    Route::post('/replyEnquiry', [EnquiryController::class, 'replyEnquiry']);

    Route::post('/interestedCourseListAdmin', [AdminController::class, 'interestedCourseListAdmin']);


    Route::get('/cronCorseCategoryInterested', [AdminController::class, 'cronCorseCategoryInterested'])->withoutMiddleware('auth:sanctum');
    Route::post('/adminCourseCategoryInterested', [AdminController::class, 'adminCourseCategoryInterested'])->withoutMiddleware('auth:sanctum');


    Route::post('/schoolList', [AdminController::class, 'schoolList']);
    Route::get('/schoolListAdmin', [AdminController::class, 'schoolListAdmin']);
    Route::post('/addSchool', [AdminController::class, 'addSchool']);
    Route::post('/editSchool', [AdminController::class, 'editSchool']);
    Route::post('/editSchoolStatus', [AdminController::class, 'editSchoolStatus']);
    Route::post('/schoolDetail', [AdminController::class, 'schoolDetail']);
    Route::post('/editSchoolFeatured', [AdminController::class, 'editSchoolFeatured']);
    Route::post('/instituteCategoryList', [AdminController::class, 'instituteCategoryList']);
    Route::post('/accountTypeList', [AdminController::class, 'accountTypeList']);
    Route::post('/removeSchoolPhoto', [AdminController::class, 'removeSchoolPhoto']);

    Route::post('/courseList', [AdminController::class, 'coursesList']);
    Route::post('/courseListAdmin', [AdminController::class, 'courseListAdmin']);
    Route::post('/courseDetail', [AdminController::class, 'courseDetail']);
    Route::post('/addCourses', [AdminController::class, 'addCourse']);
    Route::post('/editCourse', [AdminController::class, 'editCourse']);
    Route::post('/editCourseStatus', [AdminController::class, 'editCourseStatus']);
    Route::post('/editCoursesFeatured', [AdminController::class, 'editCoursesFeatured']);
    Route::post('/courseFeaturedList', [AdminController::class, 'courseFeaturedList']);
    Route::post('/allFeaturedList', [AdminController::class, 'allFeaturedList']);
    Route::post('/universityFeaturedList', [AdminController::class, 'universityFeaturedList']);
    Route::post('/intakeList', [AdminController::class, 'intakeList']);
    Route::post('/courseTag', [AdminController::class, 'courseTag']);
    Route::post('/courseDetailApplicant', [AdminController::class, 'courseDetailApplicant']);
    Route::post('/courseListFeatured', [AdminController::class, 'courseListFeatured']);

    Route::post('/addTag', [AdminController::class, 'addTag']);
    Route::post('/searchTag', [AdminController::class, 'searchTag']);

    Route::post('/categoryList', [AdminController::class, 'categoryList']);
    Route::post('/addCategory', [AdminController::class, 'addCategory']);
    Route::post('/addCategory', [AdminController::class, 'addCategory']);
    Route::post('/editCategory', [AdminController::class, 'editCategory']);
    Route::post('/editHotPick', [AdminController::class, 'editHotPick']);
    Route::post('/editCategoryStatus', [AdminController::class, 'editCategoryStatus']);
    Route::post('/categoryListAdmin', [AdminController::class, 'categoryListAdmin']);
    Route::post('/categoryDetail', [AdminController::class, 'categoryDetail']);

    Route::post('/addSubject', [AdminController::class, 'addSubject']);
    Route::post('/editSubject', [AdminController::class, 'editSubject']);
    Route::post('/editSubjectStatus', [AdminController::class, 'editSubjectStatus']);
    Route::post('/subjectList', [AdminController::class, 'subjectList']);
    Route::post('/subjectListAdmin', [AdminController::class, 'subjectListAdmin']);
    Route::post('/transcriptCategoryList', [AdminController::class, 'transcriptCategoryList']);
    Route::post('/subjectDetail', [AdminController::class, 'subjectDetail']);


    Route::post('/applicantDetailInfo', [AdminController::class, 'applicantDetailInfo']);
    Route::post('/editApplicantStatus', [AdminController::class, 'editApplicantStatus']);
    Route::post('/editApplicantForm', [AdminController::class, 'editApplicantForm']);
    Route::post('/applicantDetail', [AdminController::class, 'applicantDetail']);

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
    Route::post('/bannerDetail', [AdminController::class, 'bannerDetail']);

    Route::post('/resetAdminDummyPassword', [AdminController::class, 'resetAdminDummyPassword']);
    Route::post('/resetAdminPassword', [AdminController::class, 'resetAdminPassword']);

    Route::post('/dataList', [AdminController::class, 'dataList']);
    Route::get("/dataFilterList", [AdminController::class, 'dataFilterList']);
    Route::post("/addDataList", [AdminController::class, 'addDataList']);
    Route::post("/editData", [AdminController::class, 'editData']);
    Route::post("/editDataStatus", [AdminController::class, 'editDataStatus']);

    //request featured
    // Route::post('/featuredSchoolRequestList', [AdminController::class, 'featuredSchoolRequestList']);
    // Route::post('/featuredCourseRequestList', [AdminController::class, 'featuredCourseRequestList']);
    Route::post('/schoolFeaturedSchoolCourseRequestList', [AdminController::class, 'schoolFeaturedSchoolCourseRequestList']);
    // Route::post('/featuredRequestDetail', [AdminController::class, 'featuredRequestDetail']);
    Route::post('/updateRequestFeatured', [AdminController::class, 'updateRequestFeatured']);
    Route::post('/adminApplyFeaturedCourseRequest', [AdminController::class, 'adminApplyFeaturedCourseRequest']);
    Route::post('/adminApplyFeaturedSchoolRequest', [AdminController::class, 'adminApplyFeaturedSchoolRequest']);
    Route::post('/adminFeaturedCourseAvailable', [AdminController::class, 'adminFeaturedCourseAvailable']);
    Route::post('/featuredRequestList', [AdminController::class, 'featuredRequestList']);
    Route::post('/addNewCourse', [AdminController::class, 'addNewCourse']);

    Route::post('/adminFeaturedCourseList', [AdminController::class, 'adminFeaturedCourseList']);
    //update featured
    Route::post('/editFeaturedCourse', [AdminController::class, 'editFeaturedCourse']);
    Route::post('/editFeaturedSchool', [AdminController::class, 'editFeaturedSchool']);
    Route::post('/editRequest', [AdminController::class, 'editRequest']);
    Route::post('/adminFeaturedTypeListRequest', [AdminController::class, 'adminFeaturedTypeListRequest']);

    //personality test
    Route::get('/riasecTypesList', [AdminController::class, 'riasecTypesList']);
    Route::post('/addRiasecTypes', [AdminController::class, 'addRiasecTypes']);
    Route::post('/updateRiasecTypes', [AdminController::class, 'updateRiasecTypes']);
   
    //personality question 
    Route::post('/addPersonalQuestion', [AdminController::class, 'addPersonalQuestion']);
    Route::post('/updatePersonalQuestion', [AdminController::class, 'updatePersonalQuestion']);
    Route::post('/questionDetail', [AdminController::class, 'questionDetail']);
    Route::post('/personalityQuestionList', [AdminController::class, 'personalityQuestionList']);
    Route::post('/riasecDetail', [AdminController::class, 'riasecDetail']);
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
    Route::post('/schoolApplicantList', [SchoolController::class, 'schoolApplicantList']);

    Route::post('/updateSchoolLogo', [SchoolController::class, 'updateSchoolLogo']);
    Route::post('/resetSchoolPassword', [SchoolController::class, 'resetSchoolPassword']);
    Route::post('/resetDummySchoolPassword', [SchoolController::class, 'resetDummySchoolPassword']);

    //country statistic
    Route::post('/countryStatisticPieChart', [SchoolController::class, 'countryStatistic']);
    Route::post('/countryStatisticBarGraph', [SchoolController::class, 'countryStatisticBarGraph']);
    //program statistic
    Route::post('/programStatisticPieChart', [SchoolController::class, 'programStatisticPieChart']);
    Route::post('/programStatisticBarChart', [SchoolController::class, 'programStatisticBarChart']);
    //gender statistic
    Route::post('/genderStatisticPieChart', [SchoolController::class, 'genderStatisticPieChart']);
    Route::post('/genderStatisticBarChart', [SchoolController::class, 'genderStatisticBarChart']);
    //qualification statistic
    Route::post('/qualificationStatisticPieChart', [SchoolController::class, 'qualificationStatisticPieChart']);
    Route::post('/qualificationStatisticBarChart', [SchoolController::class, 'qualificationStatisticBarChart']);

    //applicant 
    Route::post('/applicantDetail', [SchoolController::class, 'applicantDetail']);
    Route::post('/studentDetail', [SchoolController::class, 'studentDetail']);
    Route::post('/courseDetail', [SchoolController::class, 'courseDetail']);

    Route::post('/schoolApplicantCocurriculum', [SchoolController::class, 'schoolApplicantCocurriculum']);
    Route::post('/schoolAchievementsList', [SchoolController::class, 'schoolAchievementsList']);
    Route::post('/schoolOtherFileCertList', [SchoolController::class, 'schoolOtherFileCertList']);
    Route::post('/schoolTranscriptCategoryList', [SchoolController::class, 'schoolTranscriptCategoryList']);
    Route::post('/schoolStudentTranscriptSubjectList', [SchoolController::class, 'schoolStudentTranscriptSubjectList']);
    Route::post('/schoolHigherTranscriptSubjectList', [SchoolController::class, 'schoolHigherTranscriptSubjectList']);
    Route::post('/schoolTranscriptDocumentList', [SchoolController::class, 'schoolTranscriptDocumentList']);
    Route::post('/schoolTranscriptCgpa', [SchoolController::class, 'schoolTranscriptCgpa']);
    Route::post('/getNumberOfDocument', [SchoolController::class, 'getNumberOfDocument']);

    //location 
    Route::post('/getLocation', [SchoolController::class, 'getLocation']);

    //request features
    Route::post('requestCoursesFeatured', [SchoolController::class, 'requestCoursesFeatured']);
    Route::post('requestFeaturedSchool', [SchoolController::class, 'requestFeaturedSchool']);
    Route::post('applyFeaturedCourse', [SchoolController::class, 'applyFeaturedCourse']);

    Route::post('courseRequestFeaturedList', [SchoolController::class, 'courseRequestFeaturedList']);
    Route::post('schoolRequestFeaturedList', [SchoolController::class, 'schoolRequestFeaturedList']);
    Route::post('schoolFeaturedRequestLists', [SchoolController::class, 'schoolFeaturedRequestLists']);


    Route::post('featuredCourseAvailable', [SchoolController::class, 'featuredCourseAvailable']);
    Route::post('editFeaturedCourseSetting', [SchoolController::class, 'editFeaturedCourseSetting']);
    Route::post('editSchoolFeaturedSetting', [SchoolController::class, 'editSchoolFeaturedSetting']);
    Route::get('schoolFeaturedType', [SchoolController::class, 'schoolFeaturedType']);
    Route::post('schoolFeaturedPriceList', [SchoolController::class, 'schoolFeaturedPriceList']);

    Route::get('testFeaturedRequest', [SchoolController::class, 'testFeaturedRequest']);
});

// Route::get('auth/facebook', [LoginController::class, 'redirectToFacebook'])->name('login.facebook');
//social login
Route::group(['middleware' => ['web']], function () {
    Route::get('auth/facebook', [SocialLoginController::class, 'redirectToFacebook'])->name('login.facebook');
    Route::get('auth/facebook/callback', [SocialLoginController::class, 'handleFacebookCallback']);
    Route::get('auth/google', [SocialLoginController::class, 'googlePage']);
    Route::get('auth/google/callback', [SocialLoginController::class, 'googleCallback']);
});
Route::post('/decrypt-data', [SocialLoginController::class, 'decryptData']);
Route::post('/facebook/deleteFacebookData', [SocialLoginController::class, 'deleteFacebookData']);
Route::post('/social/updateContact', [SocialLoginController::class, 'updateContact']);




//marketing 
Route::prefix('marketing')->group(function () {
    Route::get('/packageList', [MarketingController::class, 'packageList']);
});

Route::post('/importCountry', [serviceFunctionController::class, 'importCountry']);
Route::post('/importState', [serviceFunctionController::class, 'importState']);
Route::post('/importCity', [serviceFunctionController::class, 'importCity']);

Route::post('/getState', [serviceFunctionController::class, 'getState']);
Route::post('/getCities', [serviceFunctionController::class, 'getCities']);
Route::get('/getMonth', [serviceFunctionController::class, 'getMonth']);
Route::post('/getIframe', [serviceFunctionController::class, 'getIframe']);
Route::post('/getMapEmbed', [serviceFunctionController::class, 'getMapEmbed']);
Route::get('/updateGoogleMapLocation', [serviceFunctionController::class, 'updateGoogleMapLocation']);



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
