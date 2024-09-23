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
use App\Models\stp_cgpa;
use App\Models\stp_cocurriculum;
use App\Models\stp_intake;
use App\Models\stp_school_media;
use Illuminate\Support\Facades\Storage;
// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

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
            // Start building the query
            $getSchoolList = stp_school::where('school_status', 1)
                // Exclude schools with zero courses (ensure the school has courses)
                ->whereHas('courses')

                // Filter by institute category (array of categories)
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->whereIn('institue_category', $request->category);
                })
                // Filter by country (single country ID as integer)
                ->when($request->filled('country'), function ($query) use ($request) {
                    $query->where('country_id', $request->country); // Change to where instead of whereIn
                })
                // Filter by location (array of state IDs)
                ->when($request->filled('location'), function ($query) use ($request) {
                    $query->whereIn('state_id', $request->location);
                })
                // Handle search (single search term)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $searchTerm = $request->search; // Directly use the string
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('school_name', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('country', function ($q) use ($searchTerm) {
                                $q->where('country_name', 'like', '%' . $searchTerm . '%');
                            });
                    });
                })
                // Filter by course category (array of categories)
                ->when($request->filled('courseCategory'), function ($query) use ($request) {
                    $query->whereHas('courses', function ($q) use ($request) {
                        $q->whereIn('category_id', $request->courseCategory);
                    });
                })
                // Filter by study level (array of qualification IDs)
                ->when($request->filled('studyLevel'), function ($query) use ($request) {
                    $query->whereHas('courses', function ($q) use ($request) {
                        $q->whereIn('qualification_id', $request->studyLevel);
                    });
                })
                ->with(['courses.intake.month', 'featured', 'institueCategory', 'country', 'state', 'city'])
                ->paginate(10);

            $schoolList = [];

            foreach ($getSchoolList as $school) {
                // Handle featured status
                $featured = false;
                foreach ($school->featured as $s) {
                    if ($s['featured_type'] == 30 && $s['featured_status'] == 1) {
                        $featured = true;
                        break;
                    }
                }

                // Filter courses by category if needed
                $filteredCourses = $school->courses->filter(function ($course) use ($request) {
                    if ($request->filled('courseCategory')) {
                        $courseCategories = is_array($request->courseCategory) ? $request->courseCategory : [$request->courseCategory];
                        return in_array($course->category_id, $courseCategories);
                    }
                    return true; // If courseCategory is not filled, return all courses
                })->values();

                $monthList = [];
                foreach ($filteredCourses as $courses) {
                    foreach ($courses->intake as $c) {
                        $monthName = $c->month->core_metaName;
                        if (!in_array($monthName, $monthList)) {
                            $monthList[] = $monthName;
                        }
                    }
                }

                // Collect school data, with courses reflecting the filtered results
                $schoolList[] = [
                    'id' => $school->id,
                    'name' => $school->school_name,
                    'category' => $school->institueCategory->core_metaName ?? null,
                    'logo' => $school->school_logo,
                    'featured' => $featured,
                    'country' => $school->country->country_name ?? null,
                    'state' => $school->state->state_name ?? null,
                    'city' => $school->city->city_name ?? null,
                    'description' => $school->school_shortDesc,
                    'courses' => count($filteredCourses),  // Updated to show filtered course count
                    'intake' => $monthList
                ];
            }

            // Sort the results by featured status
            usort($schoolList, function ($a, $b) {
                return $b['featured'] <=> $a['featured'];
            });

            // Return the response
            return response()->json([
                'success' => true,
                'data' => $schoolList
            ]);
        } catch (Exception $e) {
            // Handle errors
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);

            $school = stp_school::find($request->id);

            $courses = $school->courses;

            $intake = [];
            foreach ($courses as $c) {
                $months = $c->intake->pluck('month.core_metaName')->toArray();
                if (!empty($months)) {
                    $intake = array_merge($intake, $months);
                }
            }

            $schoolCover = stp_school_media::where('school_id', $request->id)
                ->where('schoolMedia_type', 66)
                ->where('schoolMedia_status', 1)
                ->first();

            $schoolPhoto = stp_school_media::where('school_id', $request->id)
                ->where('schoolMedia_type', 67)
                ->where('schoolMedia_status', 1)
                ->get();


            $intakeMonth = array_values(array_unique($intake));
            $coursesList = $school->courses->makeHidden('intake')->map(function ($course) {
                $monthList = [];
                foreach ($course->intake as $m) {
                    $monthList[] = $m->month->core_metaName;
                }
                return [
                    'id' => $course->id,
                    'course_name' => $course->course_name,
                    'course_cost' => $course->course_cost,
                    'course_period' => $course->course_period,
                    'course_intake' => $monthList,
                    'category' => $course->category->category_name,
                    'qualification' => $course->qualification->qualification_name,
                    'study_mode' => $course->studyMode->core_metaName,
                    'course_logo' => $course->course_logo
                ];
            });




            $schoolDetail = [
                'id' => $school->id,
                'name' => $school->school_name,
                'category' => $school->institueCategory->core_metaName ?? null,
                'logo' => $school->school_logo,
                'country' => $school->country->country_name ?? null,
                'state' => $school->state->state_name ?? null,
                'city' => $school->city->city_name ?? null,
                'short_description' => $school->school_shortDesc,
                'long_description' => $school->school_fullDesc,
                'school_lg' => $school->school_lg,
                'school_lat' => $school->school_lat,
                'number_courses' => count($school->courses),
                'courses' => $coursesList,
                'month' => $intakeMonth,
                'school_cover' => $schoolCover,
                'school_photo' => $schoolPhoto
            ];
            return response()->json([
                'success' => true,
                'data' => $schoolDetail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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
                        "id" => $courses->courses->id,
                        "course_name" => $courses->courses->course_name,
                        "course_logo" => $logo,
                        "course_qualification" => $courses->courses->qualification->qualification_name,
                        "course_qualification_color" => $courses->courses->qualification->qualification_color_code,

                        'course_school' => $courses->courses->school->school_name,
                        'location' => $courses->courses->school->city->city_name ?? null,
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
        try {
            // Validate the request parameters
            $request->validate([
                'search' => 'string',
                'countryID' => 'integer',
                'qualification' => 'integer',
                'location' => 'array',
                'category' => 'array',
                'schoolCategory' => 'integer',
                'institute' => 'integer',
                'studyMode' => 'array',
                'tuitionFee' => 'numeric',
                'intake' => 'array'
            ]);

            // Apply filters and paginate directly
            $query = stp_course::query();

            $query->when($request->filled('qualification'), function ($query) use ($request) {
                $query->where('qualification_id', $request->qualification);
            })
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->whereIn('category_id', $request->category);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('course_name', 'like', '%' . $request->search . '%')
                        ->orWhereHas('school', function ($query) use ($request) {
                            $query->where('school_name', 'like', '%' . $request->search . '%');
                        });
                })
                ->when($request->filled('countryID'), function ($query) use ($request) {
                    $query->whereHas('school', function ($query) use ($request) {
                        $query->where('country_id', $request->countryID);
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
                });

            // Paginate the results
            $courses = $query->paginate(40);

            // Transform the results
            $transformedCourses = $courses->through(function ($course) {
                $featured = false;
                foreach ($course->featured as $c) {
                    if ($c['featured_type'] == 30 && $c['featured_status'] == 1) {
                        $featured = true;
                        break;
                    }
                }
                $intakeMonths = $course->intake->pluck('month.core_metaName')->toArray();
                return [
                    'id' => $course->id,
                    'school_name' => $course->school->school_name,
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
                    'country' => $course->school->country->country_name ?? null,
                    'state' => $course->school->state->state_name ?? null,
                    'institute_category' => $course->school->institueCategory->core_metaName ?? null
                ];
            });

            return $transformedCourses;

            return response()->json([
                'success' => true,
                'data' => $transformedCourses
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function studentDetail()
    {
        try {
            $authUser = Auth::user();

            $studentDetail = [
                'id' => $authUser->id,
                'username' => $authUser->student_userName,
                'firstName' => $authUser->detail->student_detailFirstName,
                'lastName' => $authUser->detail->student_detailLastName,
                'ic' => $authUser->student_icNumber,
                'email' => $authUser->student_email,
                'country_code' => $authUser->student_countryCode,
                'contact' => $authUser->student_contactNo,
                'profilePic' => $authUser->student_profilePic,
                'gender' => $authUser->detail->studentGender->core_metaName ?? null,
                'address' => $authUser->detail->student_detailAddress,
                'country' => $authUser->detail->country->country_name ?? null,
                'state' => $authUser->detail->state->state_name ?? null,
                'city' => $authUser->detail->city->city_name ?? null,
                'postcode' => $authUser->detail->student_detailPostcode,
            ];
            return response()->json([
                'success' => true,
                'data' => $studentDetail
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
                'data.*.grade' => 'required|string|max:255'
            ]);

            $authUser = Auth::user();
            $data = $request->data;

            $existData = stp_higher_transcript::where('category_id', $request->category)->get();
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
            return response()->json([
                'success' => true,
                'data' => ['message' => "successfully update your result"]
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
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function addProgramCgpa(Request $request)
    {

        try {
            $authUser = Auth::user();
            $request->validate([
                'programName' => 'string',
                'transcriptCategory' => 'required|integer',
                'cgpa' => 'required|numeric'
            ]);

            $createCgpa = stp_cgpa::create([
                'student_id' => $authUser->id,
                'program_name' => $request->programName ?? null,
                'transcript_category' => $request->transcriptCategory,
                'cgpa' => $request->cgpa,
                'created_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'successfully added the cgpa']
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => "Internal Server Error",
                    'error' => $e->getMessage()
                ]
            );
        }
    }

    public function editProgramCgpa(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'cgpaId' => 'required|integer',
                'programName' => "string",
                'cgpa' => 'required|numeric'
            ]);


            $cgpa = stp_cgpa::find($request->cgpaId);

            $update = $cgpa->update([
                'program_name' => $request->programName ?? null,
                'cgpa' => $request->cgpa,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "update successfully"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function programCgpaList(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'transcriptCategory' => 'required|integer'
            ]);
            $list = stp_cgpa::where('student_id', $authUser->id)
                ->where('transcript_category', $request->transcriptCategory)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $list
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
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
                    $dateTime = new \DateTime($submittedForm->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    return [
                        "id" => $submittedForm->id,
                        "course_name" => $course->course_name,
                        "school_name" => $course->school->school_name,
                        "course_period" => $course->course_period,
                        "course_intake" => $course->course_intake,
                        "qualification" => $course->qualification->qualification_name,
                        "course_logo" => $course->course_logo ?: $course->school->school_logo,
                        "category_name" => $course->category->category_name,
                        "study_mode" => $course->studyMode->core_metaName ?? 'Not Available',
                        "country_name" => $school->country->country_name ?? null,
                        "state_name" => $school->state->state_name ?? null,
                        "city_name" => $school->city->city_name ?? null,
                        "status" => "Pending",
                        'student_id' => $submittedForm->student_id,
                        'feedback' => $submittedForm->form_feedback,
                        'date_applied' => $appliedDate,
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
                        0 => "WithDrawl",
                        3 => "Rejected",
                        4 => "Accepted",
                        default => "Unknown"
                    };
                    $dateTime = new \DateTime($submittedForm->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    return [
                        "id" => $submittedForm->id,
                        "course_name" => $course->course_name,
                        "school_name" => $course->school->school_name,
                        "course_period" => $course->course_period,
                        "course_intake" => $course->course_intake,
                        "qualification" => $course->qualification->qualification_name,
                        "course_logo" => $course->course_logo ?: $course->school->school_logo,
                        "category_name" => $course->category->category_name,
                        "study_mode" => $course->studyMode->core_metaName ?? 'Not Available',
                        "country_name" => $school->country->country_name ?? null,
                        "state_name" => $school->state->state_name ?? null,
                        "city_name" => $school->city->city_name ?? null,
                        "status" => $status,
                        'student_id' => $submittedForm->student_id,
                        'feedback' => $submittedForm->form_feedback,
                        'date_applied' => $appliedDate,
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

    public function withdrawApplicant(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $applicant = stp_submited_form::find($request->id);
            $applicant->update([
                'form_status' => 0
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => "Successfully withdraw"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }



    public function editStudent(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'string|max:255',
                'last_name' => 'string|max:255',
                'address' => 'string|max:255',
                'country' => 'integer',
                'city' => 'integer',
                'state' => 'integer',
                'gender' => 'integer',
                'postcode' => 'string',
                'ic' => 'string|min:6|',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
            ]);
            $authUser = Auth::user();

            //check ic
            $checkingIc = stp_student::where('student_icNumber', $request->ic)
                ->where('id', '!=', $authUser->id)
                ->exists();


            if ($checkingIc) {
                throw ValidationException::withMessages([
                    'ic' => ['ic has been used'],
                ]);
            }

            //checking contact number
            $checkingUserContact = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->where('id', '!=', $authUser->id)
                ->exists();
            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }


            $student = stp_student::find($authUser->id);
            $studentDetail = $student->detail;

            $checkingEmail = stp_student::where('student_email', $request->email)
                ->where('id', '!=', $authUser->id)
                ->exists();


            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['Email has been taken'],
                ]);
            } else {
            }

            $updateingStudent = $student->update([
                "student_userName" => $request->name,
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

            $newData = [
                'student_id' => $authUser->id,
                'achievement_name' => $request->achievement_name,
                'date' => $request->date,
                'title_obtained' => $request->title,
                'awarded_by' => $request->awarded_by,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ];

            if ($request->hasFile('achievement_media')) {
                $newData['achievement_media'] = $imagePath;
            };
            $achievement->update($newData);

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
                        "id" => $achievementList->id,
                        "achievement_name" => $achievementList->achievement_name,
                        "awarded_by" => $achievementList->awarded_by,
                        "title_obtained" => $achievementList->title->core_metaName ?? '',
                        "date" => $achievementList->date,
                        "achievement_media" => $achievementList->achievement_media,
                        "status" => "Active"
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $achievementList
            ]);
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
                    $dateTime = new \DateTime($StudentMedia->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    return [
                        "id" => $StudentMedia->id,
                        "studentMedia_name" => $StudentMedia->studentMedia_name,
                        "studentMedia_location" => $StudentMedia->studentMedia_location,
                        "category_id" => $StudentMedia->studentMedia_type,
                        "created_at" => $appliedDate,
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

            $newData = [
                'student_id' => $authUser->id,
                'studentMedia_name' => $request->studentMedia_name,
                'studentMedia_type' => $request->studentMedia_type,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ];

            if ($request->hasFile('studentMedia_location')) {
                if (!empty($studentMedia->studentMedia_location)) {
                    Storage::delete('public/' . $studentMedia->studentMedia_location);
                }
                $image = $request->file('studentMedia_location');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('transcriptCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
                $newData['studentMedia_location'] = $imagePath ?? null;
            }

            $studentMedia->update($newData);






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

            $dataUpdate = [
                'student_id' => $authUser->id,
                'certificate_name' => $request->certificate_name,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ];
            if ($request->hasFile('certificate_media')) {
                $dataUpdate['certificate_media'] = $imagePath;
            }

            $certificate->update($dataUpdate);

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
                    $dateTime = new \DateTime($cert->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    $status = ($cert->certificate_status == 1) ? "Active" : "Inactive";
                    return [
                        "id" => $cert->id,
                        "name" => $cert->certificate_name,
                        "media" => $cert->certificate_media,
                        'created_at' => $appliedDate,
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

            $findStudent = stp_student::find($request->id);
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function courseCategoryList()
    {
        try {

            $getCourseList = stp_core_meta::where('core_metaType', 'transcript_category')->get()->map(function ($c) {
                return [
                    'id' => $c->id,
                    'core_metaType' => $c->core_metaType,
                    'core_metaName' => $c->core_metaName
                ];
            });
            return response()->json([
                'success' => true,
                'data' => $getCourseList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function genderList()
    {
        try {
            $getGenderList = stp_core_meta::where('core_metaType', 'gender')->get()->map(function ($g) {
                return [
                    'id' => $g->id,
                    'core_metaType' => $g->core_metaType,
                    'core_metaName' => $g->core_metaName
                ];
            });
            return response()->json([
                'success' => true,
                'data' => $getGenderList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function featuredInstituteList(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|string'
            ]);

            switch ($request->type) {
                case "secondPage":
                    $featuredType = 30;
                    break;
                case "thirdPage":
                    $featuredType = 31;
                    break;
            };
            $featuredInstituteList = stp_featured::where('school_id', '!=', null)
                ->where('featured_type', $featuredType)
                ->where('featured_status', 1)
                ->get()
                ->map(function ($institute) {
                    return [
                        'school_id' => $institute->id,
                        'school_name' => $institute->school->school_name,
                        'school_logo' => $institute->school->school_logo,

                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $featuredInstituteList
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => "Internal Server Error",
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    public function featuredCourseList(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|string'
            ]);

            switch ($request->type) {
                case "secondPage":
                    $featuredType = 30;
                    break;
                case "thirdPage":
                    $featuredType = 31;
                    break;
            }

            $featuredCoursesList = stp_featured::where('course_id', '!=', null)
                ->where('featured_type', $featuredType)
                ->where('featured_status', 1)
                ->get()
                ->map(function ($featured) {
                    return [
                        'course_id' => $featured->courses->id,
                        'course_name' => $featured->courses->course_name,
                        'course_logo' => $featured->courses->course_logo ?? $featured->courses->school->school_logo,
                        'course_qualification' => $featured->courses->qualification->qualification_name,
                        'course_qualification_color' => $featured->courses->qualification->qualification_color_code,
                        'course_school' => $featured->courses->school->school_name,
                        'state' => $featured->courses->school->state->state_name ?? null,
                        'country' => $featured->courses->school->country->country_name ?? null,
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $featuredCoursesList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addCocurriculumList(Request $request)
    {
        try {
            $request->validate([
                'club_name' => 'required|string|max:255',
                'position' => 'required|string|max:255',
                'institute_name' => 'required|string|max:255',
                'year' => 'required|integer'
            ]);
            $authUser = Auth::user();
            $newdata = [
                'student_id' => $authUser->id,
                'club_name' => $request->club_name,
                'student_position' => $request->position,
                'location' => $request->institute_name,
                'year' => $request->year,
                'created_by' => $authUser->id
            ];
            stp_cocurriculum::create($newdata);
            return response()->json([
                'success' => true,
                'data' => ['message' => "successfully create cocurriculum"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editCocurriculum(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'club_name' => 'required|string|max:255',
                'position' => 'required|string|max:255',
                'institute_name' => 'required|string|max:255',
                'year' => 'required|integer'
            ]);
            $authUser = Auth::user();
            $updateData = [
                'club_name' => $request->club_name,
                'student_position' => $request->position,
                'location' => $request->institute_name,
                'year' => $request->year,
                'updated_by' => $authUser->id
            ];
            $getCocurriculum = stp_cocurriculum::find($request->id);
            if (empty($getCocurriculum)) {
                throw ValidationException::withMessages(['cocurriculum' => 'Co-curriculum not found']);
            }
            $getCocurriculum->update($updateData);
            return response()->json([
                'success' => true,
                'data' => ['message' => "Update co-curriculum successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'error' => $e->errors()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function disableCocurriculum(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $authUser = Auth::user();
            $getCocurriculum = stp_cocurriculum::find($request->id);
            if (empty($getCocurriculum)) {
                throw ValidationException::withMessages(['co-curriculum' => "co-curriculum not found"]);
            }
            $getCocurriculum->update([
                'cocurriculums_status' => 0,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Cocurriculum being disable successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'error' => $e->errors()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function cocurriculumList()
    {
        try {
            $authUser = Auth::user();
            $getCocurriculum = stp_cocurriculum::where('student_id', $authUser->id)
                ->where('cocurriculums_status', 1)
                ->get();
            return response()->json([
                'success' => true,
                'data' => $getCocurriculum
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function achievementTypeList()
    {
        try {
            $achievementTypeList = stp_core_meta::where('core_metaType', 'achievementType')
                ->where('core_metaStatus', 1)
                ->get();
            return response()->json([
                'success' => true,
                'data' => $achievementTypeList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function transcriptSubjectList()
    {
        try {
            $authUser = Auth::user();
            $getTranscriptSubject = stp_transcript::where('student_id', $authUser->id)
                ->where('transcript_status', 1)
                ->get()
                ->map(function ($subject) {
                    return [
                        'subject_id' => $subject->subject->id,
                        'subject_name' => $subject->subject->subject_name,
                        'subject_grade_id' => $subject->grade->id,
                        'subject_grade' => $subject->grade->core_metaName,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $getTranscriptSubject
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function higherTranscriptSubjectList(Request $request)
    {
        try {
            $request->validate([
                'id' => "required|integer"
            ]);
            $authUser = Auth::user();
            $getHigherTranscriptSubject = stp_higher_transcript::where('category_id', $request->id)
                ->where('student_id', $authUser->id)
                ->where('highTranscript_status', 1)
                ->get();
            return response()->json([
                'success' => true,
                'data' => $getHigherTranscriptSubject
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function hotPickCategoryList(Request $request)
    {
        try {
            $getHotPickList = stp_courses_category::where("course_hotPick", 1)
                ->where("category_status", 1)
                ->get();
            return response()->json([
                'success' => true,
                'data' => $getHotPickList
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
