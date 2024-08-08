<?php

namespace App\Http\Controllers;

use App\Models\stp_core_meta;
use App\Models\stp_country;
use App\Models\stp_course;
use App\Models\stp_courses_category;
use App\Models\stp_featured;
use App\Models\stp_higher_transcript;
use App\Models\stp_qualification;
use Illuminate\Http\Request;
use App\Models\stp_school;
use App\Models\stp_student;
use App\Models\stp_subject;
use App\Models\stp_tag;
use App\Models\stp_transcript;
use App\Models\stp_submited_form;
use Illuminate\Support\Facades\Auth;
// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;
use App\Rules\UniqueInArray;

use Exception;

class studentController extends Controller
{
    public function schoolList(Request $request)
    {
        try {
            $schoolList = stp_school::where('school_status', 1)
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
                ->paginate(10)
                ->through(function ($school) {
                    return [
                        'id' => $school->id,
                        'name' => $school->school_name,
                        'category' => $school->institueCategory->core_metaName ?? null,
                        'logo' => $school->school_logo,
                        'country' => $school->country->country_name ?? null,
                        'state' => $school->state->state_name ?? null,
                        'city' => $school->city->city_name ?? null,
                        'description' => $school->school_shortDesc
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $schoolList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Sever Error',
                'error' => $e
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

            $hpFeaturedCoursesList = stp_featured::whereNotNull('course_id')->get()->map(function ($courses) {
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
        // $test = stp_course::find(1);
        // return $test->school;
        try {
            $request->validate([
                'search' => 'string',
                'country' => 'integer',
                'qualification' => 'integer',
                'state' => 'integer',
                'category' => 'integer',
            ]);

            // $test = stp_course::find(1);
            // return $test->studyMode->core_metaName;
            $getCourses = stp_course::when($request->filled('qualification'), function ($query) use ($request) {
                $query->orWhere('qualification_id', $request->qualification);
            })
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->orWhere('category_id', $request->category);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('course_name', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('country'), function ($query) use ($request) {
                    $query->whereHas('school', function ($query) use ($request) {
                        $query->where('country_id', 1);
                    });
                })
                ->paginate(10);

            $cousesList = [];

            foreach ($getCourses as $course) {
                if (empty($course->course_logo)) {
                    $logo = $course->school->school_logo;
                } else {
                    $logo = $course->course_logo;
                }
                $coursesList[] = [
                    'id' => $course->id,
                    'school_id' => $course->school->school_name,
                    'name' => $course->course_name,
                    'description' => $course->course_description,
                    'requirement' => $course->course_requirement,
                    'cost' => $course->course_cost,
                    'period' => $course->course_period,
                    'intake' => $course->course_intake,
                    'category' => $course->category->category_name,
                    'qualification' => $course->qualification->qualification_name,
                    'mode' => $course->studyMode->core_metaName ?? null,
                    'logo' => $logo,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $coursesList
            ]);
        } catch (\Exception $e) {
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
                'profilePic' => $student->student_proilePic,
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
                ->where('user_id', $authUser->id)
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
                        ->where('user_id', $authUser->id)
                        ->update(['transcript_status' => 0]);
                }
            }

            foreach ($request->data as $requestData) {
                $findExist = stp_transcript::where('subject_id', $requestData['subjectID'])
                    ->where('transcript_category', $request->category)
                    ->where('user_id', $authUser->id)
                    ->exists();
                if ($findExist) {
                    $updateData = [
                        'subject_id' => $requestData['subjectID'],
                        'transcript_grade' => $requestData['grade'],
                        'transcript_status' => 1
                    ];
                    $findExist = stp_transcript::where('subject_id', $requestData['subjectID'])
                        ->where('transcript_category', $request->category)
                        ->where('user_id', $authUser->id)
                        ->update($updateData);
                } else {
                    // return $requestData;
                    stp_transcript::create([
                        'subject_id' => $requestData['subjectID'],
                        'transcript_grade' => $requestData['grade'],
                        'transcript_category' => $request->category,
                        'user_id' => $authUser->id
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

            $country = stp_country::find(1);
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
                    "courses" => ['This Student applied for this course']
                ]);
            }
            stp_submited_form::create([
                'student_id' => $studentID,
                'courses_id' => $request->courseID,
                'form_status' => 2,
                'created_by' => $authUser->id,
                'created_at' => now(),
            ]);
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
}
