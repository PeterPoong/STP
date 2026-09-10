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
use App\Models\stp_school_free_education;
use App\Models\stp_course_free_education;
use App\Models\stp_student;
use Illuminate\Support\Facades\DB;
use App\Models\stp_subject;
use App\Models\stp_tag;
use App\Models\User;
use App\Models\stp_transcript;
use App\Models\stp_submited_form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
use App\Models\stp_article_category;
use App\Models\stp_article;
use App\Models\stp_article_content_image;
use App\Models\stp_article_comment;
use App\Models\NewsletterSubscription;
use App\Services\CareerMatchingService;
use App\Services\PosterAssetService;

use App\Models\stp_totalNumberVisit;
use App\Models\stp_article_visit;
// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;


use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

use App\Rules\UniqueInArray;
use Exception;



class studentController extends Controller
{
    private function formatArticleIntentConfig(?array $intentConfig): ?array
    {
        if (!$intentConfig) {
            return null;
        }

        $categoryId = $intentConfig['related_course_category_id'] ?? null;
        $intentConfig['related_course_category_name'] = $categoryId
            ? stp_courses_category::whereKey($categoryId)->value('category_name')
            : null;
        $schoolId = $intentConfig['related_school_id'] ?? null;
        $school = $schoolId
            ? stp_school::select('school_name', 'school_slug')->find($schoolId)
            : null;
        $intentConfig['related_school_name'] = $school?->school_name;
        $intentConfig['related_school_slug'] = $school?->school_slug;

        return $intentConfig;
    }
    protected $serviceFunctionController;

    public function __construct(serviceFunctionController $serviceFunctionController)
    {
        $this->serviceFunctionController = $serviceFunctionController;
    }

    public function checkAppVersion()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => ["app version" => "1.0.1"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function platformStatistics()
    {
        try {
            $currentYear = now()->year;
            $offlineCourseCount = $this->nonNegativeStatisticsConfig('studypal.offline_course_count');
            $offlineApplicationCount = $this->nonNegativeStatisticsConfig('studypal.offline_application_count');
            $offlineApplicationYear = $this->statisticsYearConfig('studypal.offline_application_year');

            if ($offlineApplicationYear !== $currentYear) {
                $offlineApplicationCount = 0;
            }

            $statistics = Cache::remember(
                "public.platform_statistics.v2.{$currentYear}.{$offlineCourseCount}.{$offlineApplicationCount}.{$offlineApplicationYear}",
                now()->addMinutes(15),
                function () use ($offlineCourseCount, $offlineApplicationCount) {
                    $yearStart = now()->startOfYear();
                    $nextYearStart = $yearStart->copy()->addYear();

                    $applicationCount = stp_submited_form::where('form_status', '!=', 0)
                        ->where('created_at', '>=', $yearStart)
                        ->where('created_at', '<', $nextYearStart)
                        ->count();

                    $courseCount = stp_course::where('course_status', 1)
                        ->whereHas('school', function ($query) {
                            $query->whereIn('school_status', [1, 3]);
                        })
                        ->count();

                    $institutionCount = stp_school::whereIn('school_status', [1, 3])
                        ->count();

                    $topAvailableFields = stp_courses_category::query()
                        ->join('stp_courses', 'stp_courses.category_id', '=', 'stp_courses_categories.id')
                        ->join('stp_schools', 'stp_schools.id', '=', 'stp_courses.school_id')
                        ->where('stp_courses_categories.category_status', 1)
                        ->where('stp_courses.course_status', 1)
                        ->whereIn('stp_schools.school_status', [1, 3])
                        ->select([
                            'stp_courses_categories.id as category_id',
                            'stp_courses_categories.category_name as name',
                            'stp_courses_categories.category_icon',
                            DB::raw('COUNT(DISTINCT stp_courses.school_id) as institution_count'),
                        ])
                        ->groupBy(
                            'stp_courses_categories.id',
                            'stp_courses_categories.category_name',
                            'stp_courses_categories.category_icon'
                        )
                        ->orderByDesc('institution_count')
                        ->orderBy('stp_courses_categories.category_name')
                        ->orderBy('stp_courses_categories.id')
                        ->limit(5)
                        ->get()
                        ->values()
                        ->map(function ($field, $index) use ($institutionCount) {
                            $icon = trim((string) $field->category_icon);

                            return [
                                'category_id' => (int) $field->category_id,
                                'name' => $field->name,
                                'category_icon' => $icon === ''
                                    ? null
                                    : (Str::startsWith($icon, ['http://', 'https://'])
                                        ? $icon
                                        : url(Storage::url($icon))),
                                'institution_count' => (int) $field->institution_count,
                                'eligible_institution_count' => $institutionCount,
                                'percentage' => $institutionCount > 0
                                    ? round(((int) $field->institution_count / $institutionCount) * 100, 1)
                                    : 0.0,
                                'rank' => $index + 1,
                            ];
                        })
                        ->all();

                    return [
                        'student_applications' => $applicationCount + $offlineApplicationCount,
                        'courses' => $courseCount + $offlineCourseCount,
                        'institutions' => $institutionCount,
                        'top_available_fields' => $topAvailableFields,
                        'calculated_at' => now()->toIso8601String(),
                    ];
                }
            );

            return response()
                ->json([
                    'success' => true,
                    'data' => $statistics,
                ])
                ->header('Cache-Control', 'public, max-age=900');
        } catch (Exception $e) {
            Log::error('Failed to load public platform statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load platform statistics.',
            ], 500);
        }
    }

    private function nonNegativeStatisticsConfig(string $key): int
    {
        $value = config($key);

        if ($value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            Log::warning('Invalid homepage statistics configuration; using zero.', [
                'config_key' => $key,
                'configured_value' => $value,
            ]);

            return 0;
        }

        return (int) $value;
    }

    private function statisticsYearConfig(string $key): int
    {
        $value = config($key);

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 2000 || (int) $value > 9999) {
            Log::warning('Invalid homepage statistics year configuration; offline applications will be ignored.', [
                'config_key' => $key,
                'configured_value' => $value,
            ]);

            return 0;
        }

