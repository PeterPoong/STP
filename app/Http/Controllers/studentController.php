<?php

namespace App\Http\Controllers;

use App\Models\stp_achievement;
use App\Models\stp_core_meta;
use App\Models\stp_country;
use App\Models\stp_course;
use App\Models\stp_courses_category;
use App\Models\stp_featured;
use App\Models\stp_higher_transcript;
use App\Models\stp_other_certificate;
use App\Models\stp_qualification;
use App\Models\stp_student_media;
use Illuminate\Http\Request;
use App\Models\stp_school;
use App\Models\stp_student;
use App\Models\stp_subject;
use App\Models\stp_tag;
use App\Models\stp_transcript;
use App\Models\stp_submited_form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\serviceFunctionController;
use App\Models\stp_intake;
use Illuminate\Support\Facades\Storage;
// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;

use App\Rules\UniqueInArray;
use Exception;

class studentController extends Controller
{
    protected $serviceFunctionController;

    public function __construct(serviceFunctionController $serviceFunctionController)
    {
        $this->serviceFunctionController = $serviceFunctionController;
    }

    public function schoolList(Request $request)
    {
        try {
            $getSchoolList = stp_school::where('school_status', 1)
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->orWhere('institue_category', $request->category);
                })
                ->when($request->filled('country'), function ($query) use ($request) {
                    $query->orWhere('country_id', $request->country);
                })
                ->when($request->filled('location'), function ($query) use ($request) {
                    $query->orWhere('state_id', $request->location);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('school_name', 'like', '%' . $request->search . '%');
                })
                ->paginate(10);


            foreach ($getSchoolList as $school) {

                $featured = false;
                foreach ($school->featured as $s) {
                    if ($s['featured_type'] == 30 && $s['featured_status'] == 1) {
                        $featured = true;
                        break;
                    }
                }
                $schoolList[] = [
                    'id' => $school->id,
                    'name' => $school->school_name,
                    'category' => $school->institueCategory->core_metaName ?? null,
                    'logo' => $school->school_logo,
                    'featured' => $featured,
                    'country' => $school->country->country_name ?? null,
                    'state' => $school->state->state_name ?? null,
                    'city' => $school->city->city_name ?? null,
                    'description' => $school->school_shortDesc
                ];
            }

            usort($schoolList, function ($a, $b) {
                return $b['featured'] <=> $a['featured'];
            });

            return response()->json([
                'success' => true,
                'data' => $schoolList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Sever Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function hpFeaturedSchoolList(Request $request)
    {
        // $test = stp_featured::find(1);
        // return $test->school;
        try {
            $hpFeaturedSchoolList = stp_featured::where('featured_type', 28)->get()->map(function ($school) {
                return ([
                    'schoolID' => $school->school->id,
                    'schoolName' => $school->school->school_name,
                    'schoolLogo' => $school->school->school_logo
                ]);
            });
            return response()->json([
                'success' => true,
                'data' => $hpFeaturedSchoolList
            ]);
            // return $hpFeaturedSchoolList;
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ]);
        }
    }

