<?php

namespace App\Http\Controllers;

use App\Models\stp_course;
use App\Models\stp_courses_category;
use App\Models\stp_featured;
use Illuminate\Http\Request;
use App\Models\stp_school;
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
            return $hpFeaturedSchoolList;
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

            $courseList = stp_course::when($request->filled('qualification'), function ($query) use ($request) {
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


            return $courseList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