        return (int) $value;
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
                'intake_month' => 'array',
                'freeForSarawakian' => 'nullable|boolean'
            ]);

            $filterConditions = function ($query) use ($request) {
                $query->whereNotIn('school_status', [0, 4])
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
                    })
                    ->when($request->has('freeForSarawakian') && $request->freeForSarawakian === true, function ($q) {
                        // Filter schools that have free education schemes with free_education_id = 1
                        $q->whereExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('stp_school_free_education')
                                ->whereColumn('stp_school_free_education.school_id', 'stp_schools.id')
                                ->where('stp_school_free_education.free_education_id', 1)
                                ->where('stp_school_free_education.data_status', 1);
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
                ->inRandomOrder()
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
                        $monthName = $c->month->core_metaName ?? null;
                        if ($monthName && !in_array($monthName, $monthList)) {
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
                // Avoid rendering Blade pagination links in JSON to prevent cache path errors
                'links' => [],
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

    public function schoolListForDropdown(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'countryID' => 'nullable|integer',
            ]);

            // Query to get all active schools (no pagination)
            $query = stp_school::query()
                ->whereNotIn('school_status', [0, 4])
                ->when($request->filled('countryID'), function ($q) use ($request) {
                    $q->where('country_id', $request->countryID);
                })
                ->orderBy('school_name', 'asc')
                ->with(['institueCategory', 'country', 'state', 'city']);

            $schools = $query->get();

            // Transform schools to simple format for dropdown
            $transformedSchools = $schools->map(function ($school) {
                return [
                    'id' => $school->id,
                    'school_slug' => $school->school_slug,
                    'school_name' => $school->school_name,
                    'name' => $school->school_name, // Alias for compatibility
                    'institution_name' => $school->school_name, // Alias for compatibility
                    'category' => $school->institueCategory->core_metaName ?? null,
                    'logo' => $school->school_logo,
                    'country' => $school->country->country_name ?? null,
                    'state' => $school->state->state_name ?? null,
                    'city' => $school->city->city_name ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedSchools->values()->all(),
                'total' => $transformedSchools->count()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function courseDetailBySlug(Request $request)
    {
        try {
            $request->validate([
                'schoolSlug' => 'required|string',
                'courseSlug' => 'required|string'
            ]);

            // Query using slugs instead of IDs/names
            $courseList = stp_course::where('course_slug', $request->courseSlug)
                ->whereHas('school', function ($query) use ($request) {
                    $query->where('school_slug', $request->schoolSlug);
                })
                ->with(['school', 'school.country', 'school.institueCategory', 'category', 'tag.tag', 'qualification', 'studyMode', 'featured.featured', 'intake.month'])
                ->first();

            if (!$courseList) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
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

            $coverPhoto = null;
            $schoolPhoto = null;
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



            // Free education schemes (school + course)
            $schoolFreeEducationSchemes = stp_school_free_education::where('school_id', $courseList->school->id)
                ->where('stp_school_free_education.data_status', 1)
                ->join('stp_free_education', 'stp_school_free_education.free_education_id', '=', 'stp_free_education.id')
                ->select(
                    'stp_free_education.id',
                    'stp_free_education.scheme_name',
                    'stp_free_education.text_color_code',
                    'stp_free_education.background_color_code',
                    'stp_free_education.data_status'
                )
                ->get()
                ->map(function ($scheme) {
                    return [
                        'id' => (int) $scheme->id,
                        'scheme_name' => $scheme->scheme_name,
                        'text_color_code' => $scheme->text_color_code,
                        'background_color_code' => $scheme->background_color_code,
                        'data_status' => (int) $scheme->data_status,
                    ];
                })
                ->toArray();

            $courseFreeEducationSchemes = stp_course_free_education::where('course_id', $courseList->id)
                ->where('stp_course_free_education.data_status', 1)
                ->join('stp_free_education', 'stp_course_free_education.free_education_id', '=', 'stp_free_education.id')
                ->select(
                    'stp_free_education.id',
                    'stp_free_education.scheme_name',
                    'stp_free_education.data_status'
                )
                ->get()
                ->map(function ($scheme) {
                    return [
                        'id' => (int) $scheme->id,
                        'scheme_name' => $scheme->scheme_name,
                        'data_status' => (int) $scheme->data_status,
                    ];
                })
                ->toArray();

            $courseListDetail = [
                'id' => $courseList->id,
                'course_slug' => $courseList->course_slug,
                'school_slug' => $courseList->school->school_slug,
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
                'category_id' => $courseList->category_id,
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
                'tag' => $tagList,
                'school_free_education_schemes' => $schoolFreeEducationSchemes,
                'course_free_education_schemes' => $courseFreeEducationSchemes,
            ];

            return response()->json([
                'success' => true,
                'data' => $courseListDetail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
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



            // Free education schemes (school + course)
            $schoolFreeEducationSchemes = stp_school_free_education::where('school_id', $courseList->school->id)
                ->where('stp_school_free_education.data_status', 1)
                ->join('stp_free_education', 'stp_school_free_education.free_education_id', '=', 'stp_free_education.id')
                ->select(
                    'stp_free_education.id',
                    'stp_free_education.scheme_name',
                    'stp_free_education.text_color_code',
                    'stp_free_education.background_color_code',
                    'stp_free_education.data_status'
                )
                ->get()
                ->map(function ($scheme) {
                    return [
                        'id' => (int) $scheme->id,
                        'scheme_name' => $scheme->scheme_name,
                        'text_color_code' => $scheme->text_color_code,
                        'background_color_code' => $scheme->background_color_code,
                        'data_status' => (int) $scheme->data_status,
                    ];
                })
                ->toArray();

            $courseFreeEducationSchemes = stp_course_free_education::where('course_id', $courseList->id)
                ->where('stp_course_free_education.data_status', 1)
                ->join('stp_free_education', 'stp_course_free_education.free_education_id', '=', 'stp_free_education.id')
                ->select(
                    'stp_free_education.id',
                    'stp_free_education.scheme_name',
                    'stp_free_education.data_status'
                )
                ->get()
                ->map(function ($scheme) {
                    return [
                        'id' => (int) $scheme->id,
                        'scheme_name' => $scheme->scheme_name,
                        'data_status' => (int) $scheme->data_status,
                    ];
                })
                ->toArray();

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
                'category_id' => $courseList->category_id,
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
                'tag' => $tagList,
                'school_free_education_schemes' => $schoolFreeEducationSchemes,
                'course_free_education_schemes' => $courseFreeEducationSchemes,
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

    public function schoolDetailBySlug(Request $request)
    {
        try {
            $request->validate([
                'slug' => 'required|string'
            ]);

            // Query using slug instead of ID
            $school = stp_school::with(['courses.featured', 'courses.category', 'courses.qualification', 'courses.studyMode', 'courses.intake.month', 'country', 'institueCategory', 'state', 'city'])
                ->where('school_slug', $request->slug)
                ->first();

            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'School not found'
                ], 404);
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

                        // Check if course is featured (featured_type = 30, active featured status)
                        $isFeatured = $course->featured->contains(function ($featured) {
                            return $featured->featured_type == 30
                                && $featured->featured_status == 1
                                && $featured->featured_startTime < now()
                                && $featured->featured_endTime > now();
                        });

                        return [
                            'id' => $course->id,
                            'course_name' => $course->course_name,
                            'course_slug' => $course->course_slug,
                            'school_slug' => $course->school->school_slug,
                            'course_cost' => number_format($course->course_cost),
                            'international_cost' => number_format($course->international_cost),
                            'course_period' => $course->course_period,
                            'course_intake' => $monthList,
                            'category' => $course->category->category_name,
                            'qualification' => $course->qualification->qualification_name,
                            'study_mode' => $course->studyMode->core_metaName ?? null,
                            'course_logo' => $course->course_logo,
                            'featured' => $isFeatured
                        ];
                    }
                    return null;
                })
                ->filter() // Removes null values
                ->values();

            $schoolDetail = [
                'id' => $school->id,
                'school_slug' => $school->school_slug,
                'name' => $school->school_name,
                'school_email' => $school->school_email,
                'school_contactNo' => $school->school_contactNo ?? null,
                'school_countryCode' => $school->school_countryCode ?? null,
                'person_inChargeEmail' => $school->person_inChargeEmail ?? null,
                'person_inChargeNumber' => $school->person_inChargeNumber ?? null,
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
                'school_website' => $school->school_officalWebsite ?? null,
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
                $school = stp_school::with('courses.featured')->find($request->id);
            } else {
                $request->validate([
                    'schoolName' => 'required|string'
                ]);
                $school = stp_school::with('courses.featured')->where('school_name', $request->schoolName)->get()->first();
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
                        
                        // Check if course is featured (featured_type = 30, active featured status)
                        $isFeatured = $course->featured->contains(function ($featured) {
                            return $featured->featured_type == 30 
                                && $featured->featured_status == 1 
                                && $featured->featured_startTime < now() 
                                && $featured->featured_endTime > now();
                        });
                        
                        return [
                            'id' => $course->id,
                            'course_name' => $course->course_name,
                            'course_slug' => $course->course_slug,
                            'school_slug' => $course->school->school_slug,
                            'course_cost' => number_format($course->course_cost),
                            'international_cost' => number_format($course->international_cost),
                            'course_period' => $course->course_period,
                            'course_intake' => $monthList,
                            'category' => $course->category->category_name,
                            'qualification' => $course->qualification->qualification_name,
                            'study_mode' => $course->studyMode->core_metaName ?? null,
                            'course_logo' => $course->course_logo,
                            'featured' => $isFeatured
                        ];
                    }
                    return null;
                })
                ->filter() // Removes null values
                ->values();




            $schoolDetail = [
                'id' => $school->id,
                'school_slug' => $school->school_slug,
                'name' => $school->school_name,
                'school_email' => $school->school_email,
                'school_contactNo' => $school->school_contactNo ?? null,
                'school_countryCode' => $school->school_countryCode ?? null,
                'person_inChargeEmail' => $school->person_inChargeEmail ?? null,
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
                'school_website' => $school->school_officalWebsite ?? null,
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
                    
                    // Get school cover photo
                    $coverPhoto = null;
                    foreach ($courses->courses->school->media as $photo) {
                        if ($photo->schoolMedia_type == 66) {
                            $coverPhoto = $photo->schoolMedia_location;
                            break;
                        }
                    }
                    
                    return [
                        "id" => $courses->courses->id,
                        "school_id" => $courses->courses->school->id,
                        "school_slug" => $courses->courses->school->school_slug,
                        "course_slug" => $courses->courses->course_slug,
                        "course_name" => $courses->courses->course_name,
                        "course_logo" => $logo,
                        "course_qualification" => $courses->courses->qualification->qualification_name,
                        "course_qualification_color" => $courses->courses->qualification->qualification_color_code,
                        'course_school' => $courses->courses->school->school_name,
                        'location' => $courses->courses->school->city->city_name ?? null,
                        'school_cover' => $coverPhoto,
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
            // Debug: Log the request parameters
            \Log::info('CourseList API Request:', $request->all());
            
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
                'intake' => 'array',
                'school_id' => 'integer',
                'freeForSarawakian' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0'
            ]);

            $filterConditions = function ($query) use ($request) {
                $query->whereHas('school', function ($q) {
                    $q->whereIn('school_status', ["1", "3"]);
                })
                    ->where('stp_courses.course_status', 1)
                    ->when($request->filled('school_id'), function ($q) use ($request) {
                        $q->where('stp_courses.school_id', $request->school_id);
                    })
                    ->when($request->filled('qualification'), function ($q) use ($request) {
                        $q->where('stp_courses.qualification_id', $request->qualification);
                    })
                    ->when($request->filled('category'), function ($q) use ($request) {
                        $q->whereIn('stp_courses.category_id', $request->category);
                    })
                    ->when($request->filled('search'), function ($q) use ($request) {
                        $q->where(function ($subQuery) use ($request) {
                            $subQuery->where('stp_courses.course_name', 'like', '%' . $request->search . '%')
                                ->orWhereHas('school', function ($q) use ($request) {
                                    $q->where('school_name', 'like', '%' . $request->search . '%');
                                });
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
                        $q->whereIn('stp_courses.study_mode', $request->studyMode);
                    })
                    ->when($request->filled('location'), function ($q) use ($request) {
                        $q->whereHas('school', function ($q) use ($request) {
                            $q->whereIn('state_id', $request->location);
                        });
                    })
                    ->when($request->filled('tuitionFee'), function ($q) use ($request) {
                        $q->where('stp_courses.course_cost', '<=', $request->tuitionFee);
                    })
                    ->when($request->filled('intake'), function ($q) use ($request) {
                        $q->whereHas('intake', function ($q) use ($request) {
                            $q->whereIn('intake_month', $request->intake);
                        });
                    })
                    ->when($request->has('freeForSarawakian') && $request->freeForSarawakian === true, function ($q) {
                        // Debug: Log that the filter is being applied
                        \Log::info('Applying freeForSarawakian filter');
                        
                        // Debug: Check if there are any records in the tables
                        $schoolFreeCount = DB::table('stp_school_free_education')
                            ->where('free_education_id', 1)
                            ->where('data_status', 1)
                            ->count();
                        $courseFreeCount = DB::table('stp_course_free_education')
                            ->where('free_education_id', 1)
                            ->where('data_status', 1)
                            ->count();
                        
                        \Log::info("School free education records with id=1: {$schoolFreeCount}");
                        \Log::info("Course free education records with id=1: {$courseFreeCount}");
                        
                        $q->whereExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('stp_school_free_education')
                                ->whereColumn('stp_school_free_education.school_id', 'stp_courses.school_id')
                                ->where('stp_school_free_education.free_education_id', 1)
                                ->where('stp_school_free_education.data_status', 1);
                        })
                        ->whereExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('stp_course_free_education')
                                ->whereColumn('stp_course_free_education.course_id', 'stp_courses.id')
                                ->where('stp_course_free_education.free_education_id', 1)
                                ->where('stp_course_free_education.data_status', 1);
                        });
                    });
            };


            // Support both pagination (page-based) and infinite scroll (offset-based)
            $useOffset = $request->has('offset');
            $perPage = $request->get('per_page', 40);
            $featuredLimit = 5;

            // Only load featured courses on first page/initial load (offset = 0 or page = 1)
            $loadFeatured = false;
            if ($useOffset) {
                $offset = $request->get('offset', 0);
                $loadFeatured = ($offset === 0);
            } else {
                $page = $request->get('page', 1);
                $offset = ($page - 1) * $perPage;
                $loadFeatured = ($page === 1);
            }

            // Randomly select featured courses (only on first load)
            $featuredCourses = collect([]);
            if ($loadFeatured) {
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
            }

            // Calculate limit for non-featured courses to fill remaining slots
            $nonFeaturedLimit = $perPage - $featuredCourses->count();
            
            // Adjust offset for non-featured courses
            // If this is the first load (offset = 0), we've loaded 0 non-featured so far
            if ($offset === 0) {
                $nonFeaturedOffset = 0;
            } else {
                // For subsequent loads, we need to account for featured courses from first load
                // Get the count of featured courses that would have been loaded on first page
                $firstLoadFeaturedCount = stp_course::query()
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
                    ->count();
                // Cap at featuredLimit (5)
                $firstLoadFeaturedCount = min($firstLoadFeaturedCount, $featuredLimit);
                // Offset represents total items loaded, subtract featured to get non-featured offset
                $nonFeaturedOffset = max(0, $offset - $firstLoadFeaturedCount);
            }

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
                ->skip($nonFeaturedOffset)
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

            // Transform the courses as per requirements (helper function)
            $transformCourse = function ($course) {
                $featured = $course->featured->contains(function ($item) {
                    return $item->featured_type == 30 && $item->featured_status == 1 && $item->featured_startTime < now() && $item->featured_endTime > now();
                });

                $intakeMonths = $course->intake->where('intake_status', 1)
                    ->pluck('month.core_metaName')
                    ->toArray();

                $coverPhoto = null;
                foreach ($course->school->media as $photo) {
                    if ($photo->schoolMedia_type == 66) {
                        $coverPhoto = $photo->schoolMedia_location;
                        break;
                    }
                }

                // Get school free education schemes
                $schoolFreeEducationSchemes = stp_school_free_education::where('school_id', $course->school->id)
                    ->where('stp_school_free_education.data_status', 1)
                    ->join('stp_free_education', 'stp_school_free_education.free_education_id', '=', 'stp_free_education.id')
                    ->select('stp_free_education.id', 'stp_free_education.scheme_name', 'stp_free_education.text_color_code', 'stp_free_education.background_color_code', 'stp_free_education.data_status')
                    ->get()
                    ->map(function($scheme) {
                        return [
                            'id' => (int) $scheme->id,
                            'scheme_name' => $scheme->scheme_name,
                            'text_color_code' => $scheme->text_color_code,
                            'background_color_code' => $scheme->background_color_code,
                            'data_status' => (int) $scheme->data_status
                        ];
                    })
                    ->toArray();

                // Get course free education schemes
                $courseFreeEducationSchemes = stp_course_free_education::where('course_id', $course->id)
                    ->where('stp_course_free_education.data_status', 1)
                    ->join('stp_free_education', 'stp_course_free_education.free_education_id', '=', 'stp_free_education.id')
                    ->select('stp_free_education.id', 'stp_free_education.scheme_name', 'stp_free_education.data_status')
                    ->get()
                    ->map(function($scheme) {
                        return [
                            'id' => (int) $scheme->id,
                            'scheme_name' => $scheme->scheme_name,
                            'data_status' => (int) $scheme->data_status
                        ];
                    })
                    ->toArray();

                return [
                    'school_id' => $course->school->id,
                    'school_slug' => $course->school->school_slug,
                    'email' => $course->school->school_email,
                    'school_cover' => $coverPhoto,
                    'id' => $course->id,
                    'course_slug' => $course->course_slug,
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
                    'state_id' => $course->school->state->id ?? null,
                    'institute_category' => $course->school->institueCategory->core_metaName ?? null,
                    'school_location' => $course->school->school_google_map_location,
                    'course_status' => $course->course_status,
                    'school_free_education_schemes' => $schoolFreeEducationSchemes,
                    'course_free_education_schemes' => $courseFreeEducationSchemes
                ];
            };

            // Transform courses
            $transformedCourses = $courses->map($transformCourse)->values();

            // Check if free education id=1 has data_status=1
            $freeEducationId1Status = \DB::table('stp_free_education')
                ->where('id', 1)
                ->value('data_status');
            $isFreeEducationId1Active = ($freeEducationId1Status == 1);

            // For offset-based requests (infinite scroll), return simple response
            if ($useOffset) {
                $hasMore = ($offset + $courses->count()) < ($totalFeatured + $totalNonFeatured);
                return response()->json([
                    'data' => $transformedCourses,
                    'total' => $totalFeatured + $totalNonFeatured,
                    'has_more' => $hasMore,
                    'loaded_count' => $offset + $courses->count(),
                    'free_education_id1_active' => $isFreeEducationId1Active
                ]);
            }

            // Paginate the combined result (for page-based requests)
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $transformedCourses,
                $totalFeatured + $totalNonFeatured,
                $perPage,
                $page,
                ['path' => $request->url()]
            );

            // Add custom metadata to paginator
            $paginatorData = $paginator->toArray();
            $paginatorData['free_education_id1_active'] = $isFreeEducationId1Active;

            // Return the paginated response in the desired format
            return response()->json($paginatorData);

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
                // Explicitly return state_id for front-end badge/filter checks (e.g., Sarawak)
                'state_id' => $authUser->detail->state_id ?? $authUser->detail->state->id ?? null,
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

            $existData = stp_higher_transcript::where('category_id', $request->category)
                ->where('student_id', $authUser->id)
                ->get();
            $existName = $existData->map(function ($test) {
                return $test->highTranscript_name;
            });
            $dataNames = collect(array_column($data, 'name'));
            $missingItems = array_diff($existName->toArray(), $dataNames->toArray());
            $missingItemsValue = array_values($missingItems);

            if (count($missingItemsValue) > 0) {
                foreach ($missingItemsValue as $removeData) {
                    stp_higher_transcript::where('highTranscript_name', $removeData)
                        ->where('category_id', $request->category)
                        ->where('student_id', $authUser->id)
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
            $missingProfileFields = [];

            if (blank($authUser->student_nationality)) {
                $missingProfileFields[] = 'nationality';
            }

            if (blank($authUser->student_icNumber)) {
                $missingProfileFields[] = 'identity_number';
            }

            if (!$authUser->detail?->gender) {
                $missingProfileFields[] = 'gender';
            }

            if (!empty($missingProfileFields)) {
                throw ValidationException::withMessages([
                    'profile' => [
                        'Complete your nationality, identity number, and gender before applying.'
                    ],
                    'missing_fields' => $missingProfileFields,
                ]);
            }

            $studentID = $authUser->id;
            $courseID = $request->courseID;
            // Use database transaction to ensure atomicity
            $newApplicant = DB::transaction(function () use ($studentID, $courseID, $authUser) {
                // Use lockForUpdate to prevent race conditions

                // check if there is already an applicant with this course id, student id and status as pending
                $checkingCourse = stp_submited_form::where('courses_id', $courseID)
                    ->where('student_id', $studentID)
                    ->whereIn('form_status', [2, 5, 6])
                    ->lockForUpdate()
                    ->first();

                // form status 2 = pending
                // form status 3 = rejected
                // form status 4 = accepted

                // form status 0 = disable/soft delete
                // form status 1 = active

                if ($checkingCourse != null) {
                    // if form_status == 3 or 4, create new record
                    // else throw error 'You had already Applied this course'
                    throw ValidationException::withMessages([
                        "courses" => ['You had already Applied this course']
                    ]);

                    // if ($checkingCourse->form_status == 2) {
                    //     throw ValidationException::withMessages([
                    //         "courses" => ['You had already Applied this course']
                    //     ]);
                    // } else {
                    //     $checkingCourse->update([
                    //         'form_status' => 2,
                    //     ]);
                    //     return $checkingCourse;
                    // }
                } else {
                    return stp_submited_form::create([
                        'student_id' => $studentID,
                        'courses_id' => $courseID,
                        'form_status' => 2,
                        'created_by' => $authUser->id,
                        'created_at' => now(),
                    ]);
                }
            });

            if ($newApplicant->course->school->id == 115 || $newApplicant->course->school->id == 118) {
                $this->serviceFunctionController->notifyAdminCustomSchoolApplication($request->courseID, $authUser);
            } else {
                $this->serviceFunctionController->sendSchoolEmail($request->courseID, $authUser, $newApplicant->id);
            }

            // Send confirmation email to the applicant
            $this->serviceFunctionController->sendCourseAppliedConfirmation($authUser, $newApplicant->course, now());



            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Applied for the Course']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors(),
                'error' => $e->errors(),
            ], 422);
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
                'cgpa' => 'nullable|numeric'
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
                'cgpa' => 'nullable|numeric'
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
                ->where('cgpa_status', true)
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
                ->whereIn('form_status', [2, 5, 6])
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
                        "status" => $submittedForm->form_status,
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
                ->whereIn('form_status', [0, 3, 4, 7])
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
                    $status = match ((int)$submittedForm->form_status) {
                        0 => "Withdrawal",
                        3 => "Rejected",
                        4 => "Accepted",
                        7 => "Withdrawn",
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
                'form_status' => 7
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

            if (
                $request->student_nationality === 'malaysian' &&
                !preg_match('/^\d{12}$/', $request->ic)
            ) {
                throw ValidationException::withMessages([
                    'ic' => ['Malaysian IC must be exactly 12 digits.'],
                ]);
            }


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
                'porfilePic' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000' // Image validation
            ]);
            $authUser = Auth::user();

            if (!empty($authUser->student_profilePic)) {
                Storage::delete('public/' . $authUser->student_profilePic);
            }

            $image = $request->file('porfilePic');
            $baseName = (string) time();
            $destinationPath = public_path('storage/studentProfilePic');

            $result = $this->convertImageToWebP($image, $destinationPath, $baseName);

            if (!$result['success'] || empty($result['path'])) {
                throw new \Exception($result['message'] ?? 'Image conversion failed');
            }

            $authUser->update([
                'student_profilePic' => $result['path'],
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Update profile successfully',
                    'profilePic' => $result['path'] // WebP (or SVG) path so UI and comment section show updated pic
                ]
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

    /**
     * Convert uploaded image to WebP (or keep SVG as-is). Used for profile pic.
     * @param \Illuminate\Http\UploadedFile $imageSource
     * @param string $destinationPath Full path to destination directory
     * @param string $imageName Base name without extension
     * @return array ['path' => relative path for DB, 'success' => bool, 'message' => string]
     */
    private function convertImageToWebP($imageSource, $destinationPath, $imageName): array
    {
        $originalExtension = strtolower($imageSource->getClientOriginalExtension() ?? 'jpg');

        try {
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            if ($originalExtension === 'svg') {
                $finalPath = $destinationPath . '/' . $imageName . '.svg';
                $imageSource->move($destinationPath, $imageName . '.svg');
                $relativePath = 'studentProfilePic/' . $imageName . '.svg';
                return ['path' => $relativePath, 'success' => true, 'message' => null];
            }

            $gdLoaded = extension_loaded('gd');
            $imagickLoaded = extension_loaded('imagick');
            if (!$gdLoaded && !$imagickLoaded) {
                throw new \Exception('GD or Imagick extension is required for image processing');
            }
            if ($gdLoaded) {
                $gdInfo = gd_info();
                if (!isset($gdInfo['WebP Support']) || !$gdInfo['WebP Support']) {
                    throw new \Exception('WebP is not supported by GD extension');
                }
            }

            $tempPath = $imageSource->getRealPath();
            $driver = $gdLoaded ? new GdDriver() : new ImagickDriver();
            $manager = new ImageManager($driver);
            $img = $manager->read($tempPath);
            $webpPath = $destinationPath . '/' . $imageName . '.webp';
            $img->toWebp(90)->save($webpPath);

            if (!file_exists($webpPath)) {
                throw new \Exception('WebP file was not created');
            }

            $relativePath = 'studentProfilePic/' . $imageName . '.webp';
            return ['path' => $relativePath, 'success' => true, 'message' => null];
        } catch (\Exception $e) {
            Log::error('WebP conversion failed for profile pic: ' . $e->getMessage());
            try {
                $fallbackExt = in_array($originalExtension, ['jpeg', 'jpg', 'png', 'gif']) ? $originalExtension : 'jpg';
                $fallbackPath = $destinationPath . '/' . $imageName . '.' . $fallbackExt;
                $imageSource->move($destinationPath, $imageName . '.' . $fallbackExt);
                $relativePath = 'studentProfilePic/' . $imageName . '.' . $fallbackExt;
                return ['path' => $relativePath, 'success' => true, 'message' => null];
            } catch (\Exception $fallbackEx) {
                return ['path' => null, 'success' => false, 'message' => $fallbackEx->getMessage()];
            }
        }
    }

    public function resetStudentPassword(Request $request)
    {
        try {
            $request->validate([
                'currentPassword' => ['required', 'string'],
                'newPassword' => ['required', Password::defaults(), 'different:currentPassword'],
                'confirmPassword' => ['required', 'string', 'same:newPassword']
            ]);
            $authUser = Auth::user();
            if (!Hash::check($request->currentPassword, $authUser->student_password)) {
                throw ValidationException::withMessages(["password does not match"]);
            }

            $passwordSecurityStatus = app(\App\Services\PasswordSecurityService::class)->assess($request->newPassword);
            $authUser->update([
                'student_password' => Hash::make($request->newPassword),
                'password_security_status' => $passwordSecurityStatus,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['messenger' => "Successfully reset password"],
                'password_security' => app(\App\Services\PasswordSecurityService::class)->response($passwordSecurityStatus),
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

            // Increment the reminder_clicked counter
            $form->increment('reminder_clicked');

            $authUser = Auth::user();
            $this->serviceFunctionController->sendReminder($form, $authUser, $request->formID);
            return response()->json([
                'success' => true,
                'data' => 'Send Reminder successfully',
                'reminder_count' => $form->fresh()->reminder_clicked
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
                'newPassword' => ['required', Password::defaults()],
                'confirmPassword' => ['required', 'string', 'same:newPassword']
            ]);

            $findStudent = stp_student::find($request->id);
            if ($findStudent->student_status != 3) {
                throw ValidationException::withMessages(['account' => 'Account is not dummy anymore']);
            }

            $passwordSecurityStatus = app(\App\Services\PasswordSecurityService::class)->assess($request->newPassword);
            $findStudent->update([
                'student_password' => Hash::make($request->newPassword),
                'password_security_status' => $passwordSecurityStatus,
                'student_status' => 1
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => "Successfully Reset Password"
                ],
                'password_security' => app(\App\Services\PasswordSecurityService::class)->response($passwordSecurityStatus),
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
                    $course = $featured->courses;
                    $intakeMonths = $course->intake->pluck('month.core_metaName')->toArray();

                    // Get school free education schemes
                    $schoolFreeEducationSchemes = stp_school_free_education::where('school_id', $course->school->id)
                        ->where('stp_school_free_education.data_status', 1)
                        ->join('stp_free_education', 'stp_school_free_education.free_education_id', '=', 'stp_free_education.id')
                        ->select(
                            'stp_free_education.id',
                            'stp_free_education.scheme_name',
                            'stp_free_education.text_color_code',
                            'stp_free_education.background_color_code',
                            'stp_free_education.data_status'
                        )
                        ->get()
                        ->map(function ($scheme) {
                            return [
                                'id' => (int) $scheme->id,
                                'scheme_name' => $scheme->scheme_name,
                                'text_color_code' => $scheme->text_color_code,
                                'background_color_code' => $scheme->background_color_code,
                                'data_status' => (int) $scheme->data_status,
                            ];
                        })
                        ->toArray();

                    // Get course free education schemes
                    $courseFreeEducationSchemes = stp_course_free_education::where('course_id', $course->id)
                        ->where('stp_course_free_education.data_status', 1)
                        ->join('stp_free_education', 'stp_course_free_education.free_education_id', '=', 'stp_free_education.id')
                        ->select(
                            'stp_free_education.id',
                            'stp_free_education.scheme_name',
                            'stp_free_education.data_status'
                        )
                        ->get()
                        ->map(function ($scheme) {
                            return [
                                'id' => (int) $scheme->id,
                                'scheme_name' => $scheme->scheme_name,
                                'data_status' => (int) $scheme->data_status,
                            ];
                        })
                        ->toArray();
                    
                    return [
                        'id' => $course->id,
                        'course_slug' => $course->course_slug,
                        'course_id' => $course->id,
                        'course_name' => $course->course_name,
                        'course_logo' => $course->course_logo ?? $course->school->school_logo,
                        'course_qualification' => $course->qualification->qualification_name,
                        'course_qualification_color' => $course->qualification->qualification_color_code,
                        'school_id' => $course->school->id,
                        'school_slug' => $course->school->school_slug,
                        'school_category' => $course->school->institueCategory->core_metaName,
                        'school_email' => $course->school->school_email,
                        'course_school' => $course->school->school_name,
                        'state' => $course->school->state->state_name ?? null,
                        'country' => $course->school->country->country_name ?? null,
                        'country_code' => $course->school->country->country_code ?? null,
                        'mode' => $course->studyMode->core_metaName ?? null,
                        'period' => $course->course_period ?? null,
                        'category_name' => $course->category->category_name ?? null,
                        'intake' => $intakeMonths,
                        'cost' => number_format($course->course_cost),
                        'international_cost' => number_format($course->international_cost),
                        'affiliating_university' => $course->school->school_name,
                        'featured' => true, // All courses from featuredCourseList are featured
                        'school_free_education_schemes' => $schoolFreeEducationSchemes,
                        'course_free_education_schemes' => $courseFreeEducationSchemes,
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

    public function otherCoursesFromUniversity(Request $request)
    {
        try {
            $request->validate([
                'schoolId' => 'required|integer',
                'courseId' => 'required|integer',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $limit = $request->get('limit', 4); // Default to 4 courses

            $otherCourses = stp_course::where('school_id', $request->schoolId)
                ->where('id', '!=', $request->courseId)
                ->where('course_status', 1)
                ->whereHas('school', function ($query) {
                    $query->whereIn('school_status', [1, 3]);
                })
                ->inRandomOrder()
                ->limit($limit)
                ->get()
                ->map(function ($course) {
                    $intakeMonths = $course->intake->where('intake_status', 1)->pluck('month.core_metaName')->toArray();

                    // Check if course is featured (featured_type 30 for course listing page)
                    $featured = $course->featured->contains(function ($item) {
                        return $item->featured_type == 30 && $item->featured_status == 1 && $item->featured_startTime < now() && $item->featured_endTime > now();
                    });

                    // Get school free education schemes
                    $schoolFreeEducationSchemes = stp_school_free_education::where('school_id', $course->school->id)
                        ->where('stp_school_free_education.data_status', 1)
                        ->join('stp_free_education', 'stp_school_free_education.free_education_id', '=', 'stp_free_education.id')
                        ->select(
                            'stp_free_education.id',
                            'stp_free_education.scheme_name',
                            'stp_free_education.text_color_code',
                            'stp_free_education.background_color_code',
                            'stp_free_education.data_status'
                        )
                        ->get()
                        ->map(function ($scheme) {
                            return [
                                'id' => (int) $scheme->id,
                                'scheme_name' => $scheme->scheme_name,
                                'text_color_code' => $scheme->text_color_code,
                                'background_color_code' => $scheme->background_color_code,
                                'data_status' => (int) $scheme->data_status,
                            ];
                        })
                        ->toArray();

                    // Get course free education schemes
                    $courseFreeEducationSchemes = stp_course_free_education::where('course_id', $course->id)
                        ->where('stp_course_free_education.data_status', 1)
                        ->join('stp_free_education', 'stp_course_free_education.free_education_id', '=', 'stp_free_education.id')
                        ->select(
                            'stp_free_education.id',
                            'stp_free_education.scheme_name',
                            'stp_free_education.data_status'
                        )
                        ->get()
                        ->map(function ($scheme) {
                            return [
                                'id' => (int) $scheme->id,
                                'scheme_name' => $scheme->scheme_name,
                                'data_status' => (int) $scheme->data_status,
                            ];
                        })
                        ->toArray();

                    return [
                        'id' => $course->id,
                        'course_slug' => $course->course_slug,
                        'course_id' => $course->id,
                        'course_name' => $course->course_name,
                        'course_logo' => $course->course_logo ?? $course->school->school_logo,
                        'course_qualification' => $course->qualification->qualification_name,
                        'course_qualification_color' => $course->qualification->qualification_color_code,
                        'school_id' => $course->school->id,
                        'school_slug' => $course->school->school_slug,
                        'school_category' => $course->school->institueCategory->core_metaName,
                        'school_email' => $course->school->school_email,
                        'course_school' => $course->school->school_name,
                        'state' => $course->school->state->state_name ?? null,
                        'country' => $course->school->country->country_name ?? null,
                        'country_code' => $course->school->country->country_code ?? null,
                        'mode' => $course->studyMode->core_metaName ?? null,
                        'period' => $course->course_period ?? null,
                        'category_name' => $course->category->category_name ?? null,
                        'intake' => $intakeMonths,
                        'cost' => number_format($course->course_cost),
                        'international_cost' => number_format($course->international_cost),
                        'affiliating_university' => $course->school->school_name,
                        'featured' => $featured,
                        'school_free_education_schemes' => $schoolFreeEducationSchemes,
                        'course_free_education_schemes' => $courseFreeEducationSchemes,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $otherCourses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function coursesMightInterested(Request $request)
    {
        try {
            $request->validate([
                'categoryId' => 'required|integer',
                'courseId' => 'required|integer',
                'limit' => 'nullable|integer|min:1|max:10'
            ]);

            $limit = $request->get('limit', 10); // Default to 10 courses, max 10

            $coursesMightInterested = stp_course::where('category_id', $request->categoryId)
                ->where('id', '!=', $request->courseId)
                ->where('course_status', 1)
                ->whereHas('school', function ($query) {
                    $query->whereIn('school_status', [1, 3]);
                })
                ->inRandomOrder()
                ->limit($limit)
                ->get()
                ->map(function ($course) {
                    $intakeMonths = $course->intake->where('intake_status', 1)->pluck('month.core_metaName')->toArray();

                    // Check if course is featured (featured_type 30 for course listing page)
                    $featured = $course->featured->contains(function ($item) {
                        return $item->featured_type == 30 && $item->featured_status == 1 && $item->featured_startTime < now() && $item->featured_endTime > now();
                    });

                    // Get school free education schemes
                    $schoolFreeEducationSchemes = stp_school_free_education::where('school_id', $course->school->id)
                        ->where('stp_school_free_education.data_status', 1)
                        ->join('stp_free_education', 'stp_school_free_education.free_education_id', '=', 'stp_free_education.id')
                        ->select(
                            'stp_free_education.id',
                            'stp_free_education.scheme_name',
                            'stp_free_education.text_color_code',
                            'stp_free_education.background_color_code',
                            'stp_free_education.data_status'
                        )
                        ->get()
                        ->map(function ($scheme) {
                            return [
                                'id' => (int) $scheme->id,
                                'scheme_name' => $scheme->scheme_name,
                                'text_color_code' => $scheme->text_color_code,
                                'background_color_code' => $scheme->background_color_code,
                                'data_status' => (int) $scheme->data_status,
                            ];
                        })
                        ->toArray();

                    // Get course free education schemes
                    $courseFreeEducationSchemes = stp_course_free_education::where('course_id', $course->id)
                        ->where('stp_course_free_education.data_status', 1)
                        ->join('stp_free_education', 'stp_course_free_education.free_education_id', '=', 'stp_free_education.id')
                        ->select(
                            'stp_free_education.id',
                            'stp_free_education.scheme_name',
                            'stp_free_education.data_status'
                        )
                        ->get()
                        ->map(function ($scheme) {
                            return [
                                'id' => (int) $scheme->id,
                                'scheme_name' => $scheme->scheme_name,
                                'data_status' => (int) $scheme->data_status,
                            ];
                        })
                        ->toArray();

                    return [
                        'id' => $course->id,
                        'course_slug' => $course->course_slug,
                        'course_id' => $course->id,
                        'course_name' => $course->course_name,
                        'course_logo' => $course->course_logo ?? $course->school->school_logo,
                        'course_qualification' => $course->qualification->qualification_name,
                        'course_qualification_color' => $course->qualification->qualification_color_code,
                        'school_id' => $course->school->id,
                        'school_slug' => $course->school->school_slug,
                        'school_category' => $course->school->institueCategory->core_metaName,
                        'school_email' => $course->school->school_email,
                        'course_school' => $course->school->school_name,
                        'state' => $course->school->state->state_name ?? null,
                        'country' => $course->school->country->country_name ?? null,
                        'country_code' => $course->school->country->country_code ?? null,
                        'mode' => $course->studyMode->core_metaName ?? null,
                        'period' => $course->course_period ?? null,
                        'category_name' => $course->category->category_name ?? null,
                        'intake' => $intakeMonths,
                        'cost' => number_format($course->course_cost),
                        'international_cost' => number_format($course->international_cost),
                        'affiliating_university' => $course->school->school_name,
                        'featured' => $featured,
                        'school_free_education_schemes' => $schoolFreeEducationSchemes,
                        'course_free_education_schemes' => $courseFreeEducationSchemes,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $coursesMightInterested
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
                    ->where('cgpa_status', true)
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
                $result['cgpaId'] = $getCGPA->id ?? null;
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

    public function submitTestResult(Request $request, CareerMatchingService $careerMatchingService)
    {
        $validated = $request->validate([
            'scores' => ['required', 'array', 'size:6'],
            'scores.*' => ['required', 'numeric', 'between:0,100'],
        ]);
        $normalizedScores = $careerMatchingService->normalizeScores($validated['scores']);

        try {
            $authUser = Auth::user();
            $careerMatches = $careerMatchingService->match($normalizedScores);
            $newData = [
                'student_id' => $authUser->id,
                'score' => json_encode($normalizedScores),
                'career_matches' => $careerMatches,
                'career_match_version' => CareerMatchingService::VERSION,
                'status' => 1,
            ];

            $finduserResult = stp_personalityTestResult::where('student_id', $authUser->id)->first();
            if ($finduserResult !== null) {
                $finduserResult->update($newData);
            } else {
                stp_personalityTestResult::create($newData);
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => "successfully save the result",
                    'scores' => $normalizedScores,
                    'career_matches' => $careerMatchingService->toApiPayload($careerMatches),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTestResult(Request $request, CareerMatchingService $careerMatchingService)
    {
        try {
            $authUser = Auth::user();
            $getResult = stp_personalityTestResult::where('student_id', $authUser->id)->where('status', 1)->get()->first();

            if (!$getResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active RIASEC result was found.',
                ], 404);
            }

            $scores = json_decode($getResult->score, true);
            $normalizedScores = $careerMatchingService->normalizeScores(is_array($scores) ? $scores : []);
            $careerMatches = $getResult->career_matches;

            if (!is_array($careerMatches) || $getResult->career_match_version === null) {
                $careerMatches = $careerMatchingService->match($normalizedScores);
                $getResult->update([
                    'score' => json_encode($normalizedScores),
                    'career_matches' => $careerMatches,
                    'career_match_version' => CareerMatchingService::VERSION,
                ]);
            }

            $result = [
                "score" => $normalizedScores,
                "career_matches" => $careerMatchingService->toApiPayload($careerMatches),
                "created_at" => $getResult->created_at,
                "updated_at" => $getResult->updated_at
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
            ], 500);
        }
    }

    public function riasecShareStatus(Request $request)
    {
        $result = stp_personalityTestResult::where('student_id', Auth::id())
            ->where('status', 1)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'share_token' => $result?->share_token,
                'shared_at' => $result?->shared_at,
            ],
        ]);
    }

    public function createRiasecShare(Request $request)
    {
        $result = stp_personalityTestResult::where('student_id', Auth::id())
            ->where('status', 1)
            ->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'No active RIASEC result was found.',
            ], 404);
        }

        if (!$result->share_token) {
            do {
                $shareToken = Str::random(64);
            } while (stp_personalityTestResult::where('share_token', $shareToken)->exists());

            $result->update([
                'share_token' => $shareToken,
                'shared_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'share_token' => $result->share_token,
                'shared_at' => $result->shared_at,
            ],
        ]);
    }

    public function revokeRiasecShare(Request $request)
    {
        $result = stp_personalityTestResult::where('student_id', Auth::id())
            ->where('status', 1)
            ->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'No active RIASEC result was found.',
            ], 404);
        }

        $result->update([
            'share_token' => null,
            'shared_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['message' => 'The shared RIASEC link has been disabled.'],
        ]);
    }

    public function sharedRiasecResult(string $token)
    {
        $payload = $this->getSharedRiasecPayload($token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Shared RIASEC result not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function riasecSharePage(string $token)
    {
        $payload = $this->getSharedRiasecPayload($token);

        if (!$payload) {
            return response('Shared RIASEC result not found.', 404);
        }

        $topType = $this->getRiasecTopType($payload['scores']);
        $username = $payload['username'] ?: 'Student';
        $frontendBaseUrl = rtrim(env('FRONTEND_REDIRECT_URL', env('URL', 'https://studypal.my/')), '/');
        $frontendShareUrl = "{$frontendBaseUrl}/share/{$token}";
        $backendShareUrl = url("/share/{$token}");
        $poster = $payload['poster'] ?? ['ready' => false];
        $ogImageUrl = $poster['og_image_url'] ?? url("/api/student/riasecOgImage/{$token}").'?v=legacy';
        $careerNames = collect($poster['career_matches'] ?? [])->pluck('name')->implode(', ');
        $title = $poster['ready'] ? "{$username}'s Career Matches - {$careerNames}" : "{$username}'s Verified RIASEC Result - {$topType}";
        $description = $poster['ready']
            ? "View {$username}'s verified StudyPal career matches: {$careerNames}."
            : "View {$username}'s verified {$topType} RIASEC assessment result on StudyPal.";

        return response($this->buildRiasecShareHtml(
            $title,
            $description,
            $backendShareUrl,
            $ogImageUrl,
            $frontendShareUrl
        ))->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function riasecOgImage(string $token)
    {
        $payload = $this->getSharedRiasecPayload($token);

        if (!$payload) {
            return response('Shared RIASEC result not found.', 404);
        }

        if (!function_exists('imagecreatetruecolor')) {
            return response('Server image generation is not available.', 503);
        }

        $png = ($payload['poster']['ready'] ?? false)
            ? $this->buildCareerOgPng($payload)
            : '';
        $png = $png ?: $this->buildRiasecOgPng($payload);

        return response($png)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function posterAsset(string $path)
    {
        $posterRoot = realpath(public_path('storage/poster-assets'));
        $assetPath = realpath(public_path('storage/'.ltrim($path, '/')));

        if (! $posterRoot || ! $assetPath || ! str_starts_with($assetPath, $posterRoot.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->file($assetPath, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Type' => 'image/webp',
        ]);
    }

    private function getSharedRiasecPayload(string $token): ?array
    {
        $result = stp_personalityTestResult::with('student')
            ->where('share_token', $token)
            ->where('status', 1)
            ->first();

        if (!$result || !$result->student) {
            return null;
        }

        $scores = json_decode($result->score, true);

        if (!is_array($scores)) {
            $scores = [];
        }

        $careerMatching = app(CareerMatchingService::class);
        try {
            $scores = $careerMatching->normalizeScores($scores);
            $careerMatches = $result->career_matches;
            if (! is_array($careerMatches) || count($careerMatches) < 3) {
                $careerMatches = $careerMatching->match($scores);
                $result->update([
                    'score' => json_encode($scores),
                    'career_matches' => $careerMatches,
                    'career_match_version' => CareerMatchingService::VERSION,
                ]);
            }
        } catch (\Throwable $error) {
            $careerMatches = [];
        }

        $topType = $this->getRiasecTopType($scores);
        $poster = app(PosterAssetService::class)->resolvePoster($topType, $careerMatches, $token);

        return [
            'username' => $result->student->student_userName,
            'scores' => $scores,
            'poster' => $poster,
            'created_at' => $result->created_at,
            'updated_at' => $result->updated_at,
        ];
    }

    private function getRiasecTopType(array $scores): string
    {
        $validTypes = [
            'Realistic',
            'Investigative',
            'Artistic',
            'Social',
            'Enterprising',
            'Conventional',
        ];

        $normalizedScores = array_intersect_key($scores, array_flip($validTypes));

        if (!$normalizedScores) {
            return 'Realistic';
        }

        arsort($normalizedScores, SORT_NUMERIC);

        return array_key_first($normalizedScores) ?: 'Realistic';
    }

    private function buildRiasecShareHtml(
        string $title,
        string $description,
        string $shareUrl,
        string $imageUrl,
        string $frontendShareUrl
    ): string {
        $escapedTitle = e($title);
        $escapedDescription = e($description);
        $escapedShareUrl = e($shareUrl);
        $escapedImageUrl = e($imageUrl);
        $escapedFrontendShareUrl = e($frontendShareUrl);
        $jsRedirectUrl = json_encode($frontendShareUrl);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$escapedTitle}</title>
  <meta name="description" content="{$escapedDescription}">
  <link rel="canonical" href="{$escapedFrontendShareUrl}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="StudyPal">
  <meta property="og:title" content="{$escapedTitle}">
  <meta property="og:description" content="{$escapedDescription}">
  <meta property="og:url" content="{$escapedShareUrl}">
  <meta property="og:image" content="{$escapedImageUrl}">
  <meta property="og:image:secure_url" content="{$escapedImageUrl}">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{$escapedTitle}">
  <meta name="twitter:description" content="{$escapedDescription}">
  <meta name="twitter:image" content="{$escapedImageUrl}">
</head>
<body>
  <p>Opening StudyPal shared RIASEC result...</p>
  <p><a href="{$escapedFrontendShareUrl}">Open result</a></p>
  <script>
    window.location.replace({$jsRedirectUrl});
  </script>
</body>
</html>
HTML;
    }

    private function buildRiasecOgPng(array $payload): string
    {
        $topType = $this->getRiasecTopType($payload['scores']);
        $username = trim((string) ($payload['username'] ?? 'Student')) ?: 'Student';
        $width = 1200;
        $height = 630;
        $colorsByType = [
            'Realistic' => ['primary' => [172, 67, 34], 'secondary' => [247, 181, 72], 'soft' => [255, 235, 205]],
            'Investigative' => ['primary' => [35, 93, 154], 'secondary' => [93, 190, 211], 'soft' => [221, 245, 249]],
            'Artistic' => ['primary' => [158, 58, 137], 'secondary' => [239, 145, 199], 'soft' => [253, 226, 242]],
            'Social' => ['primary' => [25, 129, 101], 'secondary' => [126, 213, 155], 'soft' => [222, 247, 231]],
            'Enterprising' => ['primary' => [165, 60, 54], 'secondary' => [244, 166, 68], 'soft' => [255, 231, 207]],
            'Conventional' => ['primary' => [76, 78, 121], 'secondary' => [166, 179, 219], 'soft' => [231, 235, 249]],
        ];
        $palette = $colorsByType[$topType] ?? $colorsByType['Realistic'];

        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);
        imagealphablending($image, true);

        $cream = imagecolorallocate($image, 255, 249, 243);
        $ink = imagecolorallocate($image, 45, 31, 46);
        $muted = imagecolorallocate($image, 105, 84, 96);
        $lightMuted = imagecolorallocate($image, 139, 118, 129);
        $offWhite = imagecolorallocate($image, 255, 253, 250);
        $primary = imagecolorallocate($image, ...$palette['primary']);
        $secondary = imagecolorallocate($image, ...$palette['secondary']);
        $soft = imagecolorallocate($image, ...$palette['soft']);
        $shadow = imagecolorallocatealpha($image, 42, 24, 36, 112);

        imagefilledrectangle($image, 0, 0, $width, $height, $cream);
        imagefilledrectangle($image, 0, 0, $width, 160, $soft);
        imagefilledrectangle($image, 0, 0, 24, $height, $primary);

        $this->imagefilledroundedrectangle($image, 82, 78, 1118, 552, 36, $shadow);
        $this->imagefilledroundedrectangle($image, 74, 68, 1110, 542, 36, $offWhite);
        imagerectangle($image, 74, 68, 1110, 542, $soft);

        imagefilledrectangle($image, 74, 68, 1110, 143, $primary);
        imagefilledrectangle($image, 98, 98, 122, 122, $secondary);

        $fontRegular = $this->getRiasecOgFontPath(false);
        $fontBold = $this->getRiasecOgFontPath(true);

        $this->drawRiasecText($image, $fontBold, 25, 138, 115, $offWhite, 'StudyPal Personality Assessment');
        $this->drawRiasecTextFit($image, $fontRegular, 22, 18, 720, 115, 340, $offWhite, "{$username}'s result", 'right');

        $this->drawRiasecText($image, $fontRegular, 28, 118, 212, $muted, 'I discovered my personality type');
        $this->drawRiasecTextFit($image, $fontBold, 50, 58, 112, 304, 620, $ink, $topType);
        $this->drawRiasecTextFit(
            $image,
            $fontRegular,
            24,
            18,
            118,
            366,
            510,
            $lightMuted,
            'Discover where your strengths can take you.'
        );

        $this->drawRiasecText($image, $fontBold, 28, 118, 498, $primary, 'studypal.my');

        $this->imagefilledroundedrectangle($image, 718, 172, 1068, 486, 32, $soft);
        $this->drawRiasecArtwork($image, $topType, 728, 154, 348, 348);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png ?: '';
    }

    private function buildCareerOgPng(array $payload): string
    {
        $poster = $payload['poster'];
        $width = 1200;
        $height = 630;
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);
        $cream = imagecolorallocate($image, 244, 241, 235);
        $white = imagecolorallocate($image, 255, 255, 255);
        $accentRgb = sscanf($poster['riasec']['accent_color'] ?? '#c71919', '#%02x%02x%02x');
        $accent = imagecolorallocate($image, ...($accentRgb ?: [199, 25, 25]));
        imagefill($image, 0, 0, $cream);

        foreach ($poster['career_matches'] as $index => $career) {
            $panel = $this->loadPosterImage($career['image_url']);
            if (! $panel) {
                imagedestroy($image);
                return '';
            }
            imagefilter($panel, IMG_FILTER_GRAYSCALE);
            imagefilter($panel, IMG_FILTER_CONTRAST, -12);
            $this->copyImageCover($image, $panel, $index * 400, 0, 400, $height);
            imagedestroy($panel);
            imagefilledrectangle($image, $index * 400, 492, ($index + 1) * 400, 630, imagecolorallocatealpha($image, 0, 0, 0, 50));
        }

        $animal = $this->loadPosterImage($poster['riasec']['animal_url']);
        if (! $animal) {
            imagedestroy($image);
            return '';
        }
        $sourceWidth = imagesx($animal);
        $sourceHeight = imagesy($animal);
        $targetHeight = 520;
        $targetWidth = (int) round($sourceWidth * ($targetHeight / $sourceHeight));
        imagecopyresampled($image, $animal, (int) (($width - $targetWidth) / 2), 115, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        imagedestroy($animal);

        imagefilledrectangle($image, 0, 0, $width, 92, $accent);
        $fontRegular = $this->getRiasecOgFontPath(false);
        $fontBold = $this->getRiasecOgFontPath(true);
        $username = trim((string) ($payload['username'] ?? 'Student')) ?: 'Student';
        $type = strtoupper($poster['riasec']['type']);
        $this->drawRiasecTextFit($image, $fontBold, 34, 24, 50, 61, 1100, $white, "{$username}, YOU ARE {$type}", 'center');
        $this->drawRiasecText($image, $fontBold, 21, 38, 474, $white, 'YOUR TOP CAREER MATCHES');
        foreach ($poster['career_matches'] as $index => $career) {
            $this->drawRiasecTextFit($image, $fontBold, 28, 20, ($index * 400) + 20, 574, 360, $white, strtoupper($career['name']), 'center');
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png ?: '';
    }

    private function loadPosterImage(?string $url)
    {
        if (! $url) {
            return false;
        }
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $assetPrefix = '/api/student/posterAsset/';
        $absolutePath = str_starts_with($path, $assetPrefix)
            ? public_path('storage/'.substr($path, strlen($assetPrefix)))
            : public_path(ltrim($path, '/'));
        if (! is_file($absolutePath)) {
            return false;
        }
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absolutePath) : false,
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absolutePath) : false,
            default => false,
        };
    }

    private function copyImageCover($destination, $source, int $x, int $y, int $width, int $height): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);
        $sourceX = (int) max(0, ($sourceWidth - $cropWidth) / 2);
        $sourceY = (int) max(0, ($sourceHeight - $cropHeight) / 2);
        imagecopyresampled($destination, $source, $x, $y, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
    }

    private function getRiasecOgFontPath(bool $bold): ?string
    {
        $paths = $bold
            ? [
                public_path('fonts/Ubuntu-Bold.ttf'),
                'C:\Windows\Fonts\arialbd.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ]
            : [
                public_path('fonts/Ubuntu-Regular.ttf'),
                'C:\Windows\Fonts\arial.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function drawRiasecText($image, ?string $fontPath, int $size, int $x, int $y, int $color, string $text): void
    {
        if ($fontPath && function_exists('imagettftext')) {
            imagettftext($image, $size, 0, $x, $y, $color, $fontPath, $text);
            return;
        }

        imagestring($image, 5, $x, max(0, $y - 18), $text, $color);
    }

    private function drawRiasecArtwork($image, string $topType, int $targetX, int $targetY, int $targetWidth, int $targetHeight): void
    {
        if (!function_exists('imagecreatefromwebp')) {
            return;
        }

        $typeFileNames = [
            'Realistic' => 'realisticmain.webp',
            'Investigative' => 'investigativemain.webp',
            'Artistic' => 'artisticmain.webp',
            'Social' => 'socialmain.webp',
            'Enterprising' => 'enterprisingmain.webp',
            'Conventional' => 'conventionalmain.webp',
        ];
        $fileName = $typeFileNames[$topType] ?? $typeFileNames['Realistic'];
        $artworkPath = storage_path("app/public/riasecType/{$fileName}");

        if (!is_file($artworkPath)) {
            return;
        }

        $artwork = @imagecreatefromwebp($artworkPath);

        if (!$artwork) {
            return;
        }

        imagealphablending($artwork, true);
        imagesavealpha($artwork, true);

        $sourceWidth = imagesx($artwork);
        $sourceHeight = imagesy($artwork);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($artwork);
            return;
        }

        $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $drawWidth = (int) round($sourceWidth * $scale);
        $drawHeight = (int) round($sourceHeight * $scale);
        $drawX = $targetX + (int) round(($targetWidth - $drawWidth) / 2);
        $drawY = $targetY + (int) round(($targetHeight - $drawHeight) / 2);

        imagecopyresampled(
            $image,
            $artwork,
            $drawX,
            $drawY,
            0,
            0,
            $drawWidth,
            $drawHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($artwork);
    }

    private function drawRiasecTextCentered($image, ?string $fontPath, int $size, int $centerX, int $baselineY, int $color, string $text): void
    {
        $textWidth = $this->getRiasecTextWidth($fontPath, $size, $text);
        $this->drawRiasecText($image, $fontPath, $size, (int) ($centerX - ($textWidth / 2)), $baselineY, $color, $text);
    }

    private function drawRiasecTextFit(
        $image,
        ?string $fontPath,
        int $maxSize,
        int $minSize,
        int $x,
        int $baselineY,
        int $maxWidth,
        int $color,
        string $text,
        string $align = 'left'
    ): void {
        $size = $maxSize;

        while ($size > $minSize && $this->getRiasecTextWidth($fontPath, $size, $text) > $maxWidth) {
            $size -= 2;
        }

        $textWidth = $this->getRiasecTextWidth($fontPath, $size, $text);
        $drawX = $align === 'right' ? $x + $maxWidth - $textWidth : $x;

        $this->drawRiasecText($image, $fontPath, $size, (int) $drawX, $baselineY, $color, $text);
    }

    private function getRiasecTextWidth(?string $fontPath, int $size, string $text): int
    {
        if ($fontPath && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $fontPath, $text);

            if (is_array($box)) {
                return abs($box[2] - $box[0]);
            }
        }

        return imagefontwidth(5) * strlen($text);
    }

    private function imagefilledroundedrectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
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

    public function addUpdateInterestedCourse(Request $request)
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not authenticated',
                ], 401);
            }
            
            // Example log with user info
            Log::info('User is adding/updating interested course', [
                'user_id' => $authUser->id,
                'request_data' => $request->all(),
            ]);  

            // Validate the request
            $request->validate([
                'student_id' => 'required|integer',
                'course_id' => 'required|integer',
            ]);

            // Check if this interest already exists
            $existingInterest = stp_courseInterest::where('student_id', $request->student_id)
                ->where('course_id', $request->course_id)
                ->first();

            if ($existingInterest) {
                // If exists, update the status to 1
                $updated = $existingInterest->update([
                    'status' => 1,
                    'updated_by' => auth()->id()
                ]);

                $message = 'Course interest updated successfully';
            } else {
                // If doesn't exist, create new record
                $created = stp_courseInterest::create([
                    'student_id' => $request->student_id,
                    'course_id' => $request->course_id,
                    'created_by' => auth()->id(),
                    'status' => 1,
                ]);

                $message = 'Course interest created successfully';
            }

            return response()->json([
                'success' => true,
                'data' => ['message' => $message]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in addUpdateInterestedCourse:', [
                'student_id' => $request->student_id ?? null,
                'course_id' => $request->course_id ?? null,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeInterestedCourse(Request $request)
    {
        try {
            // Validate authentication
            $authUser = Auth::user();
            if (!$authUser) {
                \Log::warning('removeInterestedCourse: Unauthenticated user attempt', [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'User is not authenticated',
                ], 401);
            }

            // Validate the request inputs
            $request->validate([
                'course_id' => 'required|integer|min:1',
                'type' => 'required|string|in:disable'
            ]);

            // Find the interest record by course_id and the authenticated user's ID
            $interest = stp_courseInterest::where('course_id', $request->course_id)
                ->where('student_id', $authUser->id);

            // Check if the interest exists
            if (!$interest) {
                \Log::warning('removeInterestedCourse: Course interest not found', [
                    'student_id' => $authUser->id,
                    'course_id' => $request->course_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Course interest not found or does not belong to the authenticated user.',
                ], 404);
            }

            // Disable the interest (set status to 0)
            DB::beginTransaction();
            try {
                $updated = $interest->update([
                    'status' => 0,
                    'updated_by' => $authUser->id,
                ]);
                DB::commit();
                
                if ($updated) {
                    \Log::info('removeInterestedCourse: Course interest disabled successfully', [
                        'student_id' => $authUser->id,
                        'course_id' => $request->course_id
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => ['message' => 'Course interest disabled successfully.'],
                    ]);
                } else {
                    throw new \Exception('Failed to disable course interest');
                }
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
            
        } catch (ValidationException $e) {
            \Log::warning('removeInterestedCourse: Validation error', [
                'student_id' => $authUser->id ?? null,
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('removeInterestedCourse: Unexpected error', [
                'student_id' => $authUser->id ?? null,
                'course_id' => $request->course_id ?? null,
                'type' => $request->type ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    public function interestedCourseList(Request $request)
    {
        try {
            $authUser = Auth::user();

            $getStudentCourseList = stp_courseInterest::where('student_id', $authUser->id)
                ->where('status', 1)
                ->get()->map(function ($interestedCourse) {
                $featured = $interestedCourse->course->featured->contains(function ($item) {
                    return $item->featured_type == 30 && $item->featured_status == 1 && $item->featured_startTime < now() && $item->featured_endTime > now();
                });
                $intakeMonths = $interestedCourse->course->intake->where('intake_status', 1)
                    ->pluck('month.core_metaName')
                    ->toArray();

                return [
                    'id' => $interestedCourse->id,
                    'course_id' => $interestedCourse->course->id,
                    'course_slug' => $interestedCourse->course->course_slug,
                    'school_id' => $interestedCourse->course->school->id,
                    'school_slug' => $interestedCourse->course->school->school_slug,
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
                'slug' => 'required|string'
            ]);

            $school = stp_school::where('school_slug', $request->slug)->first();

            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'School not found'
                ], 404);
            }

            $schoolId = $school->id;


            $validateExsitData = stp_totalNumberVisit::where('school_id', $schoolId)
                ->whereDay('created_at', Carbon::now()->day)  // Check if the day matches the current day
                ->whereMonth('created_at', Carbon::now()->month)  // Check if the month matches the current month
                ->whereYear('created_at', Carbon::now()->year)  // Check if the year matches the current year
                ->where('status', 1)
                ->first();




            if (empty($validateExsitData)) {
                $formData = [
                    'school_id' => $schoolId,
                    'totalNumberVisit' => 1,
                    'status' => 1
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

    public function increaseArticleVisit(Request $request)
    {
        try {
            $request->validate([
                'article_id' => 'required|integer'
            ]);

            $articleId = $request->article_id;

            // Check if article exists and is active
            $article = stp_article::where('id', $articleId)
                ->where('data_status', 1)
                ->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => "Article not found or inactive"
                ], 404);
            }

            // Increment article_views in stp_article table
            $article->increment('article_views');

            // Check if visit record exists for today
            $validateExistData = stp_article_visit::where('article_id', $articleId)
                ->whereDay('created_at', \Carbon\Carbon::now()->day)
                ->whereMonth('created_at', \Carbon\Carbon::now()->month)
                ->whereYear('created_at', \Carbon\Carbon::now()->year)
                ->where('status', 1)
                ->first();

            if (empty($validateExistData)) {
                // Create new visit record for today
                $formData = [
                    'article_id' => $articleId,
                    'totalNumberVisit' => 1,
                    'status' => 1
                ];
                $createData = stp_article_visit::create($formData);
                if ($createData) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'message' => "Article visit recorded successfully"
                        ]
                    ]);
                } else {
                    throw new \Exception("Failed to create article visit data");
                }
            } else {
                // Increment existing visit count for today
                $validateExistData->increment('totalNumberVisit');
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => "Article visit count updated successfully",
                        'totalNumberVisit' => $validateExistData->totalNumberVisit
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function articleDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            
            // Fetch article with category and content images, only active articles
            $article = stp_article::with(['category', 'contentImages'])
                ->where('id', $request->id)
                ->where('data_status', 1)
                ->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article not found or inactive'
                ], 404);
            }

            // Generate URLs for files in public/storage
            $featuredImageUrl = null;
            if ($article->article_featured_image) {
                $baseUrl = url('/');
                $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
            }

            // Read article content from file - from public/storage
            $articleContent = '';
            if ($article->article_content && file_exists(public_path('storage/' . $article->article_content))) {
                $articleContent = file_get_contents(public_path('storage/' . $article->article_content));
            }

            // URL-decode the content to handle URL-encoded placeholders (e.g., %5BIMAGE_ID:16%5D -> [IMAGE_ID:16])
            $articleContent = urldecode($articleContent);

            // Get content images with URLs - from public/storage
            // Load images separately to ensure they're loaded correctly
            $contentImages = stp_article_content_image::where('article_id', $article->id)
                ->where('data_status', 1)
                ->orderBy('id')
                ->get()
                ->filter(function ($image) {
                    return !empty($image->image_path);
                })
                ->map(function ($image) {
                    $baseUrl = url('/');
                    return [
                        'id' => $image->id,
                        'url' => rtrim($baseUrl, '/') . '/storage/' . ltrim($image->image_path, '/'),
                        'path' => $image->image_path,
                        'alt' => $image->image_alt ?? ''
                    ];
                })
                ->values();

            // Replace image placeholders in HTML with actual image URLs
            // Use a comprehensive approach: regex for img tags + string replace as fallback
            foreach ($contentImages as $image) {
                $placeholder = '[IMAGE_ID:' . $image['id'] . ']';
                $imageUrl = $image['url'];
                
                // Also handle URL-encoded version of placeholder
                $encodedPlaceholder = urlencode($placeholder);
                
                // Escape the placeholder for regex (brackets are special characters)
                $escapedPlaceholder = preg_quote($placeholder, '/');
                $escapedEncodedPlaceholder = preg_quote($encodedPlaceholder, '/');
                
                // Method 1: Replace in img src attributes with double quotes (most common)
                // Handles: <img src="[IMAGE_ID:14]"> or <img ... src="[IMAGE_ID:14]">
                // Also handles URL-encoded: <img src="%5BIMAGE_ID:14%5D">
                $articleContent = preg_replace(
                    '/(<img[^>]*\s+src=["])(' . $escapedPlaceholder . '|' . $escapedEncodedPlaceholder . ')(["][^>]*>)/i',
                    '$1' . $imageUrl . '$3',
                    $articleContent
                );
                
                // Method 2: Replace in img src attributes with single quotes
                // Handles: <img src='[IMAGE_ID:14]'> or <img ... src='[IMAGE_ID:14]'>
                $articleContent = preg_replace(
                    '/(<img[^>]*\s+src=[\'])(' . $escapedPlaceholder . '|' . $escapedEncodedPlaceholder . ')([\'][^>]*>)/i',
                    '$1' . $imageUrl . '$3',
                    $articleContent
                );
                
                // Method 3: Replace in img src attributes without quotes (edge case)
                // Handles: <img src=[IMAGE_ID:14]> or <img ... src=[IMAGE_ID:14]>
                $articleContent = preg_replace(
                    '/(<img[^>]*\s+src=)(' . $escapedPlaceholder . '|' . $escapedEncodedPlaceholder . ')([\s>])/i',
                    '$1' . $imageUrl . '$3',
                    $articleContent
                );
                
                // Method 4: Replace anywhere in the content (catches any edge cases)
                // This is the most reliable fallback - handles both encoded and non-encoded
                $articleContent = str_replace($placeholder, $imageUrl, $articleContent);
                $articleContent = str_replace($encodedPlaceholder, $imageUrl, $articleContent);
            }
            
            // Remove any remaining placeholders for images with data_status = 0
            // This handles cases where old HTML files might still have placeholders for deleted images
            $deletedImageIds = stp_article_content_image::where('article_id', $article->id)
                ->where('data_status', 0)
                ->pluck('id')
                ->toArray();
            
            foreach ($deletedImageIds as $deletedId) {
                $placeholder = '[IMAGE_ID:' . $deletedId . ']';
                // Remove img tags that contain the deleted image placeholder in src attribute
                $articleContent = preg_replace('/<img[^>]*src=["\'][^"\']*' . preg_quote($placeholder, '/') . '[^"\']*["\']*[^>]*>/i', '', $articleContent);
            }

            // Format date
            $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $article->id,
                    'title' => $article->article_title,
                    'slug' => $article->article_slug,
                    'category_id' => $article->category_id,
                    'category_name' => $article->category ? $article->category->category_name : 'Uncategorized',
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'featured_image' => $featuredImageUrl,
                    'content' => $articleContent,
                    'intent_config' => $this->formatArticleIntentConfig($article->article_intent_config),
                    'content_images' => $contentImages
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function articleDetailBySlug(Request $request)
    {
        try {
            $request->validate([
                'slug' => 'required|string'
            ]);
            
            // Fetch article with category and content images, only active articles
            $article = stp_article::with(['category', 'contentImages'])
                ->where('article_slug', $request->slug)
                ->where('data_status', 1)
                ->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article not found or inactive'
                ], 404);
            }

            // Generate URLs for files in public/storage
            $featuredImageUrl = null;
            if ($article->article_featured_image) {
                $baseUrl = url('/');
                $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
            }

            // Read article content from file - from public/storage
            $articleContent = '';
            if ($article->article_content && file_exists(public_path('storage/' . $article->article_content))) {
                $articleContent = file_get_contents(public_path('storage/' . $article->article_content));
            }

            // URL-decode the content to handle URL-encoded placeholders (e.g., %5BIMAGE_ID:16%5D -> [IMAGE_ID:16])
            $articleContent = urldecode($articleContent);

            // Get content images with URLs - from public/storage
            // Load images separately to ensure they're loaded correctly
            $contentImages = stp_article_content_image::where('article_id', $article->id)
                ->where('data_status', 1)
                ->orderBy('id')
                ->get()
                ->filter(function ($image) {
                    return !empty($image->image_path);
                })
                ->map(function ($image) {
                    $baseUrl = url('/');
                    return [
                        'id' => $image->id,
                        'url' => rtrim($baseUrl, '/') . '/storage/' . ltrim($image->image_path, '/'),
                        'path' => $image->image_path,
                        'alt' => $image->image_alt ?? ''
                    ];
                })
                ->values();

            // Replace image placeholders in HTML with actual image URLs
            // Use a comprehensive approach: regex for img tags + string replace as fallback
            foreach ($contentImages as $image) {
                $placeholder = '[IMAGE_ID:' . $image['id'] . ']';
                $imageUrl = $image['url'];
                
                // Also handle URL-encoded version of placeholder
                $encodedPlaceholder = urlencode($placeholder);
                
                // Escape the placeholder for regex (brackets are special characters)
                $escapedPlaceholder = preg_quote($placeholder, '/');
                $escapedEncodedPlaceholder = preg_quote($encodedPlaceholder, '/');
                
                // Method 1: Replace in img src attributes with double quotes (most common)
                // Handles: <img src="[IMAGE_ID:14]"> or <img ... src="[IMAGE_ID:14]">
                // Also handles URL-encoded: <img src="%5BIMAGE_ID:14%5D">
                $articleContent = preg_replace(
                    '/(<img[^>]*\s+src=["])(' . $escapedPlaceholder . '|' . $escapedEncodedPlaceholder . ')(["][^>]*>)/i',
                    '$1' . $imageUrl . '$3',
                    $articleContent
                );
                
                // Method 2: Replace in img src attributes with single quotes
                // Handles: <img src='[IMAGE_ID:14]'> or <img ... src='[IMAGE_ID:14]'>
                $articleContent = preg_replace(
                    '/(<img[^>]*\s+src=[\'])(' . $escapedPlaceholder . '|' . $escapedEncodedPlaceholder . ')([\'][^>]*>)/i',
                    '$1' . $imageUrl . '$3',
                    $articleContent
                );
                
                // Method 3: Replace in img src attributes without quotes (edge case)
                // Handles: <img src=[IMAGE_ID:14]> or <img ... src=[IMAGE_ID:14]>
                $articleContent = preg_replace(
                    '/(<img[^>]*\s+src=)(' . $escapedPlaceholder . '|' . $escapedEncodedPlaceholder . ')([\s>])/i',
                    '$1' . $imageUrl . '$3',
                    $articleContent
                );
                
                // Method 4: Replace anywhere in the content (catches any edge cases)
                // This is the most reliable fallback - handles both encoded and non-encoded
                $articleContent = str_replace($placeholder, $imageUrl, $articleContent);
                $articleContent = str_replace($encodedPlaceholder, $imageUrl, $articleContent);
            }
            
            // Remove any remaining placeholders for images with data_status = 0
            // This handles cases where old HTML files might still have placeholders for deleted images
            $deletedImageIds = stp_article_content_image::where('article_id', $article->id)
                ->where('data_status', 0)
                ->pluck('id')
                ->toArray();
            
            foreach ($deletedImageIds as $deletedId) {
                $placeholder = '[IMAGE_ID:' . $deletedId . ']';
                // Remove img tags that contain the deleted image placeholder in src attribute
                $articleContent = preg_replace('/<img[^>]*src=["\'][^"\']*' . preg_quote($placeholder, '/') . '[^"\']*["\']*[^>]*>/i', '', $articleContent);
            }

            // Format date
            $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $article->id,
                    'title' => $article->article_title,
                    'category_id' => $article->category_id,
                    'category_name' => $article->category ? $article->category->category_name : 'Uncategorized',
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'featured_image' => $featuredImageUrl,
                    'content' => $articleContent,
                    'intent_config' => $this->formatArticleIntentConfig($article->article_intent_config),
                    'content_images' => $contentImages
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateArticleSlug(Request $request)
    {
      try {
          $request->validate([
              'title' => 'required|string',
              'articleId' => 'nullable|integer'
          ]);

          $articleId = $request->articleId ?? null;
          $baseSlug = $request->title;

          // Convert to lowercase
          $baseSlug = strtolower($baseSlug);

          // Replace non-alphanumeric characters with dashes
          $baseSlug = preg_replace('/[^a-z0-9]+/', '-', $baseSlug);

          // Remove leading/trailing dashes
          $baseSlug = trim($baseSlug, '-');

          if (empty($baseSlug)) {
              $baseSlug = 'article';
          }

          $existingSlugs = array_flip(
              stp_article::where(function ($query) use ($baseSlug) {
                  $query->where('article_slug', $baseSlug)
                        ->orWhere('article_slug', 'LIKE', $baseSlug . '-%');
              })
              ->when($articleId, function ($query) use ($articleId) {
                  $query->where('id', '!=', $articleId); // exclude itself
              })
              ->pluck('article_slug')
              ->toArray()
          );

          if (!isset($existingSlugs[$baseSlug])) {
              $slug = $baseSlug;
          } else {
              $count = 2;
              while (isset($existingSlugs[$baseSlug . '-' . $count])) {
                  $count++;
              }
              $slug = $baseSlug . '-' . $count;
          }

          return response()->json([
              'success' => true,
              'slug' => $slug
          ]);

      } catch (\Exception $e) {
          return response()->json([
              'success' => false,
              'message' => 'Internal Server Error',
              'error' => $e->getMessage()
          ], 500);
      }
    }

    /**
     * Get all comments for an article with 3-level nesting
     */
    public function getArticleComments(Request $request)
    {
        try {
            $request->validate([
                'article_id' => 'required|integer'
            ]);

            // Get all active comments for the article
            $comments = stp_article_comment::where('article_id', $request->article_id)
                ->where('data_status', 1)
                ->with(['student.detail', 'replyTo.student.detail', 'replyTo'])
                ->orderBy('created_at', 'asc')
                ->get();

            // Build nested structure
            $commentMap = [];
            $rootComments = [];

            // First pass: create map of all comments
            foreach ($comments as $comment) {
                $commentData = [
                    'id' => $comment->id,
                    'article_id' => $comment->article_id,
                    'student_id' => $comment->student_id,
                    'author_name' => $this->getCommentAuthorDisplayName($comment),
                    'profile_picture' => $this->getCommentProfilePictureUrl($comment),
                    'parent_id' => $comment->parent_id,
                    'reply_to_id' => $comment->reply_to_id,
                    'comment_level' => $comment->comment_level,
                    'comment_text' => $comment->comment_text,
                    'reply_to_author' => null,
                    'replies' => [],
                    'created_at' => $comment->created_at->toISOString(),
                    'created_at_human' => $comment->created_at->diffForHumans(),
                ];

                // Get reply-to author info if exists
                if ($comment->reply_to_id && $comment->replyTo) {
                    $commentData['reply_to_author'] = $this->getCommentAuthorDisplayName($comment->replyTo);
                } elseif ($comment->comment_level == 3 && $comment->reply_to_id === null && $comment->parent_id) {
                    $parent = $comments->firstWhere('id', $comment->parent_id);
                    if ($parent && $parent->comment_level == 2) {
                        $commentData['reply_to_author'] = $this->getCommentAuthorDisplayName($parent);
                    }
                }

                $commentMap[$comment->id] = $commentData;
            }

            // Second pass: build nested structure
            foreach ($commentMap as $id => $commentData) {
                if ($commentData['parent_id'] === null) {
                    // Root comment (Level 1)
                    $rootComments[] = &$commentMap[$id];
                } else {
                    // Child comment - add to parent's replies
                    if (isset($commentMap[$commentData['parent_id']])) {
                        $commentMap[$commentData['parent_id']]['replies'][] = &$commentMap[$id];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rootComments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new comment or reply
     */
    public function createArticleComment(Request $request)
    {
        Log::info('=== CREATE COMMENT REQUEST START ===');
        Log::info('Request data: ', $request->all());
        Log::info('Auth header: ', ['authorization' => $request->header('Authorization')]);
        
        try {
            // Check authentication first
            $authUser = Auth::guard('sanctum')->user();
            Log::info('Auth user: ', $authUser ? ['id' => $authUser->id] : 'null');
            
            if (!$authUser) {
                Log::warning('Unauthenticated request to createArticleComment');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login to post a comment.',
                    'debug' => 'No authenticated user found'
                ], 401);
            }

            $request->validate([
                'article_id' => 'required|integer',
                'comment_text' => 'required|string|max:5000',
                'parent_id' => 'nullable|integer|exists:stp_article_comments,id',
                'reply_to_id' => 'nullable|integer|exists:stp_article_comments,id',
                'author_name' => 'nullable|string|max:255' // For anonymous comments
            ]);
            
            Log::info('Validation passed');
            
            // Check if article exists
            $article = stp_article::where('id', $request->article_id)
                ->where('data_status', 1)
                ->first();

            if (!$article) {
                Log::warning('Article not found: ' . $request->article_id);
                return response()->json([
                    'success' => false,
                    'message' => 'Article not found',
                    'debug' => 'Article ID: ' . $request->article_id . ' not found or inactive'
                ], 404);
            }
            
            Log::info('Article found: ' . $article->id);

            $commentLevel = 1;
            $parentId = null;
            $replyToId = $request->reply_to_id;

            // Determine comment level and parent
            if ($request->parent_id) {
                $parentComment = stp_article_comment::where('id', $request->parent_id)
                    ->where('article_id', $request->article_id)
                    ->where('data_status', 1)
                    ->first();

                if (!$parentComment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent comment not found'
                    ], 404);
                }

                $parentId = $parentComment->id;
                $parentLevel = $parentComment->comment_level;

                // Calculate new comment level
                if ($parentLevel == 1) {
                    $commentLevel = 2;
                } elseif ($parentLevel == 2) {
                    $commentLevel = 3;
                } elseif ($parentLevel == 3) {
                    // Level 3 can reply to Level 3, but stays at Level 3
                    $commentLevel = 3;
                    // Update parent_id to point to the Level 2 ancestor if replying to Level 3
                    $level2Parent = $parentComment;
                    while ($level2Parent->parent_id && $level2Parent->comment_level != 2) {
                        $level2Parent = stp_article_comment::find($level2Parent->parent_id);
                        if (!$level2Parent) break;
                    }
                    if ($level2Parent && $level2Parent->comment_level == 2) {
                        $parentId = $level2Parent->id;
                    }
                }
            }

            // Validate reply_to_id if provided
            if ($replyToId) {
                $replyToComment = stp_article_comment::where('id', $replyToId)
                    ->where('article_id', $request->article_id)
                    ->where('data_status', 1)
                    ->first();

                if (!$replyToComment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reply-to comment not found'
                    ], 404);
                }

                // For Level 3 comments, reply_to_id can be null even if we're replying to another Level 3
                // This is handled in the UI by showing the Level 2 parent as reply target
            }

            // Check if table exists (debug)
            try {
                $tableExists = \DB::select("SHOW TABLES LIKE 'stp_article_comments'");
                Log::info('Table check: ', ['exists' => !empty($tableExists)]);
            } catch (\Exception $e) {
                Log::error('Error checking table: ' . $e->getMessage());
            }

            // Create comment
            Log::info('Attempting to create comment with data: ', [
                'article_id' => $request->article_id,
                'student_id' => $authUser->id,
                'parent_id' => $parentId,
                'reply_to_id' => $replyToId,
                'comment_level' => $commentLevel,
                'comment_text_length' => strlen($request->comment_text),
            ]);
            
            $comment = stp_article_comment::create([
                'article_id' => $request->article_id,
                'student_id' => $authUser->id,
                'parent_id' => $parentId,
                'reply_to_id' => $replyToId,
                'comment_level' => $commentLevel,
                'comment_text' => $request->comment_text,
                'author_name' => null, // Always null for authenticated users
                'data_status' => 1,
                'created_by' => $authUser->id,
            ]);
            
            Log::info('Comment created successfully: ' . $comment->id);

            // Load relationships for response
            $comment->load(['student.detail', 'replyTo.student.detail']);

            $responseData = [
                'id' => $comment->id,
                'article_id' => $comment->article_id,
                'student_id' => $comment->student_id,
                'author_name' => $this->getCommentAuthorDisplayName($comment),
                'profile_picture' => $this->getCommentProfilePictureUrl($comment),
                'parent_id' => $comment->parent_id,
                'reply_to_id' => $comment->reply_to_id,
                'comment_level' => $comment->comment_level,
                'comment_text' => $comment->comment_text,
                'reply_to_author' => null,
                'replies' => [],
                'created_at' => $comment->created_at->toISOString(),
                'created_at_human' => $comment->created_at->diffForHumans(),
            ];

            if ($comment->reply_to_id && $comment->replyTo) {
                $responseData['reply_to_author'] = $this->getCommentAuthorDisplayName($comment->replyTo);
            } elseif ($comment->comment_level == 3 && $comment->reply_to_id === null && $comment->parent_id) {
                $parent = stp_article_comment::find($comment->parent_id);
                if ($parent && $parent->comment_level == 2) {
                    $responseData['reply_to_author'] = $this->getCommentAuthorDisplayName($parent);
                }
            }

            Log::info('Returning success response');
            return response()->json([
                'success' => true,
                'message' => 'Comment created successfully',
                'data' => $responseData
            ], 201);
        } catch (ValidationException $e) {
            Log::error('Validation failed: ', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'debug' => 'ValidationException caught'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Exception in createArticleComment: ', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        } finally {
            Log::info('=== CREATE COMMENT REQUEST END ===');
        }
    }

    /**
     * Debug endpoint to check database and table
     */
    public function debugArticleComments(Request $request)
    {
        try {
            $authUser = Auth::guard('sanctum')->user();
            
            $results = [
                'authenticated' => $authUser ? true : false,
                'user_id' => $authUser ? $authUser->id : null,
                'database' => config('database.connections.mysql.database'),
                'table_exists' => false,
                'table_structure' => null,
                'sample_data' => null,
                'errors' => []
            ];

            // Check if table exists
            try {
                $tables = DB::select("SHOW TABLES LIKE 'stp_article_comments'");
                $results['table_exists'] = !empty($tables);
                
                if ($results['table_exists']) {
                    // Get table structure
                    $structure = DB::select("DESCRIBE stp_article_comments");
                    $results['table_structure'] = $structure;
                    
                    // Get sample data
                    $sample = stp_article_comment::limit(5)->get();
                    $results['sample_data'] = $sample;
                }
            } catch (\Exception $e) {
                $results['errors'][] = 'Table check error: ' . $e->getMessage();
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a comment (especially for removing reply_to_id)
     */
    public function updateArticleComment(Request $request)
    {
        try {
            $request->validate([
                'comment_id' => 'required|integer|exists:stp_article_comments,id',
                'comment_text' => 'nullable|string|max:5000',
                'reply_to_id' => 'nullable|integer|exists:stp_article_comments,id' // Set to null to remove reply tag
            ]);

            $authUser = Auth::guard('sanctum')->user();
            
            $comment = stp_article_comment::where('id', $request->comment_id)
                ->where('data_status', 1)
                ->first();

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            // Check if user owns the comment (or is admin)
            if ($comment->student_id && $comment->student_id != $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this comment'
                ], 403);
            }

            // Update fields
            if ($request->has('comment_text')) {
                $comment->comment_text = $request->comment_text;
            }

            if ($request->has('reply_to_id')) {
                // Can set to null to remove reply tag, or set to a valid comment ID
                $comment->reply_to_id = $request->reply_to_id;
            }

            $comment->updated_by = $authUser ? $authUser->id : null;
            $comment->save();

            // Load relationships
            $comment->load(['student.detail', 'replyTo.student.detail']);

            $responseData = [
                'id' => $comment->id,
                'article_id' => $comment->article_id,
                'student_id' => $comment->student_id,
                'author_name' => $this->getCommentAuthorDisplayName($comment),
                'profile_picture' => $this->getCommentProfilePictureUrl($comment),
                'parent_id' => $comment->parent_id,
                'reply_to_id' => $comment->reply_to_id,
                'comment_level' => $comment->comment_level,
                'comment_text' => $comment->comment_text,
                'reply_to_author' => null,
                'created_at' => $comment->created_at->toISOString(),
                'created_at_human' => $comment->created_at->diffForHumans(),
            ];

            if ($comment->reply_to_id && $comment->replyTo) {
                $responseData['reply_to_author'] = $this->getCommentAuthorDisplayName($comment->replyTo);
            } elseif ($comment->comment_level == 3 && $comment->reply_to_id === null && $comment->parent_id) {
                $parent = stp_article_comment::find($comment->parent_id);
                if ($parent && $parent->comment_level == 2) {
                    $responseData['reply_to_author'] = $this->getCommentAuthorDisplayName($parent);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => $responseData
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete (soft delete) a comment
     */
    public function deleteArticleComment(Request $request)
    {
        try {
            $request->validate([
                'comment_id' => 'required|integer|exists:stp_article_comments,id'
            ]);

            $authUser = Auth::guard('sanctum')->user();
            
            $comment = stp_article_comment::where('id', $request->comment_id)
                ->where('data_status', 1)
                ->first();

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            // Check if user owns the comment (or is admin)
            if ($comment->student_id && $comment->student_id != $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this comment'
                ], 403);
            }

            // Soft delete
            $comment->data_status = 0;
            $comment->updated_by = $authUser ? $authUser->id : null;
            $comment->save();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
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
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function articleCategoryList(Request $request)
    {
        try {
            // Get all active article categories ordered by ID ascending
            $categories = stp_article_category::where('data_status', 1)
                ->orderBy('id', 'asc')
                ->get();

            // Process categories to generate slugs and format response
            $processedCategories = $categories->map(function ($category) {
                // Generate slug from category name
                $slug = \Illuminate\Support\Str::slug($category->category_name, '-');
                
                return [
                    'id' => $category->id,
                    'name' => strtoupper($category->category_name),
                    'slug' => $slug,
                    'colorCode' => $category->color_code,
                    'description' => $category->description ?? ''
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $processedCategories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function articleHeroList(Request $request)
    {
        try {
            // Calculate date 30 days ago
            $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
            
            // Fetch up to 3 random articles published within the last 30 days
            // Use a consistent seed based on date to ensure same results within the same day
            $articles = stp_article::where('data_status', 1)
                ->where('article_date', '>=', $thirtyDaysAgo)
                ->where('article_date', '<=', \Carbon\Carbon::now()->format('Y-m-d'))
                ->with('category')
                ->inRandomOrder()
                ->limit(3)
                ->get();

            // Process articles to format response
            $processedArticles = $articles->map(function ($article) {
                // Get category name
                $categoryName = $article->category ? $article->category->category_name : 'Uncategorized';
                
                // Format featured image URL - files are in public/storage
                $featuredImageUrl = null;
                if ($article->article_featured_image) {
                    // Check if it's already a full URL
                    if (filter_var($article->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $article->article_featured_image;
                    } else {
                        // Generate URL for files in public/storage directory
                        // Path format: article_featured_images/filename.jpg
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
                    }
                }
                
                // Format date
                $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');
                
                return [
                    'id' => $article->id,
                    'category' => strtoupper($categoryName),
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'title' => $article->article_title,
                    'slug' => $article->article_slug,
                    'featuredImage' => $featuredImageUrl,
                    'commentCount' => 0 // Hardcoded as requested
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $processedArticles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function firstThreeArticles(Request $request)
    {
        try {
            // Get hero article IDs from request parameter (passed from frontend)
            $heroArticleIds = [];
            if ($request->has('excludeIds')) {
                $excludeIds = $request->excludeIds;
                if (is_array($excludeIds)) {
                    $heroArticleIds = array_filter($excludeIds, function($id) {
                        return is_numeric($id) && $id > 0;
                    });
                } elseif (is_string($excludeIds)) {
                    // Handle comma-separated string
                    $ids = explode(',', $excludeIds);
                    $heroArticleIds = array_filter(array_map('intval', $ids), function($id) {
                        return $id > 0;
                    });
                }
                // Convert to integers for proper comparison
                $heroArticleIds = array_map('intval', $heroArticleIds);
            }

            // Fetch exactly 3 random articles (no date limit), excluding hero section articles
            $query = stp_article::where('data_status', 1)
                ->with('category');
            
            // Exclude hero section articles if any exist
            if (!empty($heroArticleIds)) {
                $query->whereNotIn('id', $heroArticleIds);
            }
            
            $articles = $query->inRandomOrder()
                ->limit(3)
                ->get();

            // Process articles to format response
            $processedArticles = $articles->map(function ($article) {
                // Get category name
                $categoryName = $article->category ? $article->category->category_name : 'Uncategorized';
                
                // Format featured image URL - files are in public/storage
                $featuredImageUrl = null;
                if ($article->article_featured_image) {
                    // Check if it's already a full URL
                    if (filter_var($article->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $article->article_featured_image;
                    } else {
                        // Generate URL for files in public/storage directory
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
                    }
                }
                
                // Format date
                $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');
                
                return [
                    'id' => $article->id,
                    'category' => strtoupper($categoryName),
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'title' => $article->article_title,
                    'featuredImage' => $featuredImageUrl,
                    'commentCount' => 0 // Hardcoded as requested
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $processedArticles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function articleHeroAndFirstThree(Request $request)
    {
        try {
            // Calculate date 30 days ago
            $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
            
            // Fetch up to 3 random articles published within the last 30 days for hero section
            $heroArticles = stp_article::where('data_status', 1)
                ->where('article_date', '>=', $thirtyDaysAgo)
                ->where('article_date', '<=', \Carbon\Carbon::now()->format('Y-m-d'))
                ->with('category')
                ->inRandomOrder()
                ->limit(3)
                ->get();

            // Get hero article IDs to exclude
            $heroArticleIds = $heroArticles->pluck('id')->toArray();

            // Fetch exactly 3 random articles (no date limit), excluding hero section articles
            $query = stp_article::where('data_status', 1)
                ->with('category');
            
            // Exclude hero section articles
            if (!empty($heroArticleIds)) {
                $query->whereNotIn('id', $heroArticleIds);
            }
            
            $firstThreeArticles = $query->inRandomOrder()
                ->limit(3)
                ->get();

            // Process hero articles
            $processedHeroArticles = $heroArticles->map(function ($article) {
                $categoryName = $article->category ? $article->category->category_name : 'Uncategorized';
                $featuredImageUrl = null;
                if ($article->article_featured_image) {
                    if (filter_var($article->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $article->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
                    }
                }
                $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');
                
                return [
                    'id' => $article->id,
                    'category' => strtoupper($categoryName),
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'title' => $article->article_title,
                    'slug' => $article->article_slug,
                    'featuredImage' => $featuredImageUrl,
                    'commentCount' => 0
                ];
            })->values();

            // Process first three articles
            $processedFirstThree = $firstThreeArticles->map(function ($article) {
                $categoryName = $article->category ? $article->category->category_name : 'Uncategorized';
                $featuredImageUrl = null;
                if ($article->article_featured_image) {
                    if (filter_var($article->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $article->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
                    }
                }
                $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');
                
                return [
                    'id' => $article->id,
                    'category' => strtoupper($categoryName),
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'title' => $article->article_title,
                    'slug' => $article->article_slug,
                    'featuredImage' => $featuredImageUrl,
                    'commentCount' => 0
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'heroArticles' => $processedHeroArticles,
                    'firstThreeArticles' => $processedFirstThree
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function latestArticles(Request $request)
    {
        try {
            // IMPORTANT: This function is ONLY used by the Articles page (/articles)
            // It excludes UNIVERSITY NEWS category because that category has its own section (.recent-news-content)
            // Category pages use articlesByCategory() which does NOT exclude any categories
            // Get the UNIVERSITY NEWS category ID to exclude
            $universityNewsCategory = stp_article_category::where('data_status', 1)
                ->whereRaw('UPPER(category_name) = ?', ['UNIVERSITY NEWS'])
                ->first();
            
            $excludeCategoryId = $universityNewsCategory ? $universityNewsCategory->id : null;

            // Build query for articles excluding UNIVERSITY NEWS category
            $baseQuery = stp_article::where('data_status', 1)
                ->with('category')
                ->orderBy('article_date', 'DESC')
                ->orderBy('id', 'DESC'); // Secondary sort for consistency
            
            // Exclude UNIVERSITY NEWS category if it exists
            if ($excludeCategoryId) {
                $baseQuery->where('category_id', '!=', $excludeCategoryId);
            }

            // Fetch the single most recent article (featured)
            $featuredArticle = (clone $baseQuery)->first();

            $featuredArticleData = null;
            $latestArticlesData = [];

            if ($featuredArticle) {
                // Process featured article
                $categoryName = $featuredArticle->category ? $featuredArticle->category->category_name : 'Uncategorized';
                $featuredImageUrl = null;
                if ($featuredArticle->article_featured_image) {
                    if (filter_var($featuredArticle->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $featuredArticle->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($featuredArticle->article_featured_image, '/');
                    }
                }
                $formattedDate = \Carbon\Carbon::parse($featuredArticle->article_date)->format('F j, Y');
                
                $featuredArticleData = [
                    'id' => $featuredArticle->id,
                    'isFeatured' => true,
                    'image' => $featuredImageUrl,
                    'category' => strtoupper($categoryName),
                    'author' => $featuredArticle->article_author,
                    'date' => $formattedDate,
                    'commentCount' => 0,
                    'title' => $featuredArticle->article_title,
                    'slug' =>$featuredArticle->article_slug,
                    'excerpt' => null // Featured articles don't have excerpt
                ];

                // Fetch the next most recent articles after the featured one
                // Exclude the featured article and UNIVERSITY NEWS category
                $latestQuery = stp_article::where('data_status', 1)
                    ->with('category')
                    ->where('id', '!=', $featuredArticle->id)
                    ->orderBy('article_date', 'DESC')
                    ->orderBy('id', 'DESC');
                
                // Exclude UNIVERSITY NEWS category if it exists
                if ($excludeCategoryId) {
                    $latestQuery->where('category_id', '!=', $excludeCategoryId);
                }
                
                // Fetch a maximum of 4 latest articles (after the featured one)
                $latestArticles = $latestQuery
                    ->limit(4)
                    ->get();

                // Process latest articles
                $latestArticlesData = $latestArticles->map(function ($article) {
                    $categoryName = $article->category ? $article->category->category_name : 'Uncategorized';
                    $featuredImageUrl = null;
                    if ($article->article_featured_image) {
                        if (filter_var($article->article_featured_image, FILTER_VALIDATE_URL)) {
                            $featuredImageUrl = $article->article_featured_image;
                        } else {
                            $baseUrl = url('/');
                            $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
                        }
                    }
                    $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');
                    
                    // Extract excerpt from article content (HTML file) or use summary as fallback
                    $excerpt = null;
                    if ($article->article_content && file_exists(public_path('storage/' . $article->article_content))) {
                        // Read HTML content from file
                        $htmlContent = file_get_contents(public_path('storage/' . $article->article_content));
                        // Strip HTML tags and get plain text
                        $plainText = strip_tags($htmlContent);
                        // Clean up whitespace
                        $plainText = preg_replace('/\s+/', ' ', trim($plainText));
                        // Get first 150 characters
                        if (strlen($plainText) > 150) {
                            $excerpt = substr($plainText, 0, 150) . '...';
                        } else {
                            $excerpt = $plainText;
                        }
                    } elseif ($article->article_summary) {
                        // Fallback to summary if content file doesn't exist
                        $excerpt = strlen($article->article_summary) > 150 
                            ? substr($article->article_summary, 0, 150) . '...' 
                            : $article->article_summary;
                    }
                    
                    return [
                        'id' => $article->id,
                        'isFeatured' => false,
                        'image' => $featuredImageUrl,
                        'slug' => $article->article_slug,
                        'category' => strtoupper($categoryName),
                        'author' => $article->article_author,
                        'date' => $formattedDate,
                        'commentCount' => 0,
                        'title' => $article->article_title,
                        'excerpt' => $excerpt
                    ];
                })->values()->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'featuredArticle' => $featuredArticleData,
                    'latestArticles' => $latestArticlesData
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recentNewsArticles(Request $request)
    {
        try {
            // Find the UNIVERSITY NEWS category
            $universityNewsCategory = stp_article_category::where('data_status', 1)
                ->whereRaw('UPPER(category_name) = ?', ['UNIVERSITY NEWS'])
                ->first();

            if (!$universityNewsCategory) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // Fetch up to 4 UNIVERSITY NEWS articles ordered by published date (DESC)
            $articles = stp_article::where('data_status', 1)
                ->where('category_id', $universityNewsCategory->id)
                ->with('category')
                ->orderBy('article_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->limit(4)
                ->get();

            $processed = $articles->map(function ($article) {
                $categoryName = $article->category ? $article->category->category_name : 'UNIVERSITY NEWS';

                // Build featured image URL if available
                $featuredImageUrl = null;
                if ($article->article_featured_image) {
                    if (filter_var($article->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $article->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
                    }
                }

                $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');

                return [
                    'id' => $article->id,
                    'image' => $featuredImageUrl,
                    'title' => $article->article_title,
                    'slug' => $article->article_slug,
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'category' => strtoupper($categoryName),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $processed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trending articles for student portal based on highest total visit count.
     * Returns up to 4 articles ordered by total visits (DESC), then latest date.
     */
    public function trendingArticles(Request $request)
    {
        try {
            $limit = 4;

            // Build query joining articles with visits for total visit count
            $rows = DB::table('stp_article as a')
                ->leftJoin('stp_article_visits as v', 'v.article_id', '=', 'a.id')
                ->leftJoin('stp_article_category as c', 'c.id', '=', 'a.category_id')
                ->where('a.data_status', 1) // Only active articles
                ->select(
                    'a.id',
                    'a.article_title',
                    'a.article_author',
                    'a.article_slug',
                    'a.article_date',
                    'a.article_featured_image',
                    'c.category_name',
                    DB::raw('COALESCE(SUM(v.totalNumberVisit), 0) as total_visit')
                )
                ->groupBy(
                    'a.id',
                    'a.article_title',
                    'a.article_author',
                    'a.article_slug',
                    'a.article_date',
                    'a.article_featured_image',
                    'c.category_name'
                )
                ->orderByDesc('total_visit')
                ->orderBy('a.article_date', 'DESC')
                ->orderBy('a.id', 'DESC')
                ->limit($limit)
                ->get();

            $processed = $rows->map(function ($row) {
                $categoryName = $row->category_name ?: 'Uncategorized';

                // Build featured image URL if available
                $featuredImageUrl = null;
                if (!empty($row->article_featured_image)) {
                    if (filter_var($row->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $row->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($row->article_featured_image, '/');
                    }
                }

                $formattedDate = $row->article_date
                    ? \Carbon\Carbon::parse($row->article_date)->format('F j, Y')
                    : '';

                return [
                    'id' => $row->id,
                    'image' => $featuredImageUrl,
                    'title' => $row->article_title,
                    'category' => strtoupper($categoryName),
                    'author' => $row->article_author,
                    'date' => $formattedDate,
                    'slug' => $row->article_slug,
                    // Comment count is hardcoded for now as requested
                    'commentCount' => 0,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $processed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function trendingArticlesByCategory(Request $request)
    {
        try {
            $request->validate([
                'categorySlug' => 'required|string'
            ]);

            $limit = 4;

            // Find category by slug
            $category = stp_article_category::where('data_status', 1)
                ->get()
                ->first(function ($cat) use ($request) {
                    $slug = \Illuminate\Support\Str::slug($cat->category_name, '-');
                    return $slug === $request->categorySlug;
                });

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            // Build query joining articles with visits for total visit count, filtered by category
            $rows = DB::table('stp_article as a')
                ->leftJoin('stp_article_visits as v', 'v.article_id', '=', 'a.id')
                ->leftJoin('stp_article_category as c', 'c.id', '=', 'a.category_id')
                ->where('a.data_status', 1) // Only active articles
                ->where('a.category_id', $category->id) // Filter by category
                ->select(
                    'a.id',
                    'a.article_title',
                    'a.article_slug',
                    'a.article_author',
                    'a.article_date',
                    'a.article_featured_image',
                    'c.category_name',
                    DB::raw('COALESCE(SUM(v.totalNumberVisit), 0) as total_visit')
                )
                ->groupBy(
                    'a.id',
                    'a.article_title',
                    'a.article_slug',
                    'a.article_author',
                    'a.article_date',
                    'a.article_featured_image',
                    'c.category_name'
                )
                ->orderByDesc('total_visit')
                ->orderBy('a.article_date', 'DESC')
                ->orderBy('a.id', 'DESC')
                ->limit($limit)
                ->get();

            $processed = $rows->map(function ($row) {
                $categoryName = $row->category_name ?: 'Uncategorized';

                // Build featured image URL if available
                $featuredImageUrl = null;
                if (!empty($row->article_featured_image)) {
                    if (filter_var($row->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $row->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($row->article_featured_image, '/');
                    }
                }

                $formattedDate = $row->article_date
                    ? \Carbon\Carbon::parse($row->article_date)->format('F j, Y')
                    : '';

                return [
                    'id' => $row->id,
                    'image' => $featuredImageUrl,
                    'title' => $row->article_title,
                    'category' => strtoupper($categoryName),
                    'author' => $row->article_author,
                    'slug' => $row->article_slug,
                    'date' => $formattedDate,
                    // Comment count is hardcoded for now as requested
                    'commentCount' => 0,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $processed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function featuredArticlesByCategory(Request $request)
    {
        try {
            $request->validate([
                'categorySlug' => 'required|string'
            ]);

            $limit = 3;

            // Find category by slug
            $category = stp_article_category::where('data_status', 1)
                ->get()
                ->first(function ($cat) use ($request) {
                    $slug = \Illuminate\Support\Str::slug($cat->category_name, '-');
                    return $slug === $request->categorySlug;
                });

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            // Fetch up to 3 random featured articles from this category
            $articles = stp_article::where('data_status', 1)
                ->where('category_id', $category->id)
                ->where('article_featured', 1)
                ->with('category')
                ->inRandomOrder()
                ->limit($limit)
                ->get();

            $processed = $articles->map(function ($article) {
                $categoryName = $article->category ? $article->category->category_name : 'Uncategorized';

                $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');

                // Extract excerpt from article content (HTML file) or use summary as fallback
                $excerpt = null;
                if ($article->article_content && file_exists(public_path('storage/' . $article->article_content))) {
                    $htmlContent = file_get_contents(public_path('storage/' . $article->article_content));
                    $plainText = strip_tags($htmlContent);
                    $plainText = preg_replace('/\s+/', ' ', trim($plainText));
                    if (strlen($plainText) > 150) {
                        $excerpt = substr($plainText, 0, 150) . '...';
                    } else {
                        $excerpt = $plainText;
                    }
                } elseif ($article->article_summary) {
                    $excerpt = strlen($article->article_summary) > 150
                        ? substr($article->article_summary, 0, 150) . '...'
                        : $article->article_summary;
                }

                return [
                    'id' => $article->id,
                    'title' => $article->article_title,
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'category' => strtoupper($categoryName),
                    'excerpt' => $excerpt,
                    'slug' => $article->article_slug,
                    // Keep hardcoded for now as requested
                    'commentCount' => 0,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $processed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function articlesByCategory(Request $request)
    {
        try {
            $request->validate([
                'categorySlug' => 'required|string'
            ]);

            // IMPORTANT: This function treats ALL categories equally, including University News
            // There is NO exclusion logic here - all categories get the same treatment
            // Find category by slug
            $category = stp_article_category::where('data_status', 1)
                ->get()
                ->first(function ($cat) use ($request) {
                    $slug = \Illuminate\Support\Str::slug($cat->category_name, '-');
                    return $slug === $request->categorySlug;
                });

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            // Fetch random articles from this category
            // IMPORTANT: For latest-articles-category, we want TRUE randomization
            // Get a completely random article from ALL articles in the category (not just featured)
            // This ensures latest-articles-category shows different articles on each refresh
            // Previously prioritized article_featured=1, which caused same article to always show
            $featuredArticle = stp_article::where('data_status', 1)
                ->where('category_id', $category->id)
                ->with('category')
                ->inRandomOrder()
                ->first();

            // Note: $otherArticles was previously fetched but not used in the response
            // Removed to avoid confusion - only featuredArticle and latestArticles are returned

            // Process featured article
            $featuredArticleData = null;
            $excludeIds = [];
            
            if ($featuredArticle) {
                $categoryName = $featuredArticle->category ? $featuredArticle->category->category_name : 'Uncategorized';
                
                // Format featured image URL
                $featuredImageUrl = null;
                if ($featuredArticle->article_featured_image) {
                    if (filter_var($featuredArticle->article_featured_image, FILTER_VALIDATE_URL)) {
                        $featuredImageUrl = $featuredArticle->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $featuredImageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($featuredArticle->article_featured_image, '/');
                    }
                }
                
                $formattedDate = \Carbon\Carbon::parse($featuredArticle->article_date)->format('F j, Y');
                
                $featuredArticleData = [
                    'id' => $featuredArticle->id,
                    'isFeatured' => true,
                    'category' => strtoupper($categoryName),
                    'author' => $featuredArticle->article_author,
                    'date' => $formattedDate,
                    'title' => $featuredArticle->article_title,
                    'slug' => $featuredArticle->article_slug,
                    'featuredImage' => $featuredImageUrl,
                    'commentCount' => 0, // Hardcoded as requested
                    'mainPoint1' => $featuredArticle->article_main_points_1 ?? '',
                    'mainPoint2' => $featuredArticle->article_main_points_2 ?? '',
                    'mainPoint3' => $featuredArticle->article_main_points_3 ?? ''
                ];
                
                // Add featured article ID to exclude list
                $excludeIds[] = $featuredArticle->id;
            }

            // Fetch random articles from this category (for recent-news-content-category section)
            // Exclude the featured article and limit to 5
            // IMPORTANT: This is completely separate from latest-articles-category (featuredArticle)
            // Both sections are randomized independently and do not influence each other
            $latestArticlesQuery = stp_article::where('data_status', 1)
                ->where('category_id', $category->id)
                ->with('category')
                ->inRandomOrder(); // Truly random, not ordered by date

            // Exclude featured article if it exists
            if (!empty($excludeIds)) {
                $latestArticlesQuery->whereNotIn('id', $excludeIds);
            }

            $latestArticles = $latestArticlesQuery->limit(5)->get();

            // Process latest articles
            $latestArticlesData = $latestArticles->map(function ($article) {
                $categoryName = $article->category ? $article->category->category_name : 'Uncategorized';
                
                // Format featured image URL
                $imageUrl = null;
                if ($article->article_featured_image) {
                    if (filter_var($article->article_featured_image, FILTER_VALIDATE_URL)) {
                        $imageUrl = $article->article_featured_image;
                    } else {
                        $baseUrl = url('/');
                        $imageUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($article->article_featured_image, '/');
                    }
                }
                
                $formattedDate = \Carbon\Carbon::parse($article->article_date)->format('F j, Y');
                
                return [
                    'id' => $article->id,
                    'title' => $article->article_title,
                    'slug' => $article->article_slug,
                    'author' => $article->article_author,
                    'date' => $formattedDate,
                    'featuredImage' => $imageUrl
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'featuredArticle' => $featuredArticleData,
                    'latestArticles' => $latestArticlesData,
                    'otherArticles' => [] // Not used in this section, but available if needed
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subscribe to newsletter
     * Only accepts Gmail addresses
     */
    public function subscribeNewsletter(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|ends_with:@gmail.com'
            ], [
                'email.required' => 'Email address is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.ends_with' => 'Only Gmail addresses are accepted for newsletter subscription.'
            ]);

            $email = strtolower(trim($request->email));

            // Check if email already exists
            $existing = NewsletterSubscription::where('email', $email)->first();

            if ($existing) {
                if ($existing->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email is already subscribed to our newsletter.'
                    ], 400);
                } else {
                    // Reactivate subscription
                    $existing->is_active = true;
                    $existing->subscribed_at = now();
                    $existing->unsubscribed_at = null;
                    $existing->save();

                    return response()->json([
                        'success' => true,
                        'message' => 'Your newsletter subscription has been reactivated!'
                    ]);
                }
            }

            // Create new subscription
            NewsletterSubscription::create([
                'email' => $email,
                'is_active' => true,
                'subscribed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully subscribed to newsletter!'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comment author display name: username (student_userName) so comment shows e.g. "ptt".
     * Falls back to Anonymous when no student.
     */
    private function getCommentAuthorDisplayName($comment): string
    {
        if (!$comment || !$comment->student) {
            return 'Anonymous';
        }
        $username = trim((string) ($comment->student->student_userName ?? ''));
        return $username !== '' ? $username : 'Anonymous';
    }

    /**
     * Get comment author profile picture: path (e.g. student_profile_pics/xyz.jpg) or full URL.
     * Frontend builds full URL with VITE_BASE_URL + 'storage/' + path when path does not start with http.
     */
    private function getCommentProfilePictureUrl($comment): ?string
    {
        if (!$comment || !$comment->student || empty(trim((string) ($comment->student->student_profilePic ?? '')))) {
            return null;
        }
        $path = trim($comment->student->student_profilePic);
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        return ltrim($path, '/');
    }

    /**
     * Generate XML sitemap for universities/schools.
     * Returns dynamically generated sitemap with slug-based URLs.
     */
    public function universitiesSitemap(Request $request)
    {
        try {
            $schools = stp_school::whereIn('school_status', [1, 3])
                ->whereNotNull('school_slug')
                ->select('school_slug', 'updated_at')
                ->withMax(['courses as active_courses_updated_at' => function ($query) {
                    $query->where('course_status', 1);
                }], 'updated_at')
                ->get();

            $urls = $schools->map(function ($school) {
                $latestUpdate = collect([
                    $school->updated_at,
                    $school->active_courses_updated_at,
                ])->filter()->max();
                $lastmod = $latestUpdate ? date('Y-m-d\TH:i:sP', strtotime($latestUpdate)) : date('Y-m-d\TH:i:sP');

                $loc = htmlspecialchars(
                    $this->sitemapUrl("/university-details/{$school->school_slug}"),
                    ENT_XML1,
                    'UTF-8'
                );

                return "
                  <url>
                    <loc>{$loc}</loc>
                    <lastmod>{$lastmod}</lastmod>
                  </url>";
            })->join('');

            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
              <urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">{$urls}
              </urlset>";

            return response($xml, 200)
                ->header('Content-Type', 'application/xml');
        } catch (\Exception $e) {
            return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>Error generating sitemap: " . htmlspecialchars($e->getMessage()) . "</error>", 500)
                ->header('Content-Type', 'application/xml');
        }
    }

    private function slugify($text)
    {
        $slug = strtolower(trim(preg_replace('/[^\w\s-]/', '', $text)));
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/\-+/', '-', $slug);
        return trim($slug, '-');
    }

    private function sitemapUrl(string $path = '/'): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $normalizedPath = $normalizedPath === '/'
            ? '/'
            : rtrim($normalizedPath, '/');

        return 'https://studypal.my' . $normalizedPath;
    }

    /**
     * Generate XML sitemap for courses.
     * Returns dynamically generated sitemap with slug-based URLs.
     * Example URL: https://studypal.my/course-details/linton-university-college/diploma-in-fashion-design
     */
    public function coursesSitemap(Request $request)
    {
        try {
            // Get all active courses from active/temporary schools with slugs
            $courses = stp_course::with('school')
                ->whereHas('school', function ($query) {
                    $query->whereIn('school_status', [1, 3]) // Active or Temporary schools only
                          ->whereNotNull('school_slug');
                })
                ->where('course_status', 1) // Only active courses
                ->whereNotNull('course_slug')
                ->select('course_slug', 'school_id', 'updated_at')
                ->get();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($courses as $course) {
                // Skip courses without a school or without slugs
                if (!$course->school || !$course->school->school_slug || !$course->course_slug) {
                    continue;
                }

                $latestUpdate = collect([
                    $course->updated_at,
                    $course->school->updated_at,
                ])->filter()->max();
                $lastmod = $latestUpdate
                    ? date('Y-m-d\TH:i:sP', strtotime($latestUpdate))
                    : date('Y-m-d\TH:i:sP');

                $loc = htmlspecialchars(
                    $this->sitemapUrl('/course-details/' . $course->school->school_slug . '/' . $course->course_slug),
                    ENT_XML1,
                    'UTF-8'
                );

                $xml .= '
                <url>
                    <loc>' . $loc . '</loc>
                    <lastmod>' . $lastmod . '</lastmod>
                </url>';
            }

            $xml .= '</urlset>';

            return response($xml, 200)
                ->header('Content-Type', 'application/xml');
        } catch (\Exception $e) {
            return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>Error generating sitemap: " . htmlspecialchars($e->getMessage()) . "</error>", 500)
                ->header('Content-Type', 'application/xml');
        }
    }

    /**
     * Generate XML sitemap for course listing landing pages.
     * Includes /courses/:schoolSlug, /courses/:categorySlug, and real /courses/:schoolSlug/:categorySlug pairs.
     */
    public function courseListingsSitemap(Request $request)
    {
        try {
            $now = date('Y-m-d\TH:i:sP');

            $schools = stp_school::whereIn('school_status', [1, 3])
                ->whereNotNull('school_slug')
                ->where('school_slug', '<>', '')
                ->whereHas('courses', function ($query) {
                    $query->where('course_status', 1);
                })
                ->select('id', 'school_slug', 'updated_at')
                ->withMax(['courses as active_courses_updated_at' => function ($query) {
                    $query->where('course_status', 1);
                }], 'updated_at')
                ->get();

            $categories = stp_courses_category::where('category_status', 1)
                ->whereHas('courses', function ($query) {
                    $query->where('course_status', 1)
                        ->whereHas('school', function ($schoolQuery) {
                            $schoolQuery->whereIn('school_status', [1, 3])
                                ->whereNotNull('school_slug')
                                ->where('school_slug', '<>', '');
                        });
                })
                ->select('id', 'category_name', 'updated_at')
                ->withMax(['courses as active_courses_updated_at' => function ($query) {
                    $query->where('course_status', 1)
                        ->whereHas('school', function ($schoolQuery) {
                            $schoolQuery->whereIn('school_status', [1, 3]);
                        });
                }], 'updated_at')
                ->get();

            $schoolCategoryPairs = stp_course::with(['school:id,school_slug,updated_at', 'category:id,category_name,updated_at'])
                ->where('course_status', 1)
                ->whereNotNull('category_id')
                ->whereHas('school', function ($query) {
                    $query->whereIn('school_status', [1, 3])
                        ->whereNotNull('school_slug')
                        ->where('school_slug', '<>', '');
                })
                ->whereHas('category', function ($query) {
                    $query->where('category_status', 1);
                })
                ->select('school_id', 'category_id', DB::raw('MAX(updated_at) as updated_at'))
                ->groupBy('school_id', 'category_id')
                ->get();

            $urls = collect();

            foreach ($schools as $school) {
                $latestUpdate = collect([
                    $school->updated_at,
                    $school->active_courses_updated_at,
                ])->filter()->max();
                $lastmod = $latestUpdate
                    ? date('Y-m-d\TH:i:sP', strtotime($latestUpdate))
                    : $now;

                $urls->push([
                    'loc' => $this->sitemapUrl("/courses/{$school->school_slug}"),
                    'lastmod' => $lastmod,
                ]);
            }

            foreach ($categories as $category) {
                $categorySlug = $this->slugify($category->category_name);
                if ($categorySlug === '') {
                    continue;
                }

                $latestUpdate = collect([
                    $category->updated_at,
                    $category->active_courses_updated_at,
                ])->filter()->max();
                $lastmod = $latestUpdate
                    ? date('Y-m-d\TH:i:sP', strtotime($latestUpdate))
                    : $now;

                $urls->push([
                    'loc' => $this->sitemapUrl("/courses/{$categorySlug}"),
                    'lastmod' => $lastmod,
                ]);
            }

            foreach ($schoolCategoryPairs as $pair) {
                if (!$pair->school || !$pair->category || !$pair->school->school_slug) {
                    continue;
                }

                $categorySlug = $this->slugify($pair->category->category_name);
                if ($categorySlug === '') {
                    continue;
                }

                $lastmod = $pair->updated_at
                    ? date('Y-m-d\TH:i:sP', strtotime($pair->updated_at))
                    : $now;

                $urls->push([
                    'loc' => $this->sitemapUrl("/courses/{$pair->school->school_slug}/{$categorySlug}"),
                    'lastmod' => $lastmod,
                ]);
            }

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($urls->unique('loc')->sortBy('loc') as $url) {
                $xml .= '
                <url>
                    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>
                    <lastmod>' . $url['lastmod'] . '</lastmod>
                </url>';
            }

            $xml .= '</urlset>';

            return response($xml, 200)
                ->header('Content-Type', 'application/xml');
        } catch (\Exception $e) {
            return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>Error generating sitemap: " . htmlspecialchars($e->getMessage()) . "</error>", 500)
                ->header('Content-Type', 'application/xml');
        }
    }

    /**
     * Generate XML sitemap for articles.
     * Returns dynamically generated sitemap with canonical slug-based URLs.
     * Example URL: https://studypal.my/articles/read/a-student-s-guide-to-studying-abroad
     */
    public function articlesSitemap(Request $request)
    {
        try {
            $articles = stp_article::where('data_status', 1)
                ->whereNotNull('article_slug')
                ->where('article_slug', '<>', '')
                ->select('article_slug', 'updated_at')
                ->orderBy('updated_at', 'desc')
                ->get();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($articles as $article) {
                $slug = trim((string) $article->article_slug);
                if ($slug === '') {
                    continue;
                }

                $lastmod = $article->updated_at
                    ? date('Y-m-d\TH:i:sP', strtotime($article->updated_at))
                    : date('Y-m-d\TH:i:sP');

                $loc = htmlspecialchars(
                    $this->sitemapUrl("/articles/read/{$slug}"),
                    ENT_XML1,
                    'UTF-8'
                );

                $xml .= '
                <url>
                    <loc>' . $loc . '</loc>
                    <lastmod>' . $lastmod . '</lastmod>
                </url>';
            }

            $xml .= '</urlset>';

            return response($xml, 200)
                ->header('Content-Type', 'application/xml');
        } catch (\Exception $e) {
            return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>Error generating sitemap: " . htmlspecialchars($e->getMessage()) . "</error>", 500)
                ->header('Content-Type', 'application/xml');
        }
    }
}