    public function hpFeaturedCoursesList(Request $request)
    {
        try {
            // $test = stp_featured::find(10);
            // return $test->courses->qualification;
            // return stp_featured::whereNotNull('course_id')->get();

            $hpFeaturedCoursesList = stp_featured::whereNotNull('course_id')
                ->where('featured_type', 29)
                ->where('featured_status', 1)
                ->get()->map(function ($courses) {
                    if (empty($courses->courses->course_logo)) {
                        $logo = $courses->courses->school->school_logo;
                    } else {
                        $logo = $courses->courses->course_logo;
                    }
                    return [
                        "course_name" => $courses->courses->course_name,
                        "course_logo" => $logo,
                        "course_qualification" => $courses->courses->qualification->qualification_name,
                        'course_school' => $courses->courses->school->school_name,
                        'location' => $courses->courses->school->city->city_name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $hpFeaturedCoursesList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
                "errors" => $e->getMessage()
            ], 500);
        }
    }

    public function categoryList(Request $request)
    {
        try {
            $categroyList = stp_courses_category::where('category_status', 1)->get();
            return response()->json([
                'success' => true,
                'data' => $categroyList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function courseList(Request $request)
    {
        // $test = stp_course::find(1)->intake;
        // return $test[0]->month;
        try {
            $request->validate([
                'search' => 'string',
                'country' => 'integer',
                'qualification' => 'integer',
                'location' => 'array',
                'category' => 'array',
                'institute' => 'integer',
                'studyMode' => 'array',
                'tuitionFee' => 'numeric',
                'intake' => 'array'
            ]);

            $getCourses = stp_course::when($request->filled('qualification'), function ($query) use ($request) {
                $query->where('qualification_id', $request->qualification);
            })
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->whereIn('category_id', $request->category);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('course_name', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('country'), function ($query) use ($request) {
                    $query->whereHas('school', function ($query) use ($request) {
                        $query->where('country_id', $request->country);
                    });
                })
                ->when($request->filled('institute'), function ($query) use ($request) {
                    $query->whereHas('school', function ($query) use ($request) {
                        $query->where('institue_category', $request->institute);
                    });
                })
                ->when($request->filled('studyMode'), function ($query) use ($request) {
                    $query->whereIn('study_mode', $request->studyMode);
                })
                ->when($request->filled('location'), function ($query) use ($request) {
                    $query->whereHas('school', function ($query) use ($request) {
                        $query->whereIn('state_id', $request->location);
                    });
                })
                ->when($request->filled('tuitionFee'), function ($query) use ($request) {
                    $query->where('course_cost', '<=', $request->tuitionFee);
                })
                ->when($request->filled('intake'), function ($query) use ($request) {
                    $query->whereHas('intake', function ($query) use ($request) {
                        $query->whereIn('intake_month', $request->intake);
                    });
                })
                ->paginate(10)
                ->through(function ($course) {
                    $featured = false;
                    foreach ($course->featured as $c) {

                        if ($c['featured_type'] == 30 && $c['featured_status'] == 1) {
                            $featured = true;
                            break;
                        }
                    };
                    $intakeMonths = $course->intake->pluck('month.core_metaName')->toArray();
                    return [
                        'id' => $course->id,
                        'school_id' => $course->school->school_name,
                        'name' => $course->course_name,
                        'description' => $course->course_description,
                        'requirement' => $course->course_requirement,
                        'cost' => $course->course_cost,
                        'featured' => $featured,
                        'period' => $course->course_period,
                        'intake' => $intakeMonths,
                        'category' => $course->category->category_name,
                        'qualification' => $course->qualification->qualification_name,
                        'mode' => $course->studyMode->core_metaName ?? null,
                        'logo' => $course->course_logo ?? $course->school->school_logo,
                        'location' => $course->school->state->state_name
                    ];
                });


            $sortedCourses = $getCourses->sortByDesc('featured')->values();
            $paginatedCourses = new \Illuminate\Pagination\LengthAwarePaginator(
                $sortedCourses->forPage($getCourses->currentPage(), $getCourses->perPage()), // Slice the collection for the current page
                $sortedCourses->count(),
                $getCourses->perPage(),
                $getCourses->currentPage(),
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'data' => $paginatedCourses
            ]);

            return response()->json([
                'success' => true,
                'data' => $coursesList
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function studentDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $student = stp_student::find($request->id);
            // return $student->detail;

            $stduentDetail = [
                'id' => $student->id,
                'username' => $student->student_userName,
                'firstName' => $student->detail->student_detailFirstName,
                'lastName' => $student->detail->student_detailLastName,
                'ic' => $student->student_icNumber,
                'email' => $student->student_email,
                'contact' => $student->student_countryCode . $student->student_contactNo,
                'profilePic' => $student->student_profilePic,
                'gender' => $student->detail->studentGender->core_metaName,
                'address' => $student->detail->student_detailAddress,
                'country' => $student->detail->country->country_name,
                'state' => $student->detail->state->state_name,
                'city' => $student->detail->city->city_name,
                'postcode' => $student->detail->student_detailPostcode,
            ];
            return response()->json([
                'success' => true,
                'data' => $stduentDetail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function subjectList(Request $request)
    {
        try {
            $request->validate([
                'search' => 'string|max:255',
                'selectedSubject' => 'array',
                'category' => 'required|integer'
            ]);

            $list = stp_subject::when($request->filled('search'), function ($query) use ($request) {
                $query->where('subject_name', 'like', '%' . $request->search . '%');
            })
                ->when($request->filled('selectedSubject'), function ($query) use ($request) {
                    $query->whereNotIn('id', $request->selectedSubject);
                })
                ->where('subject_status', 1)
                ->where('subject_category', $request->category)
                ->get();

            $subjectList = [];
            foreach ($list as $subject) {
                $subjectList[] = [
                    'id' => $subject->id,
                    'name' => $subject->subject_name,
                ];
            }
            return response()->json([
                'success' => true,
                'data' => $subjectList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addEditTranscript(Request $request)
    {
        try {
            $request->validate([
                'category' => 'required|integer',
                'data' => 'required|array',
                'data.*.grade' => 'required|integer',
                'data.*.subjectID' => 'required|integer'
            ]);

            $authUser = Auth::user();
            $existingSubject = stp_transcript::where('transcript_category', $request->category)
                ->where('student_id', $authUser->id)
                ->where('transcript_status', 1)
                ->pluck('subject_id')
                ->toArray();

            $requestSubject = collect($request->data)->pluck('subjectID')->toArray();
            // $newArray = array_diff($requestSubject, $existingSubject);
            $removeArray = array_diff($existingSubject, $requestSubject);

            if (!empty($removeArray)) {
                foreach (array_values($removeArray) as $new) {
                    $data = stp_transcript::where('subject_id', $new)
                        ->where('transcript_category', $request->category)
                        ->where('student_id', $authUser->id)
                        ->update(['transcript_status' => 0]);
                }
            }

            foreach ($request->data as $requestData) {
                $findExist = stp_transcript::where('subject_id', $requestData['subjectID'])
                    ->where('transcript_category', $request->category)
                    ->where('student_id', $authUser->id)
                    ->exists();
                if ($findExist) {
                    $updateData = [
                        'subject_id' => $requestData['subjectID'],
                        'transcript_grade' => $requestData['grade'],
                        'transcript_status' => 1
                    ];
                    $findExist = stp_transcript::where('subject_id', $requestData['subjectID'])
                        ->where('transcript_category', $request->category)
                        ->where('student_id', $authUser->id)
                        ->update($updateData);
                } else {
                    // return $requestData;
                    stp_transcript::create([
                        'subject_id' => $requestData['subjectID'],
                        'transcript_grade' => $requestData['grade'],
                        'transcript_category' => $request->category,
                        'student_id' => $authUser->id
                    ]);
                }
            }

            return  response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully update the transcript']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'messsage' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function addEditHigherTranscript(Request $request)
    {
        try {
            $request->validate([
                'category' => 'required|integer',
                'data' => ['required', 'array', new UniqueInArray('name')],
                'data.*.name' => 'required|string|max:255',
                'data.*.grade' => 'required|integer'
            ]);

            $authUser = Auth::user();
            $data = $request->data;

            $existData = stp_higher_transcript::get();
            $existName = $existData->map(function ($test) {
                return $test->highTranscript_name;
            });
            $dataNames = collect(array_column($data, 'name'));
            $missingItems = array_diff($existName->toArray(), $dataNames->toArray());
            $missingItemsValue = array_values($missingItems);

            if (count($missingItemsValue) > 0) {
                foreach ($missingItemsValue as $removeData) {
                    stp_higher_transcript::where('highTranscript_name', $removeData)
                        ->where('highTranscript_status', 1)
                        ->update([
                            'highTranscript_status' => 0,
                            'updated_by' => $authUser->id
                        ]);
                }
            }

            foreach ($data as $new) {
                $newdata = false;
                if (empty(count($existData))) {
                    $newdata = true;
                } else {
                    foreach ($existData as $exist) {
                        if ($new['name'] == $exist->highTranscript_name) {
                            $newdata = false;
                            $exist->update([
                                'higherTranscript_grade' => $new['grade'],
                                'highTranscript_status' => 1,
                                'updated_by' => $authUser->id
                            ]);
                            break;
                        } else {
                            $newdata = true;
                        }
                    }
                }

                if ($newdata == true) {
                    stp_higher_transcript::create([
                        'highTranscript_name' => $new['name'],
                        'category_id' => $request->category,
                        'student_id' => $authUser->id,
                        'higherTranscript_grade' => $new['grade'],
                        'created_by' => $authUser->id,
                    ]);
                }
            }

            return 'success';
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function countryList(Request $request)
    {
        try {
            $countryList = stp_country::get();
            return response()->json([
                'success' => true,
                'data' => $countryList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function instituteType(Request $request)
    {
        try {
            $institueList = stp_core_meta::where('core_metaType', 'institute')->get();
            return response()->json([
                'success' => true,
                'data' => $institueList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function qualificationFilterList(Request $request)
    {
        try {
            $qualificationList = stp_qualification::where('qualification_status', 1)
                ->get()
                ->map(function ($qualiList) {
                    return [
                        'id' => $qualiList->id,
                        'qualification_name' => $qualiList->qualification_name
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $qualificationList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function studyModeFilterlist(Request $request)
    {
        try {
            $studyModeListing = stp_core_meta::where('core_metaType', 'study_mode')
                ->where('core_metaStatus', 1)
                ->get()
                ->map(function ($studyMode) {
                    return [
                        'id' => $studyMode->id,
                        'studyMode_name' => $studyMode->core_metaName
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $studyModeListing
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function locationFilterList(Request $request)
    {
        try {

            $request->validate([
                'countryID' => 'required|integer'
            ]);

            $country = stp_country::find($request->countryID);
            $states = $country->state;
            $stateList = [];
            foreach ($states as $state) {
                $stateList[] = [
                    'id' => $state->id,
                    'state_name' => $state->state_name
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stateList
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function categoryFilterList(Request $request)
    {
        try {
            $categoryList = stp_courses_category::get()
                ->map(function ($categories) {
                    return [
                        'id' => $categories->id,
                        'category_name' => $categories->category_name
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $categoryList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function tuitionFeeFilterRange(Request $request)
    {
        try {
            $maxCost = stp_course::where('course_status', 1)
                ->max('course_cost');

            return response()->json([
                'success' => true,
                'data' => $maxCost
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function applyCourse(Request $request)
    {
        try {
            $request->validate([
                'courseID' => 'required|integer',
            ]);

            $authUser = Auth::user();
            $studentID = $authUser->id;
            $checkingCourse = stp_submited_form::where('courses_id', $request->courseID)
                ->where('student_id', $studentID)
                ->where('form_status', '!=', 3)
                ->exists();
            if ($checkingCourse) {
                throw ValidationException::withMessages([
                    "courses" => ['You had already Applied this course']
                ]);
            }
            stp_submited_form::create([
                'student_id' => $studentID,
                'courses_id' => $request->courseID,
                'form_status' => 2,
                'created_by' => $authUser->id,
                'created_at' => now(),
            ]);

            $this->serviceFunctionController->sendSchoolEmail($request->courseID, $authUser);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Applied for the Course']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function pendingAppList(Request $request)
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();
            $studentID = $authUser->id;

            // Query the stp_submited_form model
            $courseList = stp_submited_form::with([
                'course',
                'course.school',
                'course.category',
                'course.qualification',
                'course.studyMode',
                'course.school.country',
                'course.school.state',
                'course.school.city'

            ])
                ->where('form_status', 2)
                ->where('student_id', $studentID)
                ->when($request->filled('course_name'), function ($query) use ($request) {
                    $query->whereHas('course', function ($query) use ($request) {
                        $query->where('course_name', 'like', '%' . $request->course_name . '%');
                    });
                })
                ->paginate(10)
                ->through(function ($submittedForm) {
                    $course = $submittedForm->course;
                    $school = $course->school;
                    return [
                        "course_name" => $course->course_name,
                        "school_name" => $course->school->school_name,
                        "course_period" => $course->course_period,
                        "course_intake" => $course->course_intake,
                        "qualification" => $course->qualification->qualification_name,
                        "course_logo" => $course->course_logo ?: $course->school->school_logo,
                        "category_name" => $course->category->category_name,
                        "study_mode" => $course->studyMode->core_metaName ?? 'Not Available',
                        "country_name" => $school->country->country_name,
                        "state_name" => $school->state->state_name,
                        "city_name" => $school->city->city_name,
                        "status" => "Pending",
                        'student_id' => $submittedForm->student_id,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $courseList
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function historyAppList(Request $request)
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();
            $studentID = $authUser->id;
            // Query the stp_submited_form model
            $courseList = stp_submited_form::with([
                'course',
                'course.school',
                'course.category',
                'course.qualification',
                'course.studyMode',
                'course.school.country',
                'course.school.state',
                'course.school.city'

            ])
                ->whereIn('form_status', [0, 3, 4])
                ->where('student_id', $studentID)
                ->when($request->filled('course_name'), function ($query) use ($request) {
                    $query->whereHas('course', function ($query) use ($request) {
                        $query->where('course_name', 'like', '%' . $request->course_name . '%');
                    });
                })
                ->paginate(10)
                ->through(function ($submittedForm) {
                    $course = $submittedForm->course;
                    $school = $course->school;

                    // Determine the status message based on form_status
                    $status = match ($submittedForm->form_status) {
                        0 => "Deleted",
                        3 => "Rejected",
                        4 => "Accepted",
                        default => "Unknown"
                    };
                    return [
                        "course_name" => $course->course_name,
                        "school_name" => $course->school->school_name,
                        "course_period" => $course->course_period,
                        "course_intake" => $course->course_intake,
                        "qualification" => $course->qualification->qualification_name,
                        "course_logo" => $course->course_logo ?: $course->school->school_logo,
                        "category_name" => $course->category->category_name,
                        "study_mode" => $course->studyMode->core_metaName ?? 'Not Available',
                        "country_name" => $school->country->country_name,
                        "state_name" => $school->state->state_name,
                        "city_name" => $school->city->city_name,
                        "status" => $status,
                        'student_id' => $submittedForm->student_id,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $courseList
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function editStudent(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'firt_name' => 'string|max:255',
                'last_name' => 'string|max:255',
                'address' => 'string|max:255',
                'country' => 'integer',
                'city' => 'integer',
                'state' => 'integer',
                'gender' => 'integer',
                'postcode' => 'string',
                'ic' => 'integer|min:6|',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
            ]);
            $authUser = Auth::user();

            //check ic
            $checkingIc = stp_student::where('student_icNumber', $request->ic)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingIc) {
                throw ValidationException::withMessages([
                    'ic' => ['ic has been used'],
                ]);
            }

            //checking contact number
            $checkingUserContact = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }


            $student = stp_student::find(1);
            $studentDetail = $student->detail;

            $checkingEmail = stp_student::where('student_email', $request->email)
                ->where('id', '!=', $request->id)
                ->exists();


            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['Contact has been taken'],
                ]);
            }

            $updateingStudent = $student->update([
                "student_userName" => $request->name,
                "student_password" => Hash::make($request->password),
                'student_icNumber' => $request->ic,
                'student_email' => $request->email,
                'student_countryCode' => $request->country_code,
                'student_contactNo' => $request->contact_number,
                'updated_by' => $authUser->id
            ]);

            $updatingDetail = $studentDetail->update([
                "student_detailFirstName" => $request->first_name ?? "",
                "student_detailLastName" => $request->last_name ?? "",
                "student_detailAddress" => $request->address ?? "",
                "country_id" => $request->country ?? null,
                'gender' => $request->gender ?? null,
                "city_id" => $request->city ?? null,
                "state_id" => $request->state ?? null,
                "student_detailPostcode" => $request->postcode ?? "",
                'updated_by' => $authUser->id
            ]);

            if ($updateingStudent) {
                return response()->json([
                    'success' => true,
                    "data" => ["message" => "update successful"]
                ]);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Sever Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfilePic(Request $request)
    {
        try {
            $request->validate([
                'porfilePic' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048' // Image validationt
            ]);
            $authUser = Auth::user();


            if (!empty($authUser->student_profilePic)) {
                Storage::delete('public/' . $authUser->student_profilePic);
            }



            $image = $request->file('porfilePic');
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            $imagePath = $image->storeAs('studentProfilePic', $imageName, 'public'); // Store in 'storage/app/public/images'
            $authUser->update([
                'student_profilePic' => $imagePath,
                'updated_by' => $authUser->id
            ]);
            // $authUser->student_profilePic = $imagePath; // Save the path to the database

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Update profile successfully']
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => "Internal Server Error",
                    'error' => $e->getMessage()
                ]
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ]);
        }
    }

    public function resetStudentPassword(Request $request)
    {
        try {
            $request->validate([
                'currentPassword' => 'required|string|min:8',
                'newPassword' => 'required|string|min:8',
                'confirmPassword' => 'required|string|min:8|same:newPassword'
            ]);
            $authUser = Auth::user();
            if (!Hash::check($request->currentPassword, $authUser->student_password)) {
                throw ValidationException::withMessages(["password does not match"]);
            }

            $authUser->update([
                'student_password' => Hash::make($request->newPassword),
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['messenger' => "Successfully reset password"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function addAchievement(Request $request)
    {
        try {
            $request->validate([
                'achievement_name' => 'required|string|max:255',
                'date' => 'required|string|max:255',
                'title' => 'required|integer',
                'awarded_by' => 'required|string|max:255',
                'achievement_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:2048'
            ]);

            $authUser = Auth::user();
            $checkingAchievement = stp_achievement::where('student_id', $authUser->id)
                ->where('achievement_name', $request->achievement_name)
                ->exists();

            if ($checkingAchievement) {
                throw ValidationException::withMessages([
                    "courses" => ['Achievement with this name already uploaded']
                ]);
            }

            if ($request->hasFile('achievement_media')) {
                $image = $request->file('achievement_media');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('achievementCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
            }
            stp_achievement::create([
                'achievement_name' => $request->achievement_name,
                'date' => $request->date,
                'title_obtained' => $request->title,
                'awarded_by' => $request->awarded_by,
                'achievement_media' => $imagePath ?? '',
                'achievements_status' => 1,
                'student_id' => $authUser->id,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the Achievement']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function editAchievement(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'achievement_name' => 'required|string|max:255',
                'date' => 'required|string|max:255',
                'title' => 'required|integer',
                'awarded_by' => 'required|string|max:255',
                'achievement_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:2048' // Image validation
            ]);
            $checkingAchievement = stp_achievement::where('student_id',  $authUser->id)
                ->where('achievement_name', $request->achievement_name)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingAchievement) {
                throw ValidationException::withMessages([
                    "Achievement" => ['Achievement with this name already uploaded']
                ]);
            }
            $achievement = stp_achievement::find($request->id);

            if ($request->hasFile('achievement_media')) {
                if (!empty($achievement->achievement_media)) {
                    Storage::delete('public/' . $achievement->achievement_media);
                }
                $image = $request->file('achievement_media');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('achievementCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $achievement->update([
                'student_id' => $authUser->id,
                'achievement_name' => $request->achievement_name,
                'date' => $request->date,
                'title_obtained' => $request->title,
                'awarded_by' => $request->awarded_by,
                'achievement_media' => $imagePath ?? null,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'errors' => $e->errors()
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
                "errors" => $e->getMessage()
            ]);
        }
    }

    public function deleteAchievement(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255'
            ]);

            $authUser = Auth::user();

            if ($request->type == 'delete') {
                $status = 0;
                $message = "Successfully Deleted the Achievement";
            }

            $achievement = stp_achievement::find($request->id);

            $achievement->update([
                'student_id' => $authUser->id,
                'achievements_status' => $status,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => $message]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'Errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'succcess' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function achievementsList(Request $request)
    {
        try {

            $authUser = Auth::user();
            $studentlID = $authUser->id;

            $achievementList = stp_achievement::query()
                ->where('achievements_status', 1)
                ->where('student_id', $studentlID)

                ->paginate(10)
                ->through(function ($achievementList) {
                    $status = ($achievementList->achievements_status == 1) ? "Active" : "Inactive";
                    return [
                        "achievement_name" => $achievementList->achievement_name,
                        "awarded_by" => $achievementList->awarded_by,
                        "title_obtained" => $achievementList->title->core_metaName ?? '',
                        "date" => $achievementList->date,
                        "achievement_media" => $achievementList->achievement_media,
                        "status" => "Active"
                    ];
                });
            return $achievementList;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function sendReminder(Request $request)
    {
        try {
            $request->validate([
                "formID" => 'required|integer'
            ]);
            $form = stp_submited_form::find($request->formID);

            $authUser = Auth::user();
            $this->serviceFunctionController->sendReminder($form, $authUser);
            return response()->json([
                'success' => true,
                'data' => 'Send Reminder successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function transcriptCategoryList(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'category_id' => 'integer|nullable'
            ]);

            // Query to list all transcript categories with optional filtering
            $categoryList = stp_core_meta::query()
                ->where('core_metaStatus', 1) // Only active categories
                ->where('core_metaType', 'transcript_category') // Only transcript categories
                ->when($request->filled('category_id'), function ($query) use ($request) {
                    $query->where('id', $request->category_id);
                })
                ->paginate(10)
                ->through(function ($categoryList) {
                    return [
                        "id" => $categoryList->id,
                        "transcript_category" => $categoryList->core_metaName,
                        "status" => $categoryList->core_metaStatus == 1 ? "Active" : "Inactive"
                    ];
                });

            // Return the result
            return response()->json([
                'success' => true,
                'data' => $categoryList
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function subjectListByCategory(Request $request)
    {
        try {

            // Validate that 'category_id' is an integer and nullable (optional)
            $request->validate([
                'category_id' => 'integer|nullable'
            ]);

            // Query the stp_subject table to get subjects with the matching category
            $subjectList = stp_subject::query()
                ->where('subject_status', 1) // Assuming 1 means 'Active'
                ->when($request->filled('category_id'), function ($query) use ($request) {
                    // Filtering the subjects by the selected category
                    $query->where('subject_category', $request->category_id);
                })
                ->paginate(10) // Paginating the result
                ->through(function ($subject) {
                    return [
                        "id" => $subject->id,
                        "subject_name" => $subject->subject_name,
                        "category_id" => $subject->subject_category,
                        "status" => $subject->subject_status ? "Active" : "Inactive"
                    ];
                });

            // Return the filtered subject list in JSON format
            return response()->json([
                "success" => true,
                "data" => $subjectList
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => $e->getMessage()
            ], 500);
        }
    }
    public function mediaListByCategory(Request $request)
    {
        try {
            // Validate that 'category_id' is an integer and nullable (optional)
            $authUser = Auth::user();
            $studentID = $authUser->id;

            $request->validate([
                'category_id' => 'integer|nullable'
            ]);

            $mediaList = stp_student_media::query()
                ->where('studentMedia_status', 1)
                ->where('student_id', $studentID)
                ->when($request->filled('category_id'), function ($query) use ($request) {
                    // Filtering the subjects by the selected category
                    $query->where('studentMedia_type', $request->category_id);
                })
                ->paginate(10) // Paginating the result
                ->through(function ($StudentMedia) {
                    return [
                        "id" => $StudentMedia->id,
                        "studentMedia_name" => $StudentMedia->studentMedia_name,
                        "studentMedia_location" => $StudentMedia->studentMedia_location,
                        "category_id" => $StudentMedia->studentMedia_type,
                        "status" => $StudentMedia->studentMedia_status ? "Active" : "Inactive"
                    ];
                });

            // Return the filtered subject list in JSON format
            return response()->json([
                "success" => true,
                "data" => $mediaList
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => $e->getMessage()
            ], 500);
        }
    }

    public function addTranscriptFile(Request $request)
    {
        try {
            $authUser = Auth::user();

            $request->validate([
                'studentMedia_location' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:2048', // File validation
                'studentMedia_name' => 'required|string|max:255',
                'studentMedia_type' => 'required|integer',
                'studentMedia_format' => 'nullable|string|max:255'

            ]);


            $checkingTranscriptFile = stp_student_media::where('student_id', $authUser->id)
                ->where('studentMedia_name', $request->studentMedia_name)
                ->exists();

            if ($checkingTranscriptFile) {
                throw ValidationException::withMessages([
                    "transcripts" => ['Transcript with this name already uploaded']
                ]);
            }

            if ($request->hasFile('studentMedia_location')) {
                $image = $request->file('studentMedia_location');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('transcriptCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
            }
            stp_student_media::create([
                'studentMedia_name' => $request->studentMedia_name,
                'studentMedia_type' => $request->studentMedia_type,
                'studentMedia_format' => $request->studentMedia_format,
                'studentMedia_location' => $imagePath ?? '',
                'studentMedia_status' => 1,
                'student_id' => $authUser->id,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the Transcript']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }
    public function editTranscriptFile(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'studentMedia_type' => 'required|integer',
                'studentMedia_name' => 'required|string|max:255',
                'studentMedia_location' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:2048' // Image validation
            ]);
            $checkingstudentMedia = stp_student_media::where('student_id',  $authUser->id)
                ->where('studentMedia_name', $request->studentMedia_name)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingstudentMedia) {
                throw ValidationException::withMessages([
                    "studentMedia" => ['Transcript with this name already uploaded']
                ]);
            }
            $studentMedia = stp_student_media::find($request->id);

            if ($request->hasFile('studentMedia_location')) {
                if (!empty($studentMedia->studentMedia_location)) {
                    Storage::delete('public/' . $studentMedia->studentMedia_location);
                }
                $image = $request->file('studentMedia_location');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('transcriptCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $studentMedia->update([
                'student_id' => $authUser->id,
                'studentMedia_name' => $request->studentMedia_name,
                'studentMedia_type' => $request->studentMedia_type,
                'studentMedia_location' => $imagePath ?? null,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'errors' => $e->errors()
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
                "errors" => $e->getMessage()
            ]);
        }
    }

    public function deleteTranscriptFile(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255'
            ]);

            $authUser = Auth::user();

            if ($request->type == 'delete') {
                $status = 0;
                $message = "Successfully Deleted the Transcript File";
            }

            $transcriptFile = stp_student_media::find($request->id);

            $transcriptFile->update([
                'student_id' => $authUser->id,
                'studentMedia_status' => $status,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => $message]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'Errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'succcess' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addOtherCertFile(Request $request)
    {
        try {
            $authUser = Auth::user();

            $request->validate([
                'certificate_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:2048', // File validation
                'certificate_name' => 'required|string|max:255'
            ]);


            $checkingCertificateFile = stp_other_certificate::where('student_id', $authUser->id)
                ->where('certificate_name', $request->certificate_name)
                ->exists();

            if ($checkingCertificateFile) {
                throw ValidationException::withMessages([
                    "transcripts" => ['Certificate with this name already uploaded']
                ]);
            }

            if ($request->hasFile('certificate_media')) {
                $image = $request->file('certificate_media');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('otherCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
            }
            stp_other_certificate::create([
                'certificate_name' => $request->certificate_name,
                'certificate_media' => $imagePath ?? '',
                'certificate_status' => 1,
                'student_id' => $authUser->id,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the Certificate']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function editOtherCertFile(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'certificate_name' => 'required|string|max:255',
                'certificate_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:2048' // Image validation
            ]);
            $checkingCertificateMedia = stp_other_certificate::where('student_id',  $authUser->id)
                ->where('certificate_name', $request->certificate_name)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingCertificateMedia) {
                throw ValidationException::withMessages([
                    "certificate" => ['Certificate with this name already uploaded']
                ]);
            }
            $certificate = stp_other_certificate::find($request->id);

            if ($request->hasFile('certificate_media')) {
                if (!empty($certificate->certificate_media)) {
                    Storage::delete('public/' . $certificate->certificate_media);
                }
                $image = $request->file('certificate_media');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('otherCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $certificate->update([
                'student_id' => $authUser->id,
                'certificate_name' => $request->certificate_name,
                'certificate_media' => $imagePath ?? null,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'errors' => $e->errors()
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
                "errors" => $e->getMessage()
            ]);
        }
    }
    public function deleteOtherCertFile(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255'
            ]);

            $authUser = Auth::user();

            if ($request->type == 'delete') {
                $status = 0;
                $message = "Successfully Deleted the Certificate File";
            }

            $certificateFile = stp_other_certificate::find($request->id);

            $certificateFile->update([
                'student_id' => $authUser->id,
                'certificate_status' => $status,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => $message]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'Errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'succcess' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function otherFileCertList(Request $request)
    {
        try {
            $authUser = Auth::user();
            $studentID = $authUser->id;

            $otherCertList = stp_other_certificate::query()
                ->where('certificate_status', 1)
                ->where('student_id', $studentID)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('certificate_name', 'like', '%' . $request->search . '%');
                })
                ->paginate(10) // Paginating the result
                ->through(function ($cert) {
                    $status = ($cert->certificate_status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $cert->certificate_name,
                        "media" => $cert->certificate_media,
                        "status" => "Active"
                    ];
                });

            // Return the filtered subject list in JSON format
            return response()->json([
                "success" => true,
                "data" => $otherCertList
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => $e->getMessage()
            ], 500);
        }
    }

    public function resetDummyAccountPassword(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'newPassword' => 'required|string|min:8',
                'confirmPassword' => 'required|string|min:8|same:newPassword'
            ]);

            $findStudent = stp_student::find(1);
            if ($findStudent->student_status != 3) {
                throw ValidationException::withMessages(['account' => 'Account is not dummy anymore']);
            }

            $findStudent->update([
                'student_password' => Hash::make($request->newPassword),
                'student_status' => 1
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => "Successfully Reset Password"
                ]
            ]);
        } catch (validationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function intakeFilterList()
    {
        try {
            $intakeList = stp_intake::get()
                ->map(function ($intake) {
                    return ['month' => $intake->month->core_metaName];
                })
                ->unique('month')
                ->values(); // Reindex the array
            return response()->json([
                'success' => true,
                'data' => $intakeList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }
}
