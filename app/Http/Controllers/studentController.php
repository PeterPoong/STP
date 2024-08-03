<?php

namespace App\Http\Controllers;

use App\Models\stp_course;
use App\Models\stp_courses_category;
use App\Models\stp_featured;
use Illuminate\Http\Request;
use App\Models\stp_school;
use App\Models\stp_student;
use App\Models\stp_subject;
use App\Models\stp_tag;
use App\Models\stp_transcript;
use Illuminate\Support\Facades\Auth;
// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;

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
            return response()->json($schoolList);
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

            return response()->json($hpFeaturedCoursesList);
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

    public function addTranscript(Request $request)
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
}
