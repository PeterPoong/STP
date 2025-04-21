<?php

namespace App\Http\Controllers;

use App\Models\stp_achievement;
use App\Models\stp_core_meta;
use App\Models\stp_country;
use App\Models\stp_course;
use App\Models\stp_courseInterest;
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
use App\Models\User;
use App\Models\stp_transcript;
use App\Models\stp_submited_form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\serviceFunctionController;
use App\Models\stp_cgpa;
use App\Models\stp_cocurriculum;
use App\Models\stp_intake;
use App\Models\stp_school_media;
use App\Models\stp_personalityQuestions;
use Illuminate\Support\Facades\Storage;
use App\Models\stp_advertisement_banner;
use App\Models\stp_personalityTestResult;
use App\Models\stp_riasecResultImage;

use App\Models\stp_totalNumberVisit;
// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;


use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

use App\Rules\UniqueInArray;
use Exception;



class studentController extends Controller
{
    protected $serviceFunctionController;

    public function __construct(serviceFunctionController $serviceFunctionController)
    {
        $this->serviceFunctionController = $serviceFunctionController;
    }
    public function checkTermsAgreement()
    {
        try {
            $user = Auth::user();

            return response()->json([
                'success' => true,
                'hasAgreed' => (bool)$user->terms_agreed,
                'agreedAt' => $user->terms_agreed_at
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check terms agreement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function agreeTerms(Request $request)
    {
        try {
            $request->validate([
                'agreed' => 'required|boolean'
            ]);

            // Get the authenticated student using Sanctum
            $student = auth('sanctum')->user();

            if (!$student) {
                \Log::error('Student not found in agreeTerms');
                return response()->json([
                    'success' => false,
                    'message' => 'Student not authenticated'
                ], 401);
            }

            \Log::info('Updating terms agreement for student:', [
                'student_id' => $student->id,
                'student_email' => $student->student_email
            ]);

            // Update only the authenticated student's terms agreement
            $updated = $student->update([
                'terms_agreed' => true,
                'terms_agreed_at' => now(),
                'updated_by' => $student->id
            ]);

            if (!$updated) {
                throw new Exception('Failed to update terms agreement');
            }

            \Log::info('Terms agreement updated successfully for student:', [
                'student_id' => $student->id,
                'terms_agreed' => $student->terms_agreed,
                'terms_agreed_at' => $student->terms_agreed_at
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Terms agreement updated successfully',
                'data' => [
                    'hasAgreed' => (bool)$student->terms_agreed,
                    'agreedAt' => $student->terms_agreed_at
                ]
            ]);
        } catch (ValidationException $e) {
            \Log::error('Validation error in agreeTerms:', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error in agreeTerms:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update terms agreement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function schoolList(Request $request)
    {

        try {
            // Validation
            $request->validate([
                'search' => 'nullable|string',
                'countryID' => 'integer',
                'qualification_id' => 'integer',
                'location' => 'array',
                'category_id' => 'array',
                'institute' => 'integer',
                'studyMode' => 'array',
                'tuition_fee' => 'numeric',
                'intake_month' => 'array'
            ]);

            $filterConditions = function ($query) use ($request) {
                $query->where('school_status', '!=', 0)
                    ->when($request->filled('qualification_id'), function ($q) use ($request) {
                        $q->whereHas('courses', function ($query) use ($request) {
                            $query->where('qualification_id', $request->qualification_id);
                        });
                    })
                    ->when($request->filled('search'), function ($q) use ($request) {
                        // Searching in school name and country name
                        $q->where(function ($query) use ($request) {
                            $query->where('school_name', 'like', '%' . $request->search . '%')
                                ->orWhereHas('country', function ($query) use ($request) {
                                    $query->where('country_name', 'like', '%' . $request->search . '%');
                                });
                        });
                    })
                    ->when($request->filled('countryID'), function ($q) use ($request) {
                        $q->where('country_id', $request->countryID);
                    })
                    ->when($request->filled('location'), function ($q) use ($request) {
                        $q->whereIn('state_id', $request->location);
                    })
                    ->when($request->filled('category_id'), function ($q) use ($request) {
                        $q->whereHas('courses', function ($query) use ($request) {
                            $query->whereIn('category_id', $request->category_id);
                        });
                    })
                    ->when($request->filled('institute'), function ($q) use ($request) {
                        $q->where('institue_category', $request->institute);
                    })
                    ->when($request->filled('studyMode'), function ($q) use ($request) {
                        $q->whereHas('courses', function ($query) use ($request) {
                            $query->whereIn('study_mode', $request->studyMode);
                        });
                    })
                    ->when($request->filled('tuition_fee'), function ($q) use ($request) {
                        $q->whereHas('courses', function ($query) use ($request) {
                            $query->where('course_cost', '<=', $request->tuition_fee);
                        });
                    })
                    ->when($request->filled('intake_month'), function ($q) use ($request) {
                        $q->whereHas('courses', function ($query) use ($request) {
                            $query->whereHas('intake', function ($query) use ($request) {
                                $query->whereIn('intake_month', $request->intake_month);
                            });
                        });
                    });
            };

            $perPage = 10;
            $featuredLimit = 5;

            $featuredSchools = stp_school::query()
                ->select('stp_schools.*')
                ->join('stp_featureds', function ($join) {
                    $join->on('stp_schools.id', '=', 'stp_featureds.school_id')
                        ->whereNotNull('stp_featureds.school_id')
                        ->where('stp_featureds.featured_startTime', '<', now())
                        ->where('stp_featureds.featured_endTime', '>', now())
                        ->where('stp_featureds.featured_type', 30)
                        ->where('stp_featureds.featured_status', 1);
                })
                ->where($filterConditions)
                ->with(['courses' => function ($query) use ($request) {
                    $query->when($request->filled('qualification_id'), function ($q) use ($request) {
                        $q->where('qualification_id', $request->qualification_id);
                    })
                        ->when($request->filled('category_id'), function ($q) use ($request) {
                            $q->whereIn('category_id', $request->category_id);
                        })
                        ->when($request->filled('studyMode'), function ($q) use ($request) {
                            $q->whereIn('study_mode', $request->studyMode);
                        })
                        ->when($request->filled('tuition_fee'), function ($q) use ($request) {
                            $q->where('course_cost', '<=', $request->tuition_fee);
                        })
                        ->when($request->filled('intake_month'), function ($q) use ($request) {
                            $q->whereHas('intake', function ($subQuery) use ($request) {
                                $subQuery->where('intake_month', $request->intake_month);
                            });
                        });
                }])
                ->inRandomOrder() // Randomize each time
                ->take($featuredLimit)
                ->get();


            // Calculate offset and limit for the page
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;

            // Calculate limit for non-featured schools to fill remaining slots
            $nonFeaturedLimit = $perPage - $featuredSchools->count();

            // Query non-featured schools
            $nonFeaturedSchools = stp_school::query()
                ->select('stp_schools.*')
                ->leftJoin('stp_featureds', function ($join) {
                    $join->on('stp_schools.id', '=', 'stp_featureds.school_id')
                        ->where('stp_featureds.featured_type', 30)
                        ->where('stp_featureds.featured_status', 1)
                        ->where('featured_startTime', '<', now())
                        ->where('featured_endTime', '>', now());
                })

                ->whereNull('stp_featureds.school_id')
                ->where($filterConditions)
                ->with(['courses' => function ($query) use ($request) {
                    $query->when($request->filled('qualification_id'), function ($q) use ($request) {
                        $q->where('qualification_id', $request->qualification_id);
                    })
                        ->when($request->filled('category_id'), function ($q) use ($request) {
                            $q->whereIn('category_id', $request->category_id);
                        })
                        ->when($request->filled('studyMode'), function ($q) use ($request) {
                            $q->whereIn('study_mode', $request->studyMode);
                        })
                        ->when($request->filled('tuition_fee'), function ($q) use ($request) {
                            $q->where('course_cost', '<=', $request->tuition_fee);
                        })
                        ->when($request->filled('intake_month'), function ($q) use ($request) {
                            $q->whereHas('intake', function ($subQuery) use ($request) {
                                $subQuery->where('intake_month', $request->intake_month);
                            });
                        });
                }])
                ->skip($offset)
                ->take($nonFeaturedLimit)
                ->get();

            // return $nonFeaturedSchools;
            // Merge featured and non-featured results for the page
            $schools = $featuredSchools->concat($nonFeaturedSchools)->unique('id');

            // Get total count of featured and non-featured schools for pagination
            $totalFeatured = $featuredSchools->count();
            $totalNonFeatured = stp_school::query()
                ->leftJoin('stp_featureds', function ($join) {
                    $join->on('stp_schools.id', '=', 'stp_featureds.school_id')
                        ->where('stp_featureds.featured_type', 30)
                        ->where('stp_featureds.featured_status', 1);
                })
                ->whereNull('stp_featureds.school_id')
                ->where($filterConditions)
                ->count();

            // Paginate the combined result with unique entries
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $schools,
                $totalFeatured + $totalNonFeatured,
                $perPage,
                $page,
                ['path' => $request->url()]
            );

            // Transform the schools as per requirements
            $transformedSchools = $paginator->through(function ($school) {
                $featured = $school->featured->contains(function ($item) {
                    return $item->featured_type == 30 && $item->featured_status == 1 && $item->featured_startTime < now() && $item->featured_endTime > now();
                });
                $monthList = [];
                foreach ($school->courses as $courses) {
                    foreach ($courses->intake as $c) {
                        $monthName = $c->month->core_metaName;
                        if (!in_array($monthName, $monthList)) {
                            $monthList[] = $monthName;
                        }
                    }
                }
                $monthOrder = [
                    'January' => 1,
                    'February' => 2,
                    'March' => 3,
                    'April' => 4,
                    'May' => 5,
                    'June' => 6,
                    'July' => 7,
                    'August' => 8,
                    'September' => 9,
                    'October' => 10,
                    'November' => 11,
                    'December' => 12
                ];

                // Sort the months according to the predefined order
                usort($monthList, function ($a, $b) use ($monthOrder) {
                    return $monthOrder[$a] - $monthOrder[$b];
                });

                return [
                    'id' => $school->id,
                    'name' => $school->school_name,
                    'category' => $school->institueCategory->core_metaName ?? null,
                    'logo' => $school->school_logo,
                    'featured' => $featured,
                    'country' => $school->country->country_name ?? null,
                    'state' => $school->state->state_name ?? null,
                    'city' => $school->city->city_name ?? null,
                    'description' => $school->school_shortDesc,
                    'course_count' => $school->courses->count(),
                    'google_map_location' => $school->school_google_map_location,
                    'intake' =>  $monthList,
                    // 'tuition_fee' => number_format($school->tuition_fee),

                ];
            });


            return response()->json([
                'success' => true,
                'current_page' => $paginator->currentPage(),
                'data' => array_values($transformedSchools->items()),
                'first_page_url' => $paginator->url(1),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'next_page_url' => $paginator->nextPageUrl(),
                'path' => $paginator->path(),
                'links' => $paginator->links(),
                'per_page' => $paginator->perPage(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function courseDetail(Request $request)
    {

        try {
            // $request->validate([
            //     'courseID' => 'required|integer'
            // ]);

            // $courseList = stp_course::find($request->courseID);

            $request->validate([
                'courseID' => 'integer'
            ]);
            if (!empty($request->courseID)) {
                $courseList = stp_course::find($request->courseID);
            } else {
                $request->validate([
                    'schoolName' => 'required|string',
                    'courseName' => 'required|string'
                ]);
                $courseList = stp_course::where('course_name', $request->courseName)
                    ->whereHas('school', function ($query) use ($request) {
                        $query->where('school_name', $request->schoolName);
                    })
                    ->get()
                    ->first();
            }



            if (empty($courseList->course_logo)) {
                $logo = $courseList->school->school_logo;
            } else {
                $logo = $courseList->course_logo;
            }

            $courseTag = $courseList->tag;
            $tagList = [];
            foreach ($courseTag as $tag) {
                $tagList[] = [
                    "id" => $tag->tag['id'],
                    "tagName" => $tag->tag['tag_name']
                ];
            }
            // Fetch all intakes associated with the course
            $intakeList = [];

            foreach ($courseList->intake as $intake) {
                if ($intake->intake_status == 1) {
                    $intakeList[] = $intake->month->core_metaName;
                }
            }
            $featuredList = [];
            foreach ($courseList->featured as $courseFeatured) {
                $featuredList[] = $courseFeatured->featured->id;
            }

            foreach ($courseList->school->media as $photo) {
                if ($photo->schoolMedia_type == 66) {
                    $coverPhoto = $photo->schoolMedia_location;
                    break;
                }
            }

            foreach ($courseList->school->media as $photo) {

                if ($photo->schoolMedia_type == 67) {
                    $schoolPhoto[] = $photo->schoolMedia_location;
                }
            }



            $courseListDetail = [
                'id' => $courseList->id,
                'course' => $courseList->course_name,
                'description' => $courseList->course_description,
                'requirement' => $courseList->course_requirement,
                'cost' => number_format($courseList->course_cost),
                'international_cost' => number_format($courseList->international_cost),
                'country' => $courseList->school->country->country_name ?? null,
                'country_code' => $courseList->school->country->country_code ?? null,
                'period' => $courseList->course_period,
                'intake' => $intakeList, // Updated to include all intakes
                'courseFeatured' => $featuredList,
                'category' => $courseList->category->category_name,
                'school' => $courseList->school->school_name,
                'schoolShortDescription' => $courseList->school->school_shortDesc,
                'schoolLongDescription' => $courseList->school->school_fullDesc,
                'schoolCategory' => $courseList->school->institueCategory->core_metaName,
                'schoolEmail' => $courseList->school->school_email,
                'schoolID' => $courseList->school_id,
                'schoolLocation' => $courseList->school->school_location ?? null,
                'google_map_location' => $courseList->school->school_google_map_location ?? null,
                'qualification' => $courseList->qualification->qualification_name,
                'mode' => $courseList->studyMode->core_metaName ?? null,
                'logo' => $logo,
                'coverPhoto' => $coverPhoto ?? null,
                'schoolPhoto' => $schoolPhoto ?? null,
                'tag' => $tagList
            ];

            return response()->json([
                'success' => true,
                'data' => $courseListDetail
            ]);
            return $courseListDetail;
        } catch (\Exception $e) {
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
                'id' => 'integer'
            ]);

            if (!empty($request->id)) {
                $school = stp_school::find($request->id);
            } else {
                $request->validate([
                    'schoolName' => 'required|string'
                ]);
                $school = stp_school::where('school_name', $request->schoolName)->get()->first();
            }

            $courses = $school->courses;



            $schoolCover = stp_school_media::where('school_id', $school->id)
                ->where('schoolMedia_type', 66)
                ->where('schoolMedia_status', 1)
                ->first();

            $schoolPhoto = stp_school_media::where('school_id', $school->id)
                ->where('schoolMedia_type', 67)
                ->where('schoolMedia_status', 1)
                ->get();

            $intake = [];
            $monthsOrder = [
                'January' => 1,
                'February' => 2,
                'March' => 3,
                'April' => 4,
                'May' => 5,
                'June' => 6,
                'July' => 7,
                'August' => 8,
                'September' => 9,
                'October' => 10,
                'November' => 11,
                'December' => 12
            ];

            foreach ($courses as $c) {
                $months = $c->intake->pluck('month.core_metaName')->toArray();
                if (!empty($months)) {
                    $intake = array_merge($intake, $months);
                }
            }

            // Convert month names to numbers using the $monthsOrder mapping
            $intakeNumeric = array_map(function ($month) use ($monthsOrder) {
                return $monthsOrder[$month] ?? 13; // Default to 13 if month is not found
            }, $intake);

            // Sort the numeric months
            sort($intakeNumeric);

            // Convert the numeric months back to month names
            $sortedIntake = array_map(function ($monthNumber) use ($monthsOrder) {
                return array_flip($monthsOrder)[$monthNumber];
            }, $intakeNumeric);


            $intakeMonth = array_values(array_unique($sortedIntake));
            $coursesList = $school->courses
                ->makeHidden('intake')
                ->map(function ($course) {
                    if ($course->course_status != 0) {
                        $monthList = [];
                        foreach ($course->intake as $m) {
                            $monthList[] = $m->month->core_metaName;
                        }
                        $monthOrder = [
                            'January' => 1,
                            'February' => 2,
                            'March' => 3,
                            'April' => 4,
                            'May' => 5,
                            'June' => 6,
                            'July' => 7,
                            'August' => 8,
                            'September' => 9,
                            'October' => 10,
                            'November' => 11,
                            'December' => 12
                        ];

                        // Sort months according to the predefined order
                        usort($monthList, function ($a, $b) use ($monthOrder) {
                            return $monthOrder[$a] - $monthOrder[$b];
                        });
                        return [
                            'id' => $course->id,
                            'course_name' => $course->course_name,
                            'course_cost' => number_format($course->course_cost),
                            'international_cost' => number_format($course->international_cost),
                            'course_period' => $course->course_period,
                            'course_intake' => $monthList,
                            'category' => $course->category->category_name,
                            'qualification' => $course->qualification->qualification_name,
                            'study_mode' => $course->studyMode->core_metaName ?? null,
                            'course_logo' => $course->course_logo
                        ];
                    }
                    return null;
                })
                ->filter() // Removes null values
                ->values();




            $schoolDetail = [
                'id' => $school->id,
                'name' => $school->school_name,
                'school_email' => $school->school_email,
                'category' => $school->institueCategory->core_metaName ?? null,
                'logo' => $school->school_logo,
                'country' => $school->country->country_name ?? null,
                'country_code' => $school->country->country_code ?? null,
                'state' => $school->state->state_name ?? null,
                'city' => $school->city->city_name ?? null,
                'short_description' => $school->school_shortDesc,
                'long_description' => $school->school_fullDesc,
                'school_lg' => $school->school_lg,
                'school_lat' => $school->school_lat,
                'number_courses' => count($school->courses),
                'google_map_location' => $school->school_google_map_location,
                'courses' => $coursesList,
                'month' => $intakeMonth,
                'school_cover' => $schoolCover,
                'school_photo' => $schoolPhoto,
                'location' => $school->school_location
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
            $hpFeaturedSchoolList = stp_featured::where('featured_type', 28)
                ->where('featured_startTime', '<', now())
                ->where('featured_endTime', '>', now())
                ->where('featured_status', 1)
                ->whereHas('school', function ($query) {
                    $query->whereIn('school_status', [1, 3]);
                })
                ->inRandomOrder()
                ->get()
                ->map(function ($school) {
                    return ([
                        'schoolID' => $school->school->id,
                        'schoolName' => $school->school->school_name,
                        'schoolLogo' => $school->school->school_logo
                    ]);
                })
                ->unique('schoolID')
                ->values();

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
                ->where('featured_startTime', '<', now())
                ->where('featured_endTime', '>', now())

                ->where('featured_status', 1)
                ->whereHas('courses', function ($query) {
                    $query->where('course_status', '!=', 0)
                        ->whereHas('school', function ($school) {
                            $school->whereIn('school_status', [1, 3]);
                        });
                })
                ->inRandomOrder()
                ->get()->map(function ($courses) {
                    if (empty($courses->courses->course_logo)) {
                        $logo = $courses->courses->school->school_logo;
                    } else {
                        $logo = $courses->courses->course_logo;
                    }
                    return [
                        "id" => $courses->courses->id,
                        "school_id" => $courses->courses->school->id,
                        "course_name" => $courses->courses->course_name,
                        "course_logo" => $logo,
                        "course_qualification" => $courses->courses->qualification->qualification_name,
                        "course_qualification_color" => $courses->courses->qualification->qualification_color_code,
                        'course_school' => $courses->courses->school->school_name,
                        'location' => $courses->courses->school->city->city_name ?? null,
                    ];
                })
                ->unique('id')
                ->values();

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
            $request->validate([
                'search' => 'nullable|string',
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

            $filterConditions = function ($query) use ($request) {
                $query->whereHas('school', function ($q) {
                    $q->whereIn('school_status', ["1", "3"]);
                })
                    ->where('course_status', '!=', 0)
                    ->when($request->filled('qualification'), function ($q) use ($request) {
                        $q->where('qualification_id', $request->qualification);
                    })
                    ->when($request->filled('category'), function ($q) use ($request) {
                        $q->whereIn('category_id', $request->category);
                    })
                    ->when($request->filled('search'), function ($q) use ($request) {
                        $q->where('course_name', 'like', '%' . $request->search . '%')
                            ->orWhereHas('school', function ($q) use ($request) {
                                $q->where('school_name', 'like', '%' . $request->search . '%');
                            });
                    })
                    ->when($request->filled('countryID'), function ($q) use ($request) {
                        $q->whereHas('school', function ($q) use ($request) {
                            $q->where('country_id', $request->countryID);
                        });
                    })
                    ->when($request->filled('institute'), function ($q) use ($request) {
                        $q->whereHas('school', function ($q) use ($request) {
                            $q->where('institue_category', $request->institute);
                        });
                    })
                    ->when($request->filled('studyMode'), function ($q) use ($request) {
                        $q->whereIn('study_mode', $request->studyMode);
                    })
                    ->when($request->filled('location'), function ($q) use ($request) {
                        $q->whereHas('school', function ($q) use ($request) {
                            $q->whereIn('state_id', $request->location);
                        });
                    })
                    ->when($request->filled('tuitionFee'), function ($q) use ($request) {
                        $q->where('course_cost', '<=', $request->tuitionFee);
                    })
                    ->when($request->filled('intake'), function ($q) use ($request) {
                        $q->whereHas('intake', function ($q) use ($request) {
                            $q->whereIn('intake_month', $request->intake);
                        });
                    });
            };


            $perPage = 40;
            $featuredLimit = 5;

            // Randomly select featured courses
            $featuredCourses = stp_course::query()
                ->select('stp_courses.*')
                ->join('stp_featureds', function ($join) {
                    $join->on('stp_courses.id', '=', 'stp_featureds.course_id')
                        ->whereNotNull('stp_featureds.course_id')
                        ->where('stp_featureds.featured_startTime', '<', now())
                        ->where('stp_featureds.featured_endTime', '>', now())
                        ->where('stp_featureds.featured_type', 30)
                        ->where('stp_featureds.featured_status', 1);
                })
                ->where($filterConditions)
                ->whereHas('school', function ($q) {
                    $q->whereIn('school_status', ["1", "3"]);
                })
                ->inRandomOrder() // Randomize each time
                ->take($featuredLimit)
                ->get()
                ->unique('id');

            // Calculate offset and limit for the page
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;

            // Calculate limit for non-featured courses to fill remaining slots
            $nonFeaturedLimit = $perPage - $featuredCourses->count();

            // Query non-featured courses
            // $nonFeaturedCourses = stp_course::query()
            //     ->select('stp_courses.*')
            //     ->leftJoin('stp_featureds', function ($join) {
            //         $join->on('stp_courses.id', '=', 'stp_featureds.course_id')
            //             ->where('stp_featureds.featured_type', 30)
            //             ->where('stp_featureds.featured_status', 1);
            //     })
            //     ->whereNull('stp_featureds.course_id')
            //     ->where($filterConditions)
            //     ->inRandomOrder()
            //     ->skip($offset)
            //     ->take($nonFeaturedLimit)
            //     ->get();
            $nonFeaturedCourses = stp_course::query()
                ->select('stp_courses.*')
                ->whereDoesntHave('featured', function ($q) {
                    $q->where('featured_type', 30)
                        ->where('featured_status', 1)
                        ->where('featured_startTime', '<', now())
                        ->where('featured_endTime', '>', now());
                })
                ->where($filterConditions)
                ->whereHas('school', function ($q) {
                    $q->whereIn('school_status', ["1", "3"]);
                })
                ->inRandomOrder()
                ->skip($offset)
                ->take($nonFeaturedLimit)
                ->get();

            // Merge featured and non-featured results for the page
            $courses = $featuredCourses->concat($nonFeaturedCourses);

            // Get total count of featured and non-featured courses for pagination
            $totalFeatured = $featuredCourses->count();
            $totalNonFeatured = stp_course::query()
                ->leftJoin('stp_featureds', function ($join) {
                    $join->on('stp_courses.id', '=', 'stp_featureds.course_id')
                        ->where('stp_featureds.featured_type', 30)
                        ->where('stp_featureds.featured_status', 1)
                        ->where('featured_startTime', '<', now())
                        ->where('featured_endTime', '>', now());
                })
                ->whereNull('stp_featureds.course_id')
                ->where($filterConditions)
                ->whereHas('school', function ($q) {
                    $q->whereIn('school_status', ["1", "3"]);
                })
                ->count();

            // Paginate the combined result
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $courses,
                $totalFeatured + $totalNonFeatured,
                $perPage,
                $page,
                ['path' => $request->url()]
            );

            // Transform the courses as per requirements
            $transformedCourses = $paginator->through(function ($course) {
                $featured = $course->featured->contains(function ($item) {
                    return $item->featured_type == 30 && $item->featured_status == 1 && $item->featured_startTime < now() && $item->featured_endTime > now();
                });

                $intakeMonths = $course->intake->where('intake_status', 1)
                    ->pluck('month.core_metaName')
                    ->toArray();



                $coverPhoto = null;  // Initialize it to null

                foreach ($course->school->media as $photo) {
                    if ($photo->schoolMedia_type == 66) {
                        $coverPhoto = $photo->schoolMedia_location;
                        break;
                    }
                }

                return [
                    'school_id' => $course->school->id,
                    'email' => $course->school->school_email,
                    'school_cover' => $coverPhoto,
                    'id' => $course->id,
                    'school_name' => $course->school->school_name,
                    'name' => $course->course_name,
                    'description' => $course->course_description,
                    'requirement' => $course->course_requirement,
                    'cost' => number_format($course->course_cost),
                    'international_cost' => number_format($course->international_cost),
                    'featured' => $featured,
                    'period' => $course->course_period,
                    'intake' => $intakeMonths,
                    'category' => $course->category->category_name,
                    'qualification' => $course->qualification->qualification_name,
                    'mode' => $course->studyMode->core_metaName ?? null,
                    'logo' => $course->course_logo ?? $course->school->school_logo,
                    'country' => $course->school->country->country_name ?? null,
                    'country_code' => $course->school->country->country_code ?? null,
                    'state' => $course->school->state->state_name ?? null,
                    'institute_category' => $course->school->institueCategory->core_metaName ?? null,
                    'school_location' => $course->school->school_google_map_location,
                    'course_status' => $course->course_status
                ];
            })->values(); // Apply values() to reindex the data



            // Reset the collection in the paginator
            $paginator->setCollection(collect($transformedCourses));

            // Return the paginated response in the desired format
            return response()->json($paginator);

            // return $transformedCourses;


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
            $getFrontIc = $authUser->media()->where('studentMedia_type', 89)->get()->first();
            $getBackIc = $authUser->media()->where('studentMedia_type', 90)->get()->first();
            $getPassport = $authUser->media()->where('studentMedia_type', 91)->get()->first();
            $frontIc = [
                'studentMedia_name' => $getFrontIc->studentMedia_name ?? "",
                'studentMedia_location' => $getFrontIc->studentMedia_location ?? ""
            ];

            $backIc = [
                'studentMedia_name' => $getBackIc->studentMedia_name ?? "",
                'studentMedia_location' => $getBackIc->studentMedia_location ?? ""
            ];

            $passport = [
                'studentMedia_name' => $getPassport->studentMedia_name ?? "",
                'studentMedia_location' => $getPassport->studentMedia_location ?? ""

            ];





            $studentDetail = [
                'id' => $authUser->id,
                'username' => $authUser->student_userName,
                'firstName' => $authUser->detail->student_detailFirstName,
                'lastName' => $authUser->detail->student_detailLastName,
                'ic' => $authUser->student_icNumber,
                'email' => $authUser->student_email,
                'country_code' => $authUser->student_countryCode,
                'contact' => $authUser->student_contactNo,
                'nationality' => $authUser->student_nationality,
                'profilePic' => $authUser->student_profilePic,
                'gender' => $authUser->detail->studentGender->core_metaName ?? null,
                'address' => $authUser->detail->student_detailAddress,
                'country' => $authUser->detail->country->country_name ?? null,
                'state' => $authUser->detail->state->state_name ?? null,
                'city' => $authUser->detail->city->city_name ?? null,
                'postcode' => $authUser->detail->student_detailPostcode,
                'frontIc' => $frontIc,
                'backIc' => $backIc,
                'passport' => $passport

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

            if ($request->category == 85) {
                $category = 32;
            } else {
                $category = $request->category;
            }
            $list = stp_subject::when($request->filled('search'), function ($query) use ($request) {
                $query->where('subject_name', 'like', '%' . $request->search . '%');
            })
                ->when($request->filled('selectedSubject'), function ($query) use ($request) {
                    $query->whereNotIn('id', $request->selectedSubject);
                })
                ->where('subject_status', 1)
                ->where('subject_category', $category)
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
            // Validate the request
            $request->validate([
                'countryID' => 'required|integer'
            ]);

            // Find the country by ID and get the associated states
            $country = stp_country::find($request->countryID);
            $states = $country->state;

            // Create the state list and sort it by state_name in ascending order
            $stateList = collect($states)->map(function ($state) {
                return [
                    'id' => $state->id,
                    'state_name' => $state->state_name
                ];
            })->sortBy('state_name')->values(); // Sort and reindex the array

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
            $categoryList = stp_courses_category::where('category_status', 1)
                ->orderBy('category_name', 'asc')
                ->get()
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
                ->where('form_status', '!=', 0)
                ->exists();
            if ($checkingCourse) {
                throw ValidationException::withMessages([
                    "courses" => ['You had already Applied this course']
                ]);
            }
            $newApplicant = stp_submited_form::create([
                'student_id' => $studentID,
                'courses_id' => $request->courseID,
                'form_status' => 2,
                'created_by' => $authUser->id,
                'created_at' => now(),
            ]);
            if ($newApplicant->course->school->id == 115) {

                $this->serviceFunctionController->notifyAdminCustomSchoolApplication($request->courseID, $authUser);
            } else {
                $this->serviceFunctionController->sendSchoolEmail($request->courseID, $authUser, $newApplicant->id);
            }



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
                // Add orderBy for descending order by 'created_at'
                ->where('form_status', 2)
                ->where('student_id', $studentID)
                ->when($request->filled('course_name'), function ($query) use ($request) {
                    $query->whereHas('course', function ($query) use ($request) {
                        $query->where('course_name', 'like', '%' . $request->course_name . '%');
                    });
                })
                ->orderBy('created_at', 'desc') // Order by 'created_at' in descending order
                ->paginate(10)
                ->through(function ($submittedForm) {
                    $course = $submittedForm->course;
                    $school = $course->school;
                    $dateTime = new \DateTime($submittedForm->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    $intakeMonths = $course->intake->pluck('month.core_metaName')->toArray();

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
                        'date_applied' => $appliedDate, // Applied date in the correct format
                        'intake' => $intakeMonths

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
                ->orderBy('created_at', 'desc') // Order by 'created_at' in descending order
                ->paginate(10)
                ->through(function ($submittedForm) {
                    $course = $submittedForm->course;
                    $school = $course->school;

                    // Determine the status message based on form_status
                    $status = match ($submittedForm->form_status) {
                        0 => "Withdrawal",
                        3 => "Rejected",
                        4 => "Accepted",
                        default => "Unknown"
                    };
                    $dateTime = new \DateTime($submittedForm->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    $intakeMonths = $course->intake->pluck('month.core_metaName')->toArray();

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
                        'intake' => $intakeMonths
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
                'student_nationality' => 'required|string',
                'country' => 'integer',
                'city' => 'integer',
                'state' => 'integer',
                'gender' => 'integer',
                'postcode' => 'string',
                'ic' => 'required|string',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
                'student_frontIC' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10000',
                'student_backIC' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10000',
                'student_passport' => 'nullable|file|mimes:jpeg,png,jpg,pdf,PNG|max:10000',
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
            }

            // front ic
            if ($request->hasFile('student_frontIC')) {
                $checkFrontIC = stp_student_media::where('student_id', $authUser->id)->where('studentMedia_type', 89)->get()->first();
                if ($checkFrontIC == null) {

                    $icFrontImage = $request->file('student_frontIC');
                    $icFrontImageName = 'frontIc' . time() . '.' . $icFrontImage->getClientOriginalExtension();
                    $icFrontImagePath = $icFrontImage->storeAs('studentDocument', $icFrontImageName, 'public'); // Store in 'storage/app/public/images'

                    stp_student_media::create([
                        'studentMedia_name' => 'icFrontImage',
                        'studentMedia_type' => 89,
                        'studentMedia_format' => 'photo',
                        'studentMedia_location' => $icFrontImagePath ?? '',
                        'studentMedia_status' => 1,
                        'student_id' => $authUser->id,
                        'created_by' => $authUser->id,
                        'created_at' => now()
                    ]);
                } else {

                    Storage::delete('public/' .  $checkFrontIC->studentMedia_location);
                    $icFrontImage = $request->file('student_frontIC');
                    $icFrontImageName =  'frontIc' . time() . '.' . $icFrontImage->getClientOriginalExtension();
                    $icFrontImagePath = $icFrontImage->storeAs('studentDocument', $icFrontImageName, 'public'); // Store in 'storage/app/public/images'
                    $newFrontIcData['studentMedia_location'] = $icFrontImagePath ?? null;
                    $checkFrontIC->update($newFrontIcData);
                }
            }



            // back ic
            if ($request->hasFile('student_backIC')) {
                $checkBackIC = stp_student_media::where('student_id', $authUser->id)->where('studentMedia_type', 90)->get()->first();
                if ($checkBackIC == null) {

                    $icBackImage = $request->file('student_backIC');
                    $icBackImageName = 'backIc' .  time() . '.' . $icBackImage->getClientOriginalExtension();
                    $icBackImagePath = $icBackImage->storeAs('studentDocument', $icBackImageName, 'public'); // Store in 'storage/app/public/images'

                    stp_student_media::create([
                        'studentMedia_name' => 'icBackImage',
                        'studentMedia_type' => 90,
                        'studentMedia_format' => 'photo',
                        'studentMedia_location' => $icBackImagePath ?? '',
                        'studentMedia_status' => 1,
                        'student_id' => $authUser->id,
                        'created_by' => $authUser->id,
                        'created_at' => now()
                    ]);
                } else {
                    Storage::delete('public/' .  $checkBackIC->studentMedia_location);
                    $icBackImage = $request->file('student_backIC');
                    $icBackImageName = 'backIc' . time() . '.' . $icBackImage->getClientOriginalExtension();
                    $icBackImagePath = $icBackImage->storeAs('studentDocument', $icBackImageName, 'public'); // Store in 'storage/app/public/images'
                    $newData['studentMedia_location'] = $icBackImagePath ?? null;
                    $checkBackIC->update($newData);
                }
            }

            //passport
            if ($request->hasFile('student_passport')) {
                $checkPassport = stp_student_media::where('student_id', $authUser->id)->where('studentMedia_type', 91)->get()->first();
                if ($checkPassport == null) {
                    $passportImage = $request->file('student_passport');

                    $passportImageName = 'passport' . time() . '.' . $passportImage->getClientOriginalExtension();
                    $passportImagePath = $passportImage->storeAs('studentDocument', $passportImageName, 'public'); // Store in 'storage/app/public/images'

                    stp_student_media::create([
                        'studentMedia_name' => 'passport',
                        'studentMedia_type' => 91,
                        'studentMedia_format' => 'photo',
                        'studentMedia_location' => $passportImagePath ?? '',
                        'studentMedia_status' => 1,
                        'student_id' => $authUser->id,
                        'created_by' => $authUser->id,
                        'created_at' => now()
                    ]);
                } else {
                    Storage::delete('public/' .  $checkPassport->studentMedia_location);
                    $passportImage = $request->file('student_passport');
                    $passportImageName = 'passport' . time() . '.' . $passportImage->getClientOriginalExtension();
                    $passportImagePath = $passportImage->storeAs('studentDocument', $passportImageName, 'public'); // Store in 'storage/app/public/images'
                    $newData['studentMedia_location'] = $passportImagePath ?? null;
                    $checkPassport->update($newData);
                }
            }



            $updateingStudent = $student->update([
                "student_userName" => $request->name,
                'student_icNumber' => $request->ic,
                'student_email' => $request->email,
                'student_countryCode' => $request->country_code,
                'student_contactNo' => $request->contact_number,
                'student_nationality' => $request->student_nationality,
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
                'porfilePic' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000' // Image validationt
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
                'achievement_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:10000'
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
                'achievement_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:10000' // Image validation
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
            $achievement = stp_achievement::find($request->id);
            Storage::delete('public/' . $achievement->achievement_media);
            $achievement->delete();

            return response()->json([
                'success' => true,
                'data' => ['message' => 'success delete achievement']
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
            $this->serviceFunctionController->sendReminder($form, $authUser, $request->formID);
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

            if ($request->category_id == 85) {
                $category = 32;
            } else {
                $category = $request->category_id;
            }
            // Query the stp_subject table to get subjects with the matching category
            $subjectList = stp_subject::query()
                ->where('subject_status', 1) // Assuming 1 means 'Active'
                ->when($request->filled('category_id'), function ($query) use ($category) {
                    // Filtering the subjects by the selected category
                    $query->where('subject_category', $category);
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
                'studentMedia_location' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:10000', // File validation
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
                'studentMedia_location' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:10000' // Image validation
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

            // if ($request->type == 'delete') {
            //     $status = 0;
            //     $message = "Successfully Deleted the Transcript File";
            // }

            $transcriptFile = stp_student_media::find($request->id);
            Storage::delete('public/' . $transcriptFile->studentMedia_location);
            $transcriptFile->delete();

            // $transcriptFile->update([
            //     'student_id' => $authUser->id,
            //     'studentMedia_status' => $status,
            //     'updated_by' => $authUser->id,
            //     'updated_at' => now(),
            // ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => "Successfully Deleted the Transcript File"]
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
                'certificate_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:10000', // File validation
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
                'certificate_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:10000' // Image validation
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

            // if ($request->type == 'delete') {
            //     $status = 0;
            //     $message = "Successfully Deleted the Certificate File";
            // }

            $certificateFile = stp_other_certificate::find($request->id);
            Storage::delete('public/' . $certificateFile->certificate_media);
            $certificateFile->delete();


            // $certificateFile->update([
            //     'student_id' => $authUser->id,
            //     'certificate_status' => $status,
            //     'updated_by' => $authUser->id,
            //     'updated_at' => now(),
            // ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => "Successfully Deleted the Certificate File"]
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
            $monthsOrder = [
                'January' => 1,
                'February' => 2,
                'March' => 3,
                'April' => 4,
                'May' => 5,
                'June' => 6,
                'July' => 7,
                'August' => 8,
                'September' => 9,
                'October' => 10,
                'November' => 11,
                'December' => 12
            ];

            $intakeList = stp_intake::get()
                ->map(function ($intake) {
                    return ['month' => $intake->month->core_metaName];
                })
                ->unique('month')
                ->sortBy(function ($intake) use ($monthsOrder) {
                    // Sort by the corresponding month number
                    return $monthsOrder[$intake['month']] ?? 13; // Default to 13 if month is not found
                })
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
                'type' => 'required|string',
                'schoolId' => "required|integer"
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
                ->where('featured_startTime', '<', now())
                ->where('featured_endTime', '>', now())
                ->where('id', '!=', $request->schoolId)
                ->inRandomOrder()
                ->get()
                ->map(function ($institute) {
                    return [
                        'school_id' => $institute->school_id,
                        'school_name' => $institute->school->school_name,
                        'school_logo' => $institute->school->school_logo,

                    ];
                })
                ->unique('school_id');


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
                'type' => 'required|string',
                'courseId' => 'required|integer'
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
                ->where('featured_startTime', '<', now())
                ->where('featured_endTime', '>', now())
                ->where('course_id', '!=', $request->courseId)
                ->inRandomOrder()
                ->get()
                ->map(function ($featured) {
                    return [
                        'course_id' => $featured->courses->id,
                        'course_name' => $featured->courses->course_name,
                        'course_logo' => $featured->courses->course_logo ?? $featured->courses->school->school_logo,
                        'course_qualification' => $featured->courses->qualification->qualification_name,
                        'course_qualification_color' => $featured->courses->qualification->qualification_color_code,
                        'school_id' => $featured->courses->school->id,
                        'school_category' => $featured->courses->school->institueCategory->core_metaName,
                        'school_email' => $featured->courses->school->school_email,
                        'course_school' => $featured->courses->school->school_name,
                        'state' => $featured->courses->school->state->state_name ?? null,
                        'country' => $featured->courses->school->country->country_name ?? null,
                    ];
                })
                ->unique('course_id');

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
                ->where('transcript_category', 32)
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

            $getSPMTrial = stp_transcript::where('student_id', $authUser->id)
                ->where('transcript_category', 85)
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

            $data['spm'] = $getTranscriptSubject;
            $data['trial'] = $getSPMTrial;

            return response()->json([
                'success' => true,
                'data' => $data
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

    public function resetTranscript(Request $request)
    {

        try {
            $request->validate([
                'transcriptType' => 'required|integer'
            ]);
            $authUser = Auth::user();
            //spm
            if ($request->transcriptType == 32 || $request->transcriptType == 85) {
                $resetTranscript = stp_transcript::where('student_id', $authUser->id)->where('transcript_category', $request->transcriptType);
            } else {
                $resetTranscript = stp_higher_transcript::where('student_id', $authUser->id)->where('category_id', $request->transcriptType);
                //remove cgpa
                stp_cgpa::where('student_id', $authUser->id)->where('transcript_category', $request->transcriptType)->delete();
            }

            //delete media
            $deleteTranscript = stp_student_media::where('student_id', $authUser->id)->where('studentMedia_type', $request->transcriptType)->get();
            foreach ($deleteTranscript as $deleteTranscriptFile) {
                Storage::delete('public/' . $deleteTranscriptFile->studentMedia_location);
            };

            //delete data media
            stp_student_media::where('student_id', $authUser->id)
                ->where('studentMedia_type', $request->transcriptType)
                ->delete();


            //delete transcript subject 
            $resetTranscript->delete();
            return response()->json([
                'success' => true,
                'message' => "Successfully Delete",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listingFilterList(Request $request)
    {
        try {
            $request->validate([
                'countryID' => 'required|integer'
            ]);

            // Fetch all relevant core_meta data in one query
            $coreMetaData = stp_core_meta::whereIn('core_metaType', ['study_mode', 'institute', 'achievementType', 'month'])
                ->where('core_metaStatus', 1)
                ->get();

            // Initialize arrays to hold categorized data
            $studyModeListing = [];
            $institueList = [];
            $achievementTypeList = [];
            $intakeList = [];

            // Categorize the data based on core_metaType
            foreach ($coreMetaData as $meta) {
                switch ($meta->core_metaType) {
                    case 'study_mode':
                        $studyModeListing[] = [
                            'id' => $meta->id,
                            'studyMode_name' => $meta->core_metaName
                        ];
                        break;
                    case 'institute':
                        $institueList[] = [
                            'id' => $meta->id,
                            'institute_name' => $meta->core_metaName
                        ];
                        break;
                    case 'achievementType':
                        $achievementTypeList[] = [
                            'id' => $meta->id,
                            'achievement_type_name' => $meta->core_metaName
                        ];
                        break;
                    case 'month':
                        $intakeList[] = [
                            'id' => $meta->id,
                            'month' => $meta->core_metaName
                        ];
                        break;
                }
            }

            // Fetch categories, qualifications, and other data as required
            $categoryList = stp_courses_category::where('category_status', 1)
                ->orderBy('category_name', 'asc')
                ->get()
                ->map(function ($categories) {
                    return [
                        'id' => $categories->id,
                        'category_name' => $categories->category_name
                    ];
                });

            $qualificationList = stp_qualification::where('qualification_status', 1)
                ->get()
                ->map(function ($qualiList) {
                    return [
                        'id' => $qualiList->id,
                        'qualification_name' => $qualiList->qualification_name
                    ];
                });

            $maxCost = stp_course::where('course_status', 1)
                ->max('course_cost');

            // Order the months and list intake information
            // $monthsOrder = [
            //     'January' => 1,
            //     'February' => 2,
            //     'March' => 3,
            //     'April' => 4,
            //     'May' => 5,
            //     'June' => 6,
            //     'July' => 7,
            //     'August' => 8,
            //     'September' => 9,
            //     'October' => 10,
            //     'November' => 11,
            //     'December' => 12
            // ];

            // $intakeList = stp_intake::get()
            //     ->map(function ($intake) {
            //         return [
            //             'id' => $intake->month->id,
            //             'month' => $intake->month->core_metaName
            //         ];
            //     })
            //     ->unique('month')
            //     ->sortBy(function ($intake) use ($monthsOrder) {
            //         return $monthsOrder[$intake['month']] ?? 13; // Default to 13 if month is not found
            //     })
            //     ->values();

            // Get country and states data
            $country = stp_country::find($request->countryID);
            $states = $country->state;

            // Create the state list and sort it by state_name in ascending order
            $stateList = collect($states)->map(function ($state) {
                return [
                    'id' => $state->id,
                    'state_name' => $state->state_name
                ];
            })->sortBy('state_name')->values();

            // Return all filtered data in a structured response
            $filterList = [
                'categoryList' => $categoryList,
                'qualificationList' => $qualificationList,
                'studyModeListing' => $studyModeListing,
                'institueList' => $institueList,
                'achievementTypeList' => $achievementTypeList,
                'maxAmount' => $maxCost,
                'intakeList' => $intakeList,
                'state' => $stateList
            ];

            return response()->json([
                'success' => true,
                'data' => $filterList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function applyCourseTranscript(Request $request)
    {
        try {
            $authUser = Auth::user();


            $categoryList = stp_core_meta::query()
                ->where('core_metaStatus', 1) // Only active categories
                ->where('core_metaType', 'transcript_category') // Only transcript categories
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'transcript_category' => $category->core_metaName
                    ];
                });


            $getTranscriptSubject = stp_transcript::where('student_id', $authUser->id)
                ->where('transcript_category', 32)
                ->where('transcript_status', 1)
                ->get()
                ->map(function ($subject) {
                    return [
                        'subject_id' => $subject->subject->id,
                        'subject_name' => $subject->subject->subject_name,
                        'subject_grade_id' => $subject->grade->id ?? null,
                        'subject_grade' => $subject->grade->core_metaName ?? null,
                    ];
                });

            $spmMediaList = stp_student_media::query()
                ->where('studentMedia_status', 1)
                ->where('student_id', $authUser->id)
                ->where('studentMedia_type', 32)
                ->get() // Get all records instead of paginating
                ->map(function ($spmMediaList) {
                    $dateTime = new \DateTime($spmMediaList->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    return [
                        "id" => $spmMediaList->id,
                        "studentMedia_name" => $spmMediaList->studentMedia_name,
                        "studentMedia_location" => $spmMediaList->studentMedia_location,
                        "category_id" => $spmMediaList->studentMedia_type,
                        "created_at" => $appliedDate,
                        "status" => $spmMediaList->studentMedia_status ? "Active" : "Inactive"
                    ];
                });
            $getTranscriptSubject = [
                'subjects' => $getTranscriptSubject, // Use 'subjects' as key
                'document' => $spmMediaList
            ];




            $getSpmTrial = stp_transcript::where('student_id', $authUser->id)
                ->where('transcript_category', 85)
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

            $spmTrialMedia = stp_student_media::query()
                ->where('studentMedia_status', 1)
                ->where('student_id', $authUser->id)
                ->where('studentMedia_type', 85)
                ->get() // Get all records instead of paginating
                ->map(function ($spmMediaList) {
                    $dateTime = new \DateTime($spmMediaList->created_at);
                    $appliedDate = $dateTime->format('Y-m-d H:i:s');
                    return [
                        "id" => $spmMediaList->id,
                        "studentMedia_name" => $spmMediaList->studentMedia_name,
                        "studentMedia_location" => $spmMediaList->studentMedia_location,
                        "category_id" => $spmMediaList->studentMedia_type,
                        "created_at" => $appliedDate,
                        "status" => $spmMediaList->studentMedia_status ? "Active" : "Inactive"
                    ];
                });
            $getSpmTrial = [
                'subjects' => $getSpmTrial, // Use 'subjects' as key
                'document' => $spmTrialMedia
            ];

            $spm['spm'] = $getTranscriptSubject;
            $spm['trial'] = $getSpmTrial;

            $getAllHigherTranscriptId = stp_core_meta::where('core_metaType', 'transcript_category')
                ->whereNotIn('id', [32, 85])
                ->get();




            $higherTranscriptSubject = stp_higher_transcript::where('student_id', $authUser->id)
                ->where('highTranscript_status', 1)
                ->get();

            // return  $higherTranscriptSubject;

            $higherTranscriptList = [];
            // return $higherTranscript;

            // foreach ($getAllHigherTranscriptId as $higherTranscript) {
            //     $result = [];
            //     $result['id'] = $higherTranscript->id;
            //     $result['name'] = $higherTranscript->core_metaName;
            //     $subject = [];
            //     $document = [];
            //     foreach ($higherTranscriptSubject as $higherSubject) {
            //         $getHigherTranscriptMedia = stp_student_media::where('studentMedia_type', $higherTranscript->id)->get();
            //         $getCGPA = stp_cgpa::where('transcript_category', $higherTranscript->id)
            //             ->where('student_id', $authUser->id)
            //             ->first();
            //         // return $getCGPA->program_name;
            //         $programName = null;
            //         $cgpa = null;

            //         if ($higherSubject->category_id == $higherTranscript->id) {
            //             $subject['subject_id'] = $higherTranscript->id;
            //             $subject['subject_name'] = $higherSubject->highTranscript_name;
            //             $subject['subject_grade'] = $higherSubject->higherTranscript_grade;
            //             $document[] = $getHigherTranscriptMedia;
            //             $programName = $getCGPA->program_name ?? null;
            //             $cgpa = $getCGPA->cgpa ?? null;
            //         }
            //     }
            //     $subjects[] = $subject;
            //     $result['subject'] = $subjects;
            //     $result['program_name'] = $programName ?? null;
            //     $result['cgpa'] = $cgpa ?? null;
            //     $result['document'] = $document;

            //     $higherTranscriptList[] = $result;
            // }

            foreach ($getAllHigherTranscriptId as $higherTranscript) {
                $result = [];
                $result['id'] = $higherTranscript->id;
                $result['name'] = $higherTranscript->core_metaName;
                $subjects = [];
                $documents = [];
                $higherTranscriptSubject = stp_higher_transcript::where('student_id', $authUser->id)
                    ->where('category_id', $higherTranscript->id)
                    ->where('highTranscript_status', 1)
                    ->get();
                $getHigherTranscriptMedia = stp_student_media::where('studentMedia_type', $higherTranscript->id)
                    ->where('student_id', $authUser->id)
                    ->get();
                $getCGPA = stp_cgpa::where('transcript_category', $higherTranscript->id)
                    ->where('student_id', $authUser->id)
                    ->first();



                // foreach ($higherTranscriptSubject as $higherSubject) {
                //     $getHigherTranscriptMedia = stp_student_media::where('studentMedia_type', $higherTranscript->id)->get();
                //     $getCGPA = stp_cgpa::where('transcript_category', $higherTranscript->id)
                //         ->where('student_id', $authUser->id)
                //         ->first();
                //     // return $getCGPA->program_name;
                //     $programName = null;
                //     $cgpa = null;

                //     if ($higherSubject->category_id == $higherTranscript->id) {
                //         $subject['subject_id'] = $higherTranscript->id;
                //         $subject['subject_name'] = $higherSubject->highTranscript_name;
                //         $subject['subject_grade'] = $higherSubject->higherTranscript_grade;
                //         $document[] = $getHigherTranscriptMedia;
                //         $programName = $getCGPA->program_name ?? null;
                //         $cgpa = $getCGPA->cgpa ?? null;
                //     }
                // }
                $subjects[] = $higherTranscriptSubject;
                $documents[] = $getHigherTranscriptMedia;
                $result['program_name'] = $getCGPA->program_name ?? null;
                $result['cgpa'] = $getCGPA->cgpa ?? null;
                $result['subject'] = $subjects;

                $result['document'] = $documents;

                $higherTranscriptList[] = $result;
            }




            $result = [
                'categories' => $categoryList,
                'transcripts' => $spm,
                'higherTranscripts' => $higherTranscriptList

            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }



    public function advertisementList(Request $request)
    {
        $request->validate([
            'advertisement_type' => 'required|integer'
        ]);
        $advertsmentList = stp_advertisement_banner::where('featured_id', $request->advertisement_type)->where('banner_status', 1)
            ->where('banner_start', '<=', now())
            ->where('banner_end', '>=', now())
            ->get();
        return response()->json([
            'success' => true,
            'data' => $advertsmentList
        ]);
    }

    public function personalityQuestionList(Request $request)
    {
        try {
            $getQuestionList = stp_personalityQuestions::where('status', 1)
                ->get()
                ->map(function ($question) {
                    return [
                        'question' => $question->question,
                        'riasec_type' => [
                            'id' => $question->question_type->id,
                            'type_name' => $question->question_type->type_name,
                        ]
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $getQuestionList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function submitTestResult(Request $request)
    {
        try {
            $authUser = Auth::user();

            $request->validate([
                'scores' => 'required'
            ]);
            $newData = [
                'student_id' => $authUser->id,
                'score' => json_encode($request->scores)
            ];

            $finduserResult = stp_personalityTestResult::where('student_id', $authUser->id)->first();
            if ($finduserResult !== null) {
                $finduserResult->update($newData);
            } else {
                $addResult = stp_personalityTestResult::insert($newData);
            }
            return response()->json([
                'success' => true,
                'data' => ['message' => "successfully save the result"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getTestResult(Request $request)
    {
        try {
            $authUser = Auth::user();
            $getResult = stp_personalityTestResult::where('student_id', $authUser->id)->where('status', 1)->get()->first();
            $result = [
                "score" => json_decode($getResult->score, true),
                "created_at" => $getResult->created_at
            ];
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'sucess' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }



    public function addInterestedCourse(Request $request)
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not authenticated',
                ], 401);
            }

            $request->validate([
                'course_id' => 'required|integer',
            ]);

            $createInterestedCourse = stp_courseInterest::create([
                'student_id' => $authUser->id,
                'course_id' => $request->course_id,
                'created_by' => $authUser->id,
                'status' => 1,
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully added the course to interest'],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error',
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
    public function removeInterestedCourse(Request $request)
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not authenticated',
                ], 401);
            }

            // Validate the request inputs
            $request->validate([
                'course_id' => 'required|integer',
                'type' => 'required|string'
            ]);

            // Determine the new status
            $status = ($request->type == 'disable') ? 0 : 1;

            // Find the interest record by course_id and the authenticated user's ID
            $interest = stp_courseInterest::where('course_id', $request->course_id)
                ->where('student_id', $authUser->id)
                ->first();

            // Check if the interest exists
            if (!$interest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course interest not found or does not belong to the authenticated user.',
                ], 404);
            }

            // Update the interest status
            $interest->update([
                'status' => $status,
                'updated_by' => $authUser->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully updated the interested course status.'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage() // Optionally include this for debugging
            ], 500);
        }
    }

    public function interestedCourseList(Request $request)
    {
        try {
            $authUser = Auth::user();

            $getStudentCourseList = stp_courseInterest::where('student_id', $authUser->id)->get()->map(function ($interestedCourse) {
                $featured = $interestedCourse->course->featured->contains(function ($item) {
                    return $item->featured_type == 30 && $item->featured_status == 1 && $item->featured_startTime < now() && $item->featured_endTime > now();
                });
                $intakeMonths = $interestedCourse->course->intake->where('intake_status', 1)
                    ->pluck('month.core_metaName')
                    ->toArray();

                return [
                    'id' => $interestedCourse->id,
                    'course_id' => $interestedCourse->course->id,
                    'school_id' => $interestedCourse->course->school->id,
                    'name' => $interestedCourse->course->course_name,
                    'school_name' => $interestedCourse->course->school->school_name,
                    'email' => $interestedCourse->course->school->school_email,
                    'description' => $interestedCourse->course->course_description,
                    'cost' => number_format($interestedCourse->course->course_cost),
                    'international_cost' => number_format($interestedCourse->course->international_cost),
                    'period' => $interestedCourse->course->course_period,
                    'featured' => $featured,
                    'intake' => $intakeMonths,
                    'category_id' => $interestedCourse->course->category_id,
                    'qualification' => $interestedCourse->course->qualification->qualification_name,
                    'mode' => $interestedCourse->course->studyMode->core_metaName,
                    'logo' => $interestedCourse->course->course_logo ?? $interestedCourse->course->school->school_logo,
                    'country' => $interestedCourse->course->school->country->country_name ?? null,
                    'country_code' => $interestedCourse->course->school->country->country_code ?? null,
                    'state' => $interestedCourse->course->school->state->state_name ?? null,
                    'institute_category' => $interestedCourse->course->school->institueCategory->core_metaName ?? null,
                    'school_location' => $interestedCourse->course->school->school_google_map_location ?? null,
                    'school_status' => $interestedCourse->course->school->school_status,
                    'status' => $interestedCourse->status
                ];
            });
            return response()->json([
                'success' => true,
                'data' => $getStudentCourseList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function riasecCourseCategory(Request $request)
    {
        try {
            $request->validate([
                'riasecType' => 'required|integer'
            ]);

            $getCourseCategory = stp_courses_category::where('riasecTypes', $request->riasecType)->get()->map(function ($courseCategory) {
                return [
                    'id' => $courseCategory->id,
                    'category_name' => $courseCategory->category_name
                ];
            });
            return response()->json([
                'success' => true,
                'data' => $getCourseCategory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function uplaodRiasecResultImage(Request $request)
    {
        try {
            // Validation for multiple images and image types
            $request->validate([
                'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000',  // For multiple images
                'imageTypes.*' => 'required|integer',  // For multiple image types
            ]);

            // Get the authenticated user
            $authUser = Auth::user();
            $data = [];

            // Loop over the images and image types to store each set of data
            foreach ($request->file('images') as $key => $image) {
                $imageName = time() . '_' . $key . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('riasecImage', $imageName, 'public');

                $existingData = stp_riasecResultImage::where('riasec_imageType', $request->input('imageTypes')[$key])
                    ->where('student_id', $authUser->id)
                    ->first(); // Use first() instead of get(), as you're only looking for one match

                if ($existingData) {
                    // If the data exists, delete the old image file
                    $oldImagePath = $existingData->resultImage_location;

                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath); // Delete the file from storage
                    }

                    $newUpdateData = [
                        'resultImage_location' => $imagePath,
                    ];
                    $existingData->update($newUpdateData);
                } else {
                    $newData = [
                        'resultImage_location' => $imagePath,
                        'riasec_imageType' => $request->input('imageTypes')[$key],
                        'student_id' => $authUser->id
                    ];

                    // Store each set of data
                    $data[] = stp_riasecResultImage::create($newData);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Successfully uploaded all images.',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getRiasecResultImage(Request $request)
    {
        try {
            $request->validate([
                'imageType' => 'required|integer',
                'id' => 'required|integer'
            ]);


            $getImage = stp_riasecResultImage::where('riasec_imageType', $request->imageType)
                ->where('student_id', $request->id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $getImage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function applyCustomSchool(Request $request)
    {
        try {
            $authUser = Auth::user();

            $request->validate([
                'certificate_media' => 'required|file|mimes:jpeg,png,jpg,gif,svg,doc,docx,pdf|max:10000', // File validation
                'course_id' => 'required|integer'
            ]);

            $checkCourse = stp_submited_form::where('student_id', $authUser->id)
                ->where('courses_id', $request->course_id)
                ->where('form_status', 2)
                ->get()
                ->first();

            if ($checkCourse) {
                throw ValidationException::withMessages([
                    "courses" => ["Your Application still pending"]
                ]);
            }

            stp_submited_form::create([
                'student_id' => $authUser->id,
                'courses_id' => $request->course_id,
                'form_status' => 2,
                'updated_by' => $authUser->id,
                'created_by' => $authUser->id,
                'created_at' => now(),
            ]);

            $checkingCertificateFile = stp_other_certificate::where('student_id', $authUser->id)
                ->where('certificate_name', 'studentIc')
                ->get()
                ->first();

            if ($checkingCertificateFile) {
                $oldFilePath = $checkingCertificateFile->certificate_media; // The file path in the database

                if (Storage::disk('public')->exists($oldFilePath)) {
                    Storage::disk('public')->delete($oldFilePath);
                }

                // Upload the new certificate if file is present
                if ($request->hasFile('certificate_media')) {
                    $image = $request->file('certificate_media');
                    $imageName = time() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('otherCertificate', $imageName, 'public');
                }

                // Update the existing record with the new file path
                $checkingCertificateFile->update([
                    'certificate_media' => $imagePath ?? $oldFilePath, // Keep old path if no new file is uploaded
                ]);
            } else {
                if ($request->hasFile('certificate_media')) {
                    $image = $request->file('certificate_media');
                    $imageName = time() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('otherCertificate', $imageName, 'public'); // Store in 'storage/app/public/images'
                }

                stp_other_certificate::create([
                    'certificate_name' => 'studentIc',
                    'certificate_media' => $imagePath ?? '',
                    'certificate_status' => 1,
                    'student_id' => $authUser->id,
                    'created_by' => $authUser->id,
                    'created_at' => now()
                ]);
            }



            $this->serviceFunctionController->notifyAdminCustomSchoolApplication($request->course_id, $authUser);


            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Apply']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function checkCourseApplicationStatus(Request $request)
    {
        try {
            $request->validate([
                'courseId' => 'required|integer'
            ]);
            $authUser = Auth::user();

            $checkApplicantExist = stp_submited_form::where('student_id', $authUser->id)
                ->where('courses_id', $request->courseId)
                ->where('form_status', 2)
                ->get()
                ->first();

            if ($checkApplicantExist) {
                throw new \Exception('You have already applied for this course and your application is under review.');
            }

            return response()->json([
                'success' => true,
                'data' => ['message' => "Course are not applied before"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function increaseNumberVisit(Request $request)
    {
        try {
            $request->validate([
                'school_id' => 'integer'
            ]);

            if (!empty($request->school_id)) {
                $schoolId = $request->school_id;
            } else {
                $request->validate([
                    'school_name' => 'required|string'
                ]);
                $school = stp_school::where('school_name', $request->school_name)->get()->first();
                $schoolId = $school->id;
            }


            $validateExsitData = stp_totalNumberVisit::where('school_id', $schoolId)
                ->whereDay('created_at', Carbon::now()->day)  // Check if the day matches the current day
                ->whereMonth('created_at', Carbon::now()->month)  // Check if the month matches the current month
                ->whereYear('created_at', Carbon::now()->year)  // Check if the year matches the current year
                ->where('status', 1)
                ->first();




            if (empty($validateExsitData)) {
                $formData = [
                    'school_id' => $schoolId,
                    'totalNumberVisit' => 1
                ];
                $createData = stp_totalNumberVisit::create($formData);
                if ($createData) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'message' => "create data successfully"
                        ]
                    ]);
                } else {
                    throw new \Exception("failed to create number visit data");
                }
            } else {
                $validateExsitData->increment('totalNumberVisit');
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => "Visit count updated successfully",
                        'totalNumberVisit' => $validateExsitData->totalNumberVisit
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateICPassport(Request $request)
    {
        try {
            $request->validate([
                'student_frontIC' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10000',
                'student_backIC' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10000',
                'student_passport' => 'nullable|file|mimes:jpeg,png,jpg,pdf,PNG|max:10000',
            ]);
            $authUser = Auth::user();
            // front ic
            if ($request->hasFile('student_frontIC')) {
                $checkFrontIC = stp_student_media::where('student_id', $authUser->id)->where('studentMedia_type', 89)->get()->first();
                if ($checkFrontIC == null) {

                    $icFrontImage = $request->file('student_frontIC');
                    $icFrontImageName = 'frontIc' . time() . '.' . $icFrontImage->getClientOriginalExtension();
                    $icFrontImagePath = $icFrontImage->storeAs('studentDocument', $icFrontImageName, 'public'); // Store in 'storage/app/public/images'

                    stp_student_media::create([
                        'studentMedia_name' => 'icFrontImage',
                        'studentMedia_type' => 89,
                        'studentMedia_format' => 'photo',
                        'studentMedia_location' => $icFrontImagePath ?? '',
                        'studentMedia_status' => 1,
                        'student_id' => $authUser->id,
                        'created_by' => $authUser->id,
                        'created_at' => now()
                    ]);
                } else {

                    Storage::delete('public/' .  $checkFrontIC->studentMedia_location);
                    $icFrontImage = $request->file('student_frontIC');
                    $icFrontImageName =  'frontIc' . time() . '.' . $icFrontImage->getClientOriginalExtension();
                    $icFrontImagePath = $icFrontImage->storeAs('studentDocument', $icFrontImageName, 'public'); // Store in 'storage/app/public/images'
                    $newFrontIcData['studentMedia_location'] = $icFrontImagePath ?? null;
                    $checkFrontIC->update($newFrontIcData);
                }
            }



            // back ic
            if ($request->hasFile('student_backIC')) {
                $checkBackIC = stp_student_media::where('student_id', $authUser->id)->where('studentMedia_type', 90)->get()->first();
                if ($checkBackIC == null) {

                    $icBackImage = $request->file('student_backIC');
                    $icBackImageName = 'backIc' .  time() . '.' . $icBackImage->getClientOriginalExtension();
                    $icBackImagePath = $icBackImage->storeAs('studentDocument', $icBackImageName, 'public'); // Store in 'storage/app/public/images'

                    stp_student_media::create([
                        'studentMedia_name' => 'icBackImage',
                        'studentMedia_type' => 90,
                        'studentMedia_format' => 'photo',
                        'studentMedia_location' => $icBackImagePath ?? '',
                        'studentMedia_status' => 1,
                        'student_id' => $authUser->id,
                        'created_by' => $authUser->id,
                        'created_at' => now()
                    ]);
                } else {
                    Storage::delete('public/' .  $checkBackIC->studentMedia_location);
                    $icBackImage = $request->file('student_backIC');
                    $icBackImageName = 'backIc' . time() . '.' . $icBackImage->getClientOriginalExtension();
                    $icBackImagePath = $icBackImage->storeAs('studentDocument', $icBackImageName, 'public'); // Store in 'storage/app/public/images'
                    $newData['studentMedia_location'] = $icBackImagePath ?? null;
                    $checkBackIC->update($newData);
                }
            }

            //passport
            if ($request->hasFile('student_passport')) {
                $checkPassport = stp_student_media::where('student_id', $authUser->id)->where('studentMedia_type', 91)->get()->first();
                if ($checkPassport == null) {
                    $passportImage = $request->file('student_passport');

                    $passportImageName = 'passport' . time() . '.' . $passportImage->getClientOriginalExtension();
                    $passportImagePath = $passportImage->storeAs('studentDocument', $passportImageName, 'public'); // Store in 'storage/app/public/images'

                    stp_student_media::create([
                        'studentMedia_name' => 'passport',
                        'studentMedia_type' => 91,
                        'studentMedia_format' => 'photo',
                        'studentMedia_location' => $passportImagePath ?? '',
                        'studentMedia_status' => 1,
                        'student_id' => $authUser->id,
                        'created_by' => $authUser->id,
                        'created_at' => now()
                    ]);
                } else {
                    Storage::delete('public/' .  $checkPassport->studentMedia_location);
                    $passportImage = $request->file('student_passport');
                    $passportImageName = 'passport' . time() . '.' . $passportImage->getClientOriginalExtension();
                    $passportImagePath = $passportImage->storeAs('studentDocument', $passportImageName, 'public'); // Store in 'storage/app/public/images'
                    $newData['studentMedia_location'] = $passportImagePath ?? null;
                    $checkPassport->update($newData);
                }
            }
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Update ic and passport successfully']
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
