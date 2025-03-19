<?php

namespace App\Http\Controllers;

use App\Models\stp_city;
use App\Models\stp_transcript;
use Illuminate\Http\Request;
use App\Models\stp_student;
use App\Models\stp_country;
use App\Models\stp_course;
use App\Models\stp_course_tag;
use App\Models\stp_courses_category;
use App\Models\stp_submited_form;
use App\Models\stp_achievement;
use App\Models\stp_student_media;
use App\Models\stp_student_detail;
use Illuminate\Support\Facades\DB;
use App\Models\stp_featured;
use App\Models\stp_school;
use App\Models\stp_state;
use App\Models\stp_subject;
use App\Models\stp_tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image as Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\serviceFunctionController;
use App\Models\stp_cgpa;
use App\Models\stp_cocurriculum;
use App\Models\stp_core_meta;
use App\Models\stp_courseInterest;
use App\Models\stp_featured_price;
use App\Models\stp_featured_request;
use App\Models\stp_higher_transcript;
use App\Models\stp_intake;
use App\Models\stp_other_certificate;
use App\Models\stp_school_media;
use PHPUnit\TextUI\Help;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ResponseSequence;
use Throwable;

use function Laravel\Prompts\error;

class SchoolController extends Controller
{
    protected $serviceFunctionController;

    public function __construct(serviceFunctionController $serviceFunctionController)
    {
        $this->serviceFunctionController = $serviceFunctionController;
    }

    public function schoolDetail()
    {
        try {
            $authUser = Auth::user();
            return response()->json([
                'success' => true,
                'data' => $authUser
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function coursesList(Request $request)
    {

        try {
            $authUser = Auth::user();

            $courseList = stp_course::query()
                ->where('course_status', 1)
                ->where('school_id', $authUser->id)
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->where('category_id', $request->category);
                })
                ->when($request->filled('qualification'), function ($query) use ($request) {
                    $query->where('qualification_id', $request->qualification);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('course_name', 'like', '%' . $request->search . '%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(100)
                ->through(function ($course) {
                    $status = ($course->course_status == 1) ? "Active" : "Inactive";
                    $intakeMonths = $course->intake->where('intake_status', 1)->pluck('month.core_metaName')->toArray();
                    return [
                        'id' => $course->id,
                        'school_name' => $course->school->school_name,
                        'name' => $course->course_name,
                        'description' => $course->course_description,
                        'requirement' => $course->course_requirement,
                        'cost' => $course->course_cost,
                        'period' => $course->course_period,
                        'intake' => $intakeMonths,
                        'category' => $course->category->category_name,
                        'qualification' => $course->qualification->qualification_name,
                        'mode' => $course->studyMode->core_metaName ?? null,
                        'logo' => $course->course_logo ?? $course->school->school_logo,
                        'location' => $course->school->country->country_name ?? null,
                        'institute_category' => $course->school->institueCategory->core_metaName ?? null
                    ];
                });

            return $courseList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function addCourse(Request $request)
    {
        try {
            $request->validate([
                'schoolID' => 'required|integer',
                'name' => 'required|string|max:255',
                'description' => 'string',
                'requirement' => 'string',
                'cost' => 'required|regex:/^\d+(\.\d{1,2})?$/',
                'period' => 'required|string|max:255',
                'intake' => 'required|array',
                'category' => 'required|integer',
                'mode' => 'required|integer',
                // 'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Image validation
            ]);
            // $validator = Validator::make($request->all(), [
            //     'schoolID' => 'required|integer',
            //     'name' => 'required|string|max:255',
            //     'description' => 'nullable|string|max:255',
            //     'requirement' => 'nullable|string|max:255',
            //     'cost' => 'required|regex:/^\d+(\.\d{1,2})?$/',
            //     'period' => 'required|string|max:255',
            //     'intake' => 'required|array',
            //     'category' => 'required|integer',
            //     'mode' => 'required|integer',
            // ]);

            $authUser = Auth::user();
            $checkingCourse = stp_course::where('school_id', $request->schoolID)
                ->where('course_name', $request->name)
                ->exists();

            $error = [];

            //check course exist or not
            if ($checkingCourse) {
                $error["courses"] = 'Courses already exist in the school';
            }

            //check logo format correct or not
            if ($request->hasFile('logo')) {
                $allowedExtensions = ['jpeg', 'png', 'jpg', 'gif', 'svg'];
                $logo = $request->file('logo');
                $extension = strtolower($logo->getClientOriginalExtension());

                // Check if the file extension is in the allowed list
                if (!in_array($extension, $allowedExtensions)) {
                    $error["logo"] = 'The logo must be a file of type: jpeg, png, jpg, gif, svg.';
                }
            }

            if (count($error) > 0) {
                throw ValidationException::withMessages($error);
            }

            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $createCourse = stp_course::create([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'study_mode' => $request->mode,
                'course_logo' => $imagePath ?? null,
                'course_status' => 1,
                'created_by' => $authUser->id,
                'created_at' => now(),
            ]);

            $newIntakeData = [];
            foreach ($request->intake as $intakeMonth) {
                $newIntakeData[] = [
                    'course_id' => $createCourse->id,
                    'intake_month' => $intakeMonth,
                    'created_by' => $authUser->id
                ];
            };

            stp_intake::insert($newIntakeData);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the Course']
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

    // public function addCourse(Request $request)
    // {
    //     try {
    //         // Main validation for request fields excluding the logo
    //         $validator = Validator::make($request->all(), [
    //             'schoolID' => 'required|integer',
    //             'name' => 'required|string|max:255',
    //             'description' => 'nullable|string|max:255',
    //             'requirement' => 'nullable|string|max:255',
    //             'cost' => 'required|regex:/^\d+(\.\d{1,2})?$/',
    //             'period' => 'required|string|max:255',
    //             'intake' => 'required|array',
    //             'category' => 'required|integer',
    //             'mode' => 'required|integer',
    //         ]);

    //         $authUser = Auth::user();
    //         $checkingCourse = stp_course::where('school_id', $request->schoolID)
    //             ->where('course_name', $request->name)
    //             ->exists();

    //         // Add course error if the course already exists
    //         if ($checkingCourse) {
    //             $validator->errors()->add('courses', 'Course name already exists in the school.');
    //         }

    //         // Validate the logo separately if it exists
    //         if ($request->hasFile('logo')) {
    //             $logoValidator = Validator::make($request->all(), [
    //                 'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
    //             ]);

    //             // Merge logo errors if any
    //             if ($logoValidator->fails()) {
    //                 $validator->errors()->merge($logoValidator->errors());
    //             }
    //         }

    //         // Check if any errors exist after merging
    //         if ($validator->fails()) {
    //             // Explicitly throw the ValidationException with the combined errors
    //             throw new ValidationException($validator);
    //         }

    //         // Handle file upload
    //         if ($request->hasFile('logo')) {
    //             $image = $request->file('logo');
    //             $imageName = time() . '.' . $image->getClientOriginalExtension();
    //             $imagePath = $image->storeAs('courseLogo', $imageName, 'public');
    //         }

    //         // Create the course
    //         $createCourse = stp_course::create([
    //             'school_id' => $request->schoolID,
    //             'course_name' => $request->name,
    //             'course_description' => $request->description ?? null,
    //             'course_requirement' => $request->requirement ?? null,
    //             'course_cost' => $request->cost,
    //             'course_period' => $request->period,
    //             'category_id' => $request->category,
    //             'qualification_id' => $request->qualification,
    //             'study_mode' => $request->mode,
    //             'course_logo' => $imagePath ?? null,
    //             'course_status' => 1,
    //             'created_by' => $authUser->id,
    //             'created_at' => now(),
    //         ]);

    //         // Insert intake data
    //         $newIntakeData = [];
    //         foreach ($request->intake as $intakeMonth) {
    //             $newIntakeData[] = [
    //                 'course_id' => $createCourse->id,
    //                 'intake_month' => $intakeMonth,
    //                 'created_by' => $authUser->id
    //             ];
    //         };

    //         stp_intake::insert($newIntakeData);

    //         return response()->json([
    //             'success' => true,
    //             'data' => ['message' => 'Successfully Added the Course']
    //         ]);
    //     } catch (ValidationException $e) {
    //         // Return combined validation errors
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation Error',
    //             'error' => $e->errors()
    //         ]);
    //     } catch (\Exception $e) {
    //         // Handle other exceptions
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Internal Server Error',
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }
    public function courseDetail(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'courseID' => 'required|integer'
            ]);
            $getCourseDetail = stp_course::where('id', $request->courseID)
                ->where('school_id', $authUser->id)
                ->first();
            if (empty($getCourseDetail)) {
                throw ValidationException::withMessages([
                    'course' => ['course does not exist in your institute']
                ]);
            }


            $months = [];
            foreach ($getCourseDetail->intake as $month) {
                if ($month->intake_status == 1) {
                    $months[] = [
                        'id' => $month->month->id,
                        'core_metaName' => $month->month->core_metaName
                    ];
                }
            }

            if (empty($getCourseDetail->course_logo)) {
                $course_logo = $getCourseDetail->school->school_logo;
            } else {
                $course_logo = $getCourseDetail->course_logo;
            }

            $data = [
                'id' => $getCourseDetail->id,
                'course_name' => $getCourseDetail->course_name,
                'course_description' => $getCourseDetail->course_description,
                'course_requirement' => $getCourseDetail->course_requirement,
                'course_cost' => $getCourseDetail->course_cost,
                'course_period' => $getCourseDetail->course_period,
                'course_intake' => $months,
                'category' => [
                    'categoryId' => $getCourseDetail->category->id,
                    'categoryName' => $getCourseDetail->category->category_name
                ],
                'qualification' =>  [
                    'qualificationId' => $getCourseDetail->qualification->id,
                    'qualificationName' => $getCourseDetail->qualification->qualification_name
                ],
                'study_mode' => [
                    'studyModeId' => $getCourseDetail->studyMode->id,
                    'studyModeName' => $getCourseDetail->studyMode->core_metaName
                ],
                'course_logo' => $course_logo,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function editCourse(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'schoolID' => 'required|integer',
                'name' => 'required|string',
                'description' => 'string',
                'requirement' => 'string',
                'cost' => 'required|regex:/^\d+(\.\d{1,2})?$/',
                'period' => 'required|string',
                'intake' => 'required|array',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Image validation
            ]);

            $checkingCourse = stp_course::where('school_id', $request->schoolID)
                ->where('course_name', $request->name)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingCourse) {
                throw ValidationException::withMessages([
                    "courses" => ['Courses name already exist in the school']
                ]);
            }

            $courses = stp_course::find($request->id);
            $imagePath = "";
            if ($request->hasFile('logo')) {
                if (!empty($courses->course_logo)) {
                    Storage::delete('public/' . $courses->course_logo);
                }
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $data = [
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'study_mode' => $request->mode,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ];

            if ($imagePath != null) {
                $data['course_logo'] = $imagePath;
            }



            $courses->update($data);

            $getIntake = stp_intake::where("course_id", $request->id)->where('intake_status', 1)->get();
            $existingMonth = $getIntake->pluck('intake_month')->toArray();



            $new = array_diff($request->intake, $existingMonth);
            $remove = array_diff($existingMonth, $request->intake);

            $checkExistingNewIntake = stp_intake::where('course_id', $request->id)->whereIn('intake_month', $new)->get();
            if (count($checkExistingNewIntake) > 0) {
                foreach ($checkExistingNewIntake as $exist) {
                    $new = array_diff($new, [$exist['intake_month']]);
                    $exist->update([
                        'intake_status' => 1,
                        'updated_by' => $authUser->id
                    ]);
                }
            }

            $newIntakeData = [];
            foreach ($new as $newIntake) {
                $newIntakeData[] = [
                    'course_id' => $request->id,
                    'intake_month' => $newIntake,
                    'created_by' => $authUser->id
                ];
            }

            stp_intake::insert($newIntakeData);

            stp_intake::where('course_id', $request->id)
                ->whereIn('intake_month', $remove)
                ->update([
                    'intake_status' => 0,
                    'updated_by' => $authUser->id
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
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
                "errors" => $e->getMessage()
            ]);
        }
    }

    public function editCourseStatus(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255'
            ]);
            $authUser = Auth::user();

            if ($request->type == 'disable') {
                $status = 0;
                $message = "Successfully Disable the Course";
            } else {
                $status = 1;
                $message = "Successfully Enable the Course";
            }

            $course = stp_course::find($request->id);

            $course->update([
                'course_status' => $status,
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
        } catch (\Exception $e) {
            return response()->json([
                'succcess' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editSchoolDetail(Request $request)
    {
        try {
            $request->validate([
                // 'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'email' => 'required|string|max:255',
                'countryCode' => 'required|string|max:255',
                'contact' => 'required|string|max:255',
                'school_fullDesc' => 'required|string|max:255',
                'school_shortDesc' => 'required|string|max:255',
                'school_address' => 'required|string|max:255',
                'school_website' => 'nullable|string|max:255',
                'country' => 'required|integer',
                'state' => 'required|integer',
                'city' => 'required|integer',
                'category' => 'required|integer',
                'account_type' => 'required|integer'
                // 'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',

            ]);
            $authUser = Auth::user();

            $checkingEmail = stp_school::where('id', '!=', $authUser->id)
                ->where('school_email', $request->email)
                ->exists();

            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['email had been used']
                ]);
            }

            $checkingUserContact = stp_school::where('school_countryCode', $request->countryCode)
                ->where('school_contactNo', $request->contact)
                ->where('id', '!=', $authUser)
                ->exists();

            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }

            $school = stp_school::find($authUser->id);

            if ($request->hasFile('logo')) {
                if (!empty($school->school_logo)) {
                    Storage::delete('public/' . $school->school_logo);
                }
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $encodedPlace = urlencode($request->school_address);
            // Generate the Google Maps link
            $googleMapsLink = "https://www.google.com/maps/search/?api=1&query={$encodedPlace}";

            $embedUrl = "https://www.google.com/maps?q={$request->school_address}&output=embed";
            // Generate the iframe HTML
            $iframeCode = "<iframe src='{$embedUrl}' width='600' height='450' style='border:0;' allowfullscreen='' loading='lazy'></iframe>";


            $updateSchool = $school->update([
                'school_name' => $request->name,
                'school_email' => $request->email,
                'school_countryCode' => $request->countryCode,
                'school_contactNo' => $request->contact,
                'school_fullDesc' => $request->school_fullDesc,
                'school_shortDesc' => $request->school_shortDesc,
                'school_address' => $request->school_address,
                'country_id' => $request->country,
                'state_id' => $request->state,
                'city_id' => $request->city,
                'institue_category' => $request->category,
                'school_lg' => $request->lg,
                'school_lat' => $request->lat,
                'school_location' => $iframeCode,
                "school_google_map_location" => $googleMapsLink,
                'school_officalWebsite' => $request->school_website ?? null,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
                'account_type' => $request->account_type
            ]);

            return response()->json([
                "success" => true,
                "data" => ['message' => 'Update Successfully']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                "success" => false,
                "message" => "Validation Error",
                "errors" => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function applicantDetailInfo(Request $request)
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();
            $schoolID = $authUser->id;

            // Validate input fields
            $request->validate([
                'form_status' => 'integer|nullable',
                'student_id' => 'integer|nullable',
                'courses_id' => 'integer|nullable',
                'search' => 'string|nullable',
                'qualification_id' => 'integer|nullable'
            ]);

            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_submited_form::count() : (int)$request->per_page)
                : 10;

            // Define the custom order for form_status
            $statusOrder = [
                2 => 'Pending',
                4 => 'Accepted',
                1 => 'Active',
                3 => 'Rejected'
            ];

            // Fetch applicant info with student, course, award, and cocurriculum details
            $applicantInfo = stp_submited_form::with(['student', 'student.award.title', 'course', 'student.award' => function ($query) {
                $query->where('achievements_status', 1); // Filter by achievements_status = 1
            }, 'student.cocurriculum' => function ($query) {
                $query->where('cocurriculums_status', 1); // Filter by cocurriculums_status = 1
            }])
                ->whereHas('course', function ($query) use ($schoolID) {
                    $query->where('school_id', $schoolID);
                })
                ->where('form_status', '!=', 0)
                ->when($request->filled('student_id'), function ($query) use ($request) {
                    $query->where('student_id', $request->student_id);
                })
                ->when($request->filled('courses_id'), function ($query) use ($request) {
                    $query->where('courses_id', $request->courses_id);
                })
                ->when($request->filled('form_status'), function ($query) use ($request) {
                    $query->where('form_status', $request->form_status);
                })
                ->when($request->filled('qualification_id'), function ($query) use ($request) {
                    $query->whereHas('course', function ($subQuery) use ($request) {
                        $subQuery->where('qualification_id', $request->qualification_id);
                    });
                })

                // Add search filter for student name (first or last name)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->search;
                    $query->whereHas('student.detail', function ($query) use ($search) {
                        $query->where('student_detailFirstName', 'like', '%' . $search . '%')
                            ->orWhere('student_detailLastName', 'like', '%' . $search . '%');
                    });
                })
                // Apply custom order sorting by form_status
                ->orderByRaw("FIELD(form_status, 2, 4, 1, 3)")
                ->paginate($perPage)
                ->through(function ($applicant) use ($statusOrder) {
                    $status = $statusOrder[$applicant->form_status] ?? null;

                    // Filter and get all the award names and cocurriculum details with the desired status
                    // $awardNames = $applicant->student->award->filter(function($achievement) {
                    //     return $achievement->achievements_status == 1;
                    // })->pluck('achievement_name');

                    $awardTitles = $applicant->student->award->filter(function ($achievement) {
                        return $achievement->achievements_status == 1;
                    })->map(function ($achievement) {
                        return $achievement->title->core_metaName ?? null;
                    });


                    // $cocurriculumNames = $applicant->student->cocurriculum->filter(function($cocurriculum) {
                    //     return $cocurriculum->cocurriculums_status == 1;
                    // })->pluck('club_name');

                    $cocurriculumPositions = $applicant->student->cocurriculum->filter(function ($cocurriculum) {
                        return $cocurriculum->cocurriculums_status == 1;
                    })->pluck('student_position');

                    return [
                        "id" => $applicant->id ?? 'N/A',
                        "student_name" => $applicant->student->detail->student_detailFirstName . ' ' . $applicant->student->detail->student_detailLastName,
                        "profile_pic" => $applicant->student->student_profilePic,
                        "email" => $applicant->student->student_email,
                        "course_name" => $applicant->course->course_name ?? 'N/A',
                        "institution" => $applicant->course->school->school_name,
                        "form_status" => $status,
                        "country_code" => $applicant->student->student_countryCode ?? 'N/A',
                        "contact_number" => $applicant->student->student_contactNo ?? 'N/A',
                        "student_id" => $applicant->student->id,
                        "award_count" => $awardTitles->count(), // Count of award titles
                        "cocurriculum_count" => $cocurriculumPositions->count(), // Count of cocurriculum positions
                    ];
                });

            return response()->json($applicantInfo);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function resetSchoolPassword(Request $request)
    {
        try {
            $request->validate([
                'currentPassword' => 'required|string|min:8',
                'newPassword' => 'required|string|min:8',
                'confirmPassword' => 'required|string|min:8|same:newPassword'
            ]);
            $authUser = Auth::user();
            if (!Hash::check($request->currentPassword, $authUser->school_password)) {
                throw ValidationException::withMessages(["The provided credentials are incorrect."]);
            }

            $authUser->update([
                'school_password' => Hash::make($request->newPassword),
                'school_status' => 1,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['messenger' => "Successfully reset password"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'error' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function applicantDetailCocurriculum(Request $request)   //Cocurriculum list for the applicant
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();
            $schoolID = $authUser->id;

            $request->validate([
                'student_id' => 'integer|nullable'
            ]);

            // Select unique student_ids from stp_submited_form
            $uniqueStudents = stp_submited_form::with(['student.cocurriculum', 'course'])
                ->whereHas('course', function ($query) use ($schoolID) {
                    $query->where('school_id', $schoolID);
                })
                ->whereHas('student.cocurriculum', function ($query) {
                    $query->where('cocurriculums_status', 1);
                })
                ->when($request->filled('student_id'), function ($query) use ($request) {
                    $query->where('student_id', $request->student_id);
                })
                ->select('student_id')
                ->distinct() // Ensure each student_id is unique
                ->get()
                ->map(function ($form) {
                    $student = $form->student;
                    $course = $form->course;

                    $cocurriculums = $student->cocurriculum->map(function ($cocurriculum) {
                        return [
                            'club_name' => $cocurriculum->club_name,
                            'location' => $cocurriculum->location,
                            'position' => $cocurriculum->student_position,
                            'year' => $cocurriculum->year,
                        ];
                    });

                    // $test["cocurriculums"]=$cocurriculums;
                    // $test["school_id"]= $course->school_id ?? '';
                    // $test
                    return [
                        'cocurriculums' => $cocurriculums,
                        'school_id' => $course->school_id ?? '',
                        'student_id' => $student->id ?? '',
                    ];
                });

            // Paginate the results


            $paginatedResults = new \Illuminate\Pagination\LengthAwarePaginator(
                $uniqueStudents->forPage($request->page ?? 1, 10),
                $uniqueStudents->count(),
                10,
                $request->page ?? 1,
                ['path' => url()->current()]
            );

            return response()->json([
                'success' => true,
                'data' => $uniqueStudents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }
    public function applicantDetailAchievement(Request $request)   //Cocurriculum list for the applicant
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();
            $schoolID = $authUser->id;

            $request->validate([
                'student_id' => 'integer|nullable'
            ]);

            // Select unique student_ids from stp_submited_form
            $uniqueStudents = stp_submited_form::with(['student.award', 'course'])
                ->whereHas('course', function ($query) use ($schoolID) {
                    $query->where('school_id', $schoolID);
                })
                ->whereHas('student.award', function ($query) {
                    $query->where('achievements_status', 1);
                })
                ->when($request->filled('student_id'), function ($query) use ($request) {
                    $query->where('student_id', $request->student_id);
                })
                ->select('student_id')
                ->distinct() // Ensure each student_id is unique
                ->get()
                ->map(function ($form) {
                    $student = $form->student;
                    $course = $form->course;

                    $achievements = $student->award->map(function ($achievement) {
                        return [
                            'achievement_name' => $achievement->achievement_name,
                            'location' => $achievement->awarded_by,
                            'position' => $achievement->title->core_metaName ?? '',
                            'date' => $achievement->date ?? '',
                        ];
                    });

                    return [
                        'achievements' => $achievements,
                        'student_id' => $student->id ?? '',
                    ];
                });

            // Paginate the results
            $paginatedResults = new \Illuminate\Pagination\LengthAwarePaginator(
                $uniqueStudents->forPage($request->page ?? 1, 10),
                $uniqueStudents->count(),
                10,
                $request->page ?? 1,
                ['path' => url()->current()]
            );

            return response()->json([
                'success' => true,
                'data' => $paginatedResults
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }
    public function applicantDetailAcademic(Request $request) // Academic list for the applicant
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();
            $schoolID = $authUser->id;

            $request->validate([
                'student_id' => 'integer|nullable',
                'category' => 'integer|nullable'
            ]);

            // Select unique student_ids from stp_submited_form
            $uniqueStudents = stp_submited_form::with(['student.transcript.subject', 'course'])
                ->whereHas('course', function ($query) use ($schoolID) {
                    $query->where('school_id', $schoolID);
                })
                ->whereHas('student.transcript', function ($query) {
                    $query->where('transcript_status', 1);
                })
                ->when($request->filled('student_id'), function ($query) use ($request) {
                    $query->where('student_id', $request->student_id);
                })
                ->select('student_id')
                ->distinct()
                ->get()
                ->map(function ($form) use ($request) {
                    $student = $form->student;
                    $course = $form->course;

                    // Filter transcripts by category if the category is provided
                    $transcripts = $student->transcript->filter(function ($transcript) use ($request) {
                        return !$request->filled('category') || $transcript->transcript_category == $request->category;
                    })->map(function ($transcript) {
                        return [
                            'subject_name' => $transcript->subject->subject_name,
                            'grade' => $transcript->grade->core_metaName ?? '',
                            'category' => $transcript->category->core_metaName ?? '',
                            'grade_id' => $transcript->transcript_grade, // Store grade id for sorting
                        ];
                    });

                    // Sort transcripts by grade_id in ascending order
                    $sortedTranscripts = $transcripts->sortBy('grade_id')->values(); // Ensure it's indexed numerically

                    // Count the grades and sort them by the grade's stp_core_meta id
                    $gradeCounts = $sortedTranscripts->groupBy('grade_id')->map(function ($group, $gradeId) {
                        $gradeName = $group->first()['grade'];
                        return count($group) . $gradeName;
                    });

                    // Sort the grade counts by grade_id (which corresponds to stp_core_meta id)
                    $sortedGradeCounts = $gradeCounts->sortKeys()->all();

                    return [
                        'transcripts' => $sortedTranscripts->toArray(), // Convert collection to array
                        'count_grade' => $sortedGradeCounts, // Sorted by grade_id (stp_core_meta id)
                        'school_id' => $course->school_id ?? '',
                        'student_id' => $student->id ?? '',
                    ];
                });

            // Paginate the results
            $paginatedResults = new \Illuminate\Pagination\LengthAwarePaginator(
                $uniqueStudents->forPage($request->page ?? 1, 10),
                $uniqueStudents->count(),
                10,
                $request->page ?? 1,
                ['path' => url()->current()]
            );

            return response()->json([
                'success' => true,
                'data' => $paginatedResults
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }


    public function editApplicantStatus(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255',
                'feedback' => 'string|max:255'
            ]);
            $authUser = Auth::user();


            if ($request->type == 'Rejected') {
                $status = 3;
                $message = "Successfully Rejected the Applicant";
            } else {
                $status = 4;
                $message = "Successfully Accepted the Applicant";
            }


            $applicant = stp_submited_form::find($request->id);

            $applicant->update([
                'form_status' => $status,
                'form_feedback' => $request->feedback,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);

            $this->serviceFunctionController->sendStudentApplicantStatusEmail($applicant, $status, $request->feedback ?? "no comment");

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
        } catch (\Exception $e) {
            return response()->json([
                'succcess' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetDummySchoolPassword(Request $request)
    {

        try {
            $request->validate([
                'id' => 'required|integer',
                'newPassword' => 'required|string|min:8',
                'confirmPassword' => 'required|string|min:8'
            ]);

            $findSchool = stp_school::find($request->id);
            if ($findSchool->school_status != 3) {
                throw ValidationException::withMessages([
                    'account' => "Account is active already"
                ]);
            };

            $findSchool->update([
                'school_password' => Hash::make($request->newPassword),
                'school_status' => 1
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "successfully activate your account"]
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
                'message' => "Internal Server Error"
            ]);
        }
    }

    public function updateSchoolLogo(Request $request)
    {
        try {
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg|max:10000'
            ]);
            $authUser = Auth::user();
            if (!empty($authUser->school_logo)) {
                Storage::delete('public/' . $authUser->school_logo);
            }

            $image = $request->file('logo');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('schoolLogo', $imageName, 'public');

            $authUser->update([
                'school_logo' => $imagePath,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $imagePath
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateSchoolCover(Request $request)
    {
        try {
            $request->validate([
                'coverName' => 'required|string|max:255',
                'cover' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000'
            ]);
            $authUser = Auth::user();
            $getBanner = stp_school_media::where('school_id', $authUser->id)
                ->where('schoolMedia_type', 66)
                ->first();

            if (empty($getBanner)) {
                $image = $request->file('cover');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolCover', $imageName, 'public');
                $newData = [
                    'schoolMedia_name' => $request->coverName,
                    'school_id' => $authUser->id,
                    'schoolMedia_location' => $imagePath,
                    'schoolMedia_type' => 66,
                    'created_by' => $authUser->id
                ];
                $createNewCover = stp_school_media::create($newData);
                return response()->json([
                    'success' => true,
                    'data' => ['message' => 'successfully upload school cover']
                ]);
            } else {
                if (!empty($getBanner->schoolMedia_location)) {
                    Storage::delete('public/' . $getBanner->schoolMedia_location);
                }

                $image = $request->file('cover');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolCover', $imageName, 'public');

                $getBanner->update([
                    'schoolMedia_name' => $request->coverName,
                    'schoolMedia_location' => $imagePath,
                    'schoolMedia_status' => 1,
                    'updated_by' => $authUser->id
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $imagePath
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSchoolCover()
    {
        try {
            $authUser = AUth::user();
            $getCover = stp_school_media::where('school_id', $authUser->id)
                ->where('schoolMedia_type', 66)
                ->where('schoolMedia_status', 1)
                ->first();
            return response()->json([
                'success' => true,
                'data' => $getCover ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function disableSchoolCover()
    {
        try {
            $authUser = Auth::user();
            $getCover = stp_school_media::where('school_id', $authUser->id)
                ->where('schoolMedia_type', 66)
                ->first();
            $getCover->update([
                'schoolMedia_status' => 0
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => "Successfully Disable Cover Photo"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function uploadSchoolPhoto(Request $request)
    {
        try {
            $request->validate([
                'photo_Name' => 'required|string|max:255',
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000'
            ]);
            $authUser = Auth::user();
            $image = $request->file('photo');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('schoolPhoto', $imageName, 'public');

            $newData = [
                'schoolMedia_name' => $request->photo_Name,
                'schoolMedia_location' => $imagePath,
                'schoolMedia_type' => 67,
                'school_id' => $authUser->id
            ];
            stp_school_media::create($newData);
            return response()->json([
                'success' => true,
                'data' => [
                    "message" => "Successfully upload school photo"
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

    public function getSchoolPhoto(Request $request)
    {
        try {
            $authUser = Auth::user();
            $getSchoolPhoto = stp_school_media::where('school_id', $authUser->id)
                ->where('schoolMedia_type', 67)
                ->get();
            return response()->json([
                'success' => true,
                'data' => $getSchoolPhoto
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function removeSchoolPhoto(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $findPhoto = stp_school_media::find($request->id);
            if ($findPhoto && $findPhoto->schoolMedia_type == 67) {
                // Delete the photo
                $findPhoto->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Photo deleted successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo not found',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editPersonInCharge(Request $request)
    {
        try {
            $request->validate([
                'person_name' => 'required|string|max:255',
                'person_contact' => 'required|string|max:255',
                'person_email' => 'required|string|max:255'
            ]);
            $authUser = Auth::user();
            $school = stp_school::find($authUser->id);
            $updateData = [
                'person_inChargeName' => $request->person_name,
                'person_inChargeNumber' => $request->person_contact,
                'person_inChargeEmail' => $request->person_email
            ];
            $school->update($updateData);
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Update Successfully']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function filterCourseList(Request $request)
    {
        try {
            $authUser = Auth::user();
            $getCourse = stp_course::where('school_id', $authUser->id)
                ->where('course_status', 1)
                ->get();
            return response()->json([
                'success' => true,
                'data' => $getCourse
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function schoolApplicantList(Request $request)
    {
        try {
            $authUser = Auth::user();
            $Applicant = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->with('student', 'course') // Eager load related student and course data
                ->where('form_status', '!=', 0)
                ->get();



            $total = count($Applicant);
            $pending = 0;
            $reject = 0;
            $accept = 0;
            foreach ($Applicant as $application) {
                switch ($application->form_status) {
                    case 2:
                        $pending += 1;
                        break;
                    case 3:
                        $reject += 1;
                        break;
                    case 4:
                        $accept += 1;
                        break;
                }
            }

            $data = [
                "total" => $total,
                "pending" => $pending,
                "reject" => $reject,
                "accept" => $accept
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function countryStatistic(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();



            $countryCounts = [];

            // Count occurrences of each country
            foreach ($applicants as $applicant) {
                $countryName = $applicant->student->detail->country->country_name;
                if (isset($countryCounts[$countryName])) {
                    $countryCounts[$countryName]++;
                } else {
                    $countryCounts[$countryName] = 1;
                }
            }

            // Prepare the result in the desired format
            $result = [];
            foreach ($countryCounts as $countryName => $count) {
                $result[] = [$countryName, $count];
            }
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

    public function countryStatisticBarGraph(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();




            $countryCounts = [];

            foreach ($applicants as $applicant) {
                $countryName = $applicant->student->detail->country->country_name;

                // Initialize country counts if not already set
                if (!isset($countryCounts[$countryName])) {
                    $countryCounts[$countryName] = [
                        'disable' => 0,
                        'active' => 0,
                        'pending' => 0,
                        'reject' => 0,
                        'accept' => 0,
                    ];
                }



                // Increment based on form_status
                switch ($applicant->form_status) {
                    case 0:
                        $countryCounts[$countryName]['disable']++;
                        break;
                    case 1:
                        $countryCounts[$countryName]['active']++;
                        break;
                    case 2:
                        $countryCounts[$countryName]['pending']++;
                        break;
                    case 3:
                        $countryCounts[$countryName]['reject']++;
                        break;
                    case 4:
                        $countryCounts[$countryName]['accept']++;
                        break;
                }
            }

            // return  $countryCounts;

            // $result = [["Country", "Pending", "Accept", "Reject"]];
            $result = [];

            foreach ($countryCounts as $countryName => $counts) {
                $result[] = [
                    $countryName,
                    // 'disable' => $counts['disable'],
                    // 'active' => $counts['active'],
                    $counts['pending'],
                    $counts['reject'],
                    $counts['accept'],
                ];
            }



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

    public function programStatisticPieChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();

            $courseCounts = [];
            // return $applicants[0]->course->category->category_name;

            // Count occurrences of each country
            foreach ($applicants as $applicant) {
                $courseCat = $applicant->course->category->category_name;
                if (isset($courseCounts[$courseCat])) {
                    $courseCounts[$courseCat]++;
                } else {
                    $courseCounts[$courseCat] = 1;
                }
            }

            // Prepare the result in the desired format
            $result = [];
            foreach ($courseCounts as $courseCat => $count) {
                $result[] = [$courseCat, $count];
            }
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

            return $applicants;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function programStatisticBarChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();



            $courseCounts = [];

            foreach ($applicants as $applicant) {
                $courseCat = $applicant->course->category->category_name;

                // Initialize country counts if not already set
                if (!isset($courseCounts[$courseCat])) {
                    $courseCounts[$courseCat] = [
                        'disable' => 0,
                        'active' => 0,
                        'pending' => 0,
                        'reject' => 0,
                        'accept' => 0,
                    ];
                }

                // Increment based on form_status
                switch ($applicant->form_status) {
                    case 0:
                        $courseCounts[$courseCat]['disable']++;
                        break;
                    case 1:
                        $courseCounts[$courseCat]['active']++;
                        break;
                    case 2:
                        $courseCounts[$courseCat]['pending']++;
                        break;
                    case 3:
                        $courseCounts[$courseCat]['reject']++;
                        break;
                    case 4:
                        $courseCounts[$courseCat]['accept']++;
                        break;
                }
            }

            // $result = [["Country", "Pending", "Accept", "Reject"]];
            $result = [];

            foreach ($courseCounts as $courseCat => $counts) {
                $result[] = [
                    $courseCat,
                    // 'disable' => $counts['disable'],
                    // 'active' => $counts['active'],
                    $counts['pending'],
                    $counts['reject'],
                    $counts['accept'],
                ];
            }

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

    public function genderStatisticPieChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();



            $genderCount = [];
            // return $applicants[0]->course->category->category_name;

            // Count occurrences of each country
            foreach ($applicants as $applicant) {
                $courseGender = $applicant->student->detail->studentGender->core_metaName;
                if (isset($genderCount[$courseGender])) {
                    $genderCount[$courseGender]++;
                } else {
                    $genderCount[$courseGender] = 1;
                }
            }

            // Prepare the result in the desired format
            $result = [];
            foreach ($genderCount as $courseGender => $count) {
                $result[] = [$courseGender, $count];
            }
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

            return $applicants;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function genderStatisticBarChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();



            $genderCount = [];

            foreach ($applicants as $applicant) {
                $courseGender = $applicant->student->detail->studentGender->core_metaName;

                // Initialize country counts if not already set
                if (!isset($genderCount[$courseGender])) {
                    $genderCount[$courseGender] = [
                        'disable' => 0,
                        'active' => 0,
                        'pending' => 0,
                        'reject' => 0,
                        'accept' => 0,
                    ];
                }

                // Increment based on form_status
                switch ($applicant->form_status) {
                    case 0:
                        $genderCount[$courseGender]['disable']++;
                        break;
                    case 1:
                        $genderCount[$courseGender]['active']++;
                        break;
                    case 2:
                        $genderCount[$courseGender]['pending']++;
                        break;
                    case 3:
                        $genderCount[$courseGender]['reject']++;
                        break;
                    case 4:
                        $genderCount[$courseGender]['accept']++;
                        break;
                }
            }

            // $result = [["Country", "Pending", "Accept", "Reject"]];
            $result = [];

            foreach ($genderCount as $courseGender => $counts) {
                $result[] = [
                    $courseGender,
                    // 'disable' => $counts['disable'],
                    // 'active' => $counts['active'],
                    $counts['pending'],
                    $counts['reject'],
                    $counts['accept'],
                ];
            }

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

    public function qualificationStatisticPieChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();





            $qualificationCount = [];
            // return $applicants[0]->course->category->category_name;

            // Count occurrences of each country
            foreach ($applicants as $applicant) {
                $qualification = $applicant->course->qualification->qualification_name;
                if (isset($qualificationCount[$qualification])) {
                    $qualificationCount[$qualification]++;
                } else {
                    $qualificationCount[$qualification] = 1;
                }
            }

            // Prepare the result in the desired format
            $result = [];
            foreach ($qualificationCount as $qualification => $count) {
                $result[] = [$qualification, $count];
            }
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

            return $applicants;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function qualificationStatisticBarChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $applicants = stp_submited_form::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();



            $qualificationCount = [];

            foreach ($applicants as $applicant) {
                $qualification = $applicant->course->qualification->qualification_name;

                // Initialize country counts if not already set
                if (!isset($qualificationCount[$qualification])) {
                    $qualificationCount[$qualification] = [
                        'disable' => 0,
                        'active' => 0,
                        'pending' => 0,
                        'reject' => 0,
                        'accept' => 0,
                    ];
                }

                // Increment based on form_status
                switch ($applicant->form_status) {
                    case 0:
                        $qualificationCount[$qualification]['disable']++;
                        break;
                    case 1:
                        $qualificationCount[$qualification]['active']++;
                        break;
                    case 2:
                        $qualificationCount[$qualification]['pending']++;
                        break;
                    case 3:
                        $qualificationCount[$qualification]['reject']++;
                        break;
                    case 4:
                        $qualificationCount[$qualification]['accept']++;
                        break;
                }
            }

            // $result = [["Country", "Pending", "Accept", "Reject"]];
            $result = [];

            foreach ($qualificationCount as $qualification => $counts) {
                $result[] = [
                    $qualification,
                    // 'disable' => $counts['disable'],
                    // 'active' => $counts['active'],
                    $counts['pending'],
                    $counts['reject'],
                    $counts['accept'],
                ];
            }

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

    public function interestedStatisticPieChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $getInterestedCourse = stp_courseInterest::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();
            $courseCategoryCount = [];
            foreach ($getInterestedCourse as $interestedCourse) {
                $courseCategory = $interestedCourse->course->category['category_name'];
                if (isset($courseCategoryCount[$courseCategory])) {
                    $courseCategoryCount[$courseCategory]++;
                } else {
                    $courseCategoryCount[$courseCategory] = 1;
                }
            }
            $result = [];
            foreach ($courseCategoryCount as $categoryName => $count) {
                $result[] = [$categoryName, $count];
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 'fail',
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function interestedStatisticBarChart(Request $request)
    {
        try {
            $authUser = Auth::user();
            $interestedCourseCategory = stp_courseInterest::whereHas('course', function ($query) use ($authUser) {
                $query->where('school_id', $authUser->id);
            })
                ->when($request->filled('filterDuration'), function ($query) use ($request) {
                    switch ($request->filterDuration) {
                        case "today":
                            $query->whereDate('created_at', Carbon::today());
                            break;
                        case "yesterday":
                            $query->whereDate('created_at', Carbon::yesterday());
                            break;
                        case "this_week":
                            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case "previous_week":
                            $query->whereBetween('created_at', [
                                Carbon::now()->subWeek()->startOfWeek(),
                                Carbon::now()->subWeek()->endOfWeek()
                            ]);
                            break;
                        case "this_month":
                            $query->whereMonth('created_at', Carbon::now()->month)
                                ->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_month":
                            $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
                            $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();
                            $query->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth]);
                            break;
                        case "current_year":
                            $query->whereYear('created_at', Carbon::now()->year);
                            break;
                        case "previous_year":
                            $query->whereYear('created_at', Carbon::now()->subYear()->year);
                            break;

                        default:
                            // Handle other cases or defaults
                            break;
                    }
                })
                ->get();
            $courseCategoryCount = [];
            foreach ($interestedCourseCategory as $courseCategory) {
                $courseCategory = $courseCategory->course->category['category_name'];
                if (isset($courseCategoryCount[$courseCategory])) {
                    $courseCategoryCount[$courseCategory]++;
                } else {
                    $courseCategoryCount[$courseCategory] = 1;
                }
            }
            $result = [];
            foreach ($courseCategoryCount as $categoryName => $count) {
                $result[] = [$categoryName, $count];
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => "false",
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function studentDetail(Request $request)
    {
        try {
            $request->validate([
                'studentId' => 'required|integer'
            ]);
            $studentDetail = stp_student::find($request->studentId);

            $detail = stp_student_detail::where('student_id', $request->studentId)->first();
            $studentDetail["first_name"] = $detail->student_detailFirstName;
            $studentDetail["last_name"] = $detail->student_detailLastName;
            $studentDetail["address"] = $detail->student_detailAddress;

            return response()->json([
                'success' => true,
                'data' => $studentDetail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function applicantDetail(Request $request)
    {
        try {
            $request->validate([
                'applicantId' => 'required|integer'
            ]);

            $getApplicantDetail = stp_submited_form::find($request->applicantId);
            $getApplicantDetail['course_name'] = $getApplicantDetail->course->course_name;
            return $getApplicantDetail;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }


    public function schoolApplicantCocurriculum(Request $request)
    {
        try {
            $request->validate([
                'studentId' => 'required|integer'
            ]);
            $getCocurriculum = stp_cocurriculum::where('student_id', $request->studentId)
                ->where('cocurriculums_status', 1)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $getCocurriculum
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolAchievementsList(Request $request)
    {
        try {
            $request->validate([
                'studentId' => 'required|integer',
                'paginate' => 'required|string'
            ]);

            if ($request->paginate === "true") {
                $achievementList = stp_achievement::query()
                    ->where('achievements_status', 1)
                    ->where('student_id', $request->studentId)
                    ->when($request->filled('search'), function ($query) use ($request) {
                        $query->where('achievement_name', 'like', '%' . $request->search . '%');
                    })
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
            } else {
                $achievementList = stp_achievement::query()
                    ->where('achievements_status', 1)
                    ->where('student_id', $request->studentId)
                    ->get();

                foreach ($achievementList as $achievement) {
                    $finalAchievementList[] = [
                        "id" => $achievement->id,
                        "achievement_name" => $achievement->achievement_name,
                        "awarded_by" => $achievement->awarded_by,
                        "title_obtained" => $achievement->title->core_metaName ?? '',
                        "date" => $achievement->date,
                        "achievement_media" => $achievement->achievement_media,
                        "status" => "Active"
                    ];
                };
            }

            return response()->json([
                'success' => true,
                'data' => $achievementList,

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolOtherFileCertList(Request $request)
    {
        try {

            $request->validate([
                'studentId' => 'required|integer'
            ]);

            $otherCertList = stp_other_certificate::query()
                ->where('certificate_status', 1)
                ->where('student_id', $request->studentId)
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


            return response()->json([
                "success" => true,
                "data" => $otherCertList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }


    public function schoolTranscriptCategoryList(Request $request)
    {
        try {
            $request->validate([

                'categoryId' => 'nullable|integer'
            ]);

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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolStudentTranscriptSubjectList(Request $request)
    {
        try {
            $request->validate([
                'studentId' => 'required|integer'
            ]);
            $getSPMTranscriptSubject = stp_transcript::where('student_id', $request->studentId)
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

            $getSPMTrialTranscriptSubject = stp_transcript::where('student_id', $request->studentId)
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

            $spmSubject['subject'] = $getSPMTranscriptSubject;
            $spmTrialSubject['subject'] = $getSPMTrialTranscriptSubject;

            $data = [
                'spm' => $spmSubject,
                'trial' => $spmTrialSubject
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolHigherTranscriptSubjectList(Request $request)
    {
        try {
            $request->validate([
                'studentId' => 'required|integer',
                'categoryId' => 'required|integer'
            ]);

            $getHigherTranscriptSubject = stp_higher_transcript::where('category_id', $request->categoryId)
                ->where('student_id', $request->studentId)
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

    public function schoolTranscriptDocumentList(Request $request)
    {
        try {
            $request->validate([
                'studentId' => 'required|integer',
                'categoryId' => 'integer|nullable'
            ]);

            $mediaList = stp_student_media::query()
                ->where('studentMedia_status', 1)
                ->where('student_id', $request->studentId)
                ->when($request->filled('categoryId'), function ($query) use ($request) {
                    // Filtering the subjects by the selected category
                    $query->where('studentMedia_type', $request->categoryId);
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
        } catch (\Exception  $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolTranscriptCgpa(Request $request)
    {
        try {

            $request->validate([
                'studentId' => 'required|integer',
                'transcriptCategory' => 'required|integer'
            ]);
            $list = stp_cgpa::where('student_id', $request->studentId)
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

    public function getNumberOfDocument(Request $request)
    {
        try {
            $request->validate([
                'studentId' => 'required|integer'
            ]);

            $getAcademicNumber = stp_student_media::where('student_id', $request->studentId)
                ->where('studentMedia_status', '!=', 0)
                ->get();

            $getOtherCertNumber = stp_other_certificate::where('student_id', $request->studentId)
                ->where('certificate_status', '!=', 0)
                ->get();

            $getAchievementNumber = stp_achievement::where('student_id', $request->studentId)
                ->where('achievements_status', '!=', 0)
                ->get();

            $result[] = [
                'academicCount' => count($getAcademicNumber),
                'OtherCertCount' => count($getOtherCertNumber),
                'achievementCount' => count($getAchievementNumber),
                'totalDocument' => (count($getAcademicNumber)) + (count($getOtherCertNumber)) + (count($getAchievementNumber))
            ];

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);



            return $getOtherCertNumber;

            return count($getAcademicNumber);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getLocation(Request $request)
    {

        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $googleMapsLink = "https://www.google.com/maps?q={$latitude},{$longitude}";

        // Return the Google Maps link as a JSON response
        return response()->json([
            'google_maps_link' => $googleMapsLink,
        ]);
    }

    public function requestCoursesFeatured(Request $request)
    {
        try {
            $request->validate([
                'request_name' => 'required|string|max:255',
                'featured_type' => 'integer|required',
                'quantity' => 'integer|required',
                'duration' => 'integer|required',
                'transaction_proof' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
            ]);
            $authUser = Auth::user();
            $image = $request->file('transaction_proof');
            $imageName = $imageName = 'transactionProof/' . time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('transactionProof', $imageName, 'public');
            $data = [
                'school_id' => $authUser->id,
                'request_name' => $request->request_name,
                'request_type' => 83,
                'featured_type' => $request->featured_type,
                'request_quantity' => $request->quantity,
                'request_featured_duration' => $request->duration,
                'request_transaction_prove' => $imageName,
                'request_status' => 2
            ];
            stp_featured_request::create($data);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Successfully Create request'
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

    public function requestFeaturedSchool(Request $request)
    {
        try {
            $request->validate([
                'request_name' => 'required|string|max:255',
                'featured_type' => 'integer|required',
                'start_date' => 'required|date',
                'duration' => 'required|integer',
                'transaction_proof' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
            ]);


            $authUser = Auth::user();
            $image = $request->file('transaction_proof');
            $imageName = $imageName = 'transactionProof/' . time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('transactionProof', $imageName, 'public');
            $data = [
                'school_id' => $authUser->id,
                'request_name' => $request->request_name,
                'request_type' => 84,
                'featured_type' => $request->featured_type,
                'request_quantity' => 1,
                'start_date' => $request->start_date,
                'request_featured_duration' => $request->duration,
                'request_transaction_prove' => $imageName,
                'request_status' => 2
            ];
            stp_featured_request::create($data);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Successfully Create request'
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

    public function applyFeaturedCourse(Request $request)
    {
        try {
            $request->validate([
                'course_id' => 'required|integer',
                'request_id' => 'required|integer',
                'startDatetime' => 'required|date'
            ]);

            $authUser = Auth::user();
            $requestFeatured = stp_featured_request::find($request->request_id);


            $totalQuantity = $requestFeatured['request_quantity'];
            $featuredData = stp_featured::where('request_id', $requestFeatured['id'])->get();
            $usedQuantity = count($featuredData);

            if ($totalQuantity > $usedQuantity) {
                stp_featured::create([
                    'course_id' => $request->course_id,
                    'featured_type' => $requestFeatured['featured_type'],
                    'featured_startTime' => $request->startDatetime,
                    'featured_endTime' => Carbon::parse($request->startDatetime)->addDays($requestFeatured['request_featured_duration']),
                    'request_id' =>  $requestFeatured['id']
                ]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => 'create successfully'
                    ]
                ]);
            } else {
                throw new \Exception('No slots available');
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }


    public function courseRequestFeaturedList(Request $request)
    {

        try {
            $request->validate([
                'search' => "nullable|string",
                'featured_type' => "nullable|integer",
                "status" => "nullable|integer"
            ]);
            $authUser = Auth::user();
            $featuredList = stp_featured_request::where('school_id', $authUser->id)
                ->where('request_type', 83)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('request_name', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('featured_type'), function ($query) use ($request) {
                    $query->where('featured_type', $request->featured_type);
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('request_status', $request->status);
                })
                ->get()
                ->map(function ($item) {
                    $usedFeatured = stp_featured::where('request_id', $item->id)->get()->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'course_name' => $item->courses['course_name'] ?? null,
                            'end_date' => $item['featured_endTime'] ?? null,
                            'day_left' => abs(Carbon::now()->startOfDay()->diffInDays(Carbon::parse($item['featured_endTime'])->startOfDay())),
                        ];
                    });

                    $numberUsed = count($usedFeatured);
                    $featuredType = [
                        'featured_id' => $item->featured['id'],
                        'featured_type' => $item->featured['core_metaName']
                    ];

                    return [
                        'id' => $item->id,
                        'name' => $item->request_name,
                        'featured_type' => $featuredType,
                        'duration' => $item->request_featured_duration,
                        'quantity_used' => $numberUsed,
                        'total_quantity' => $item->request_quantity,
                        'request_status' => $item->request_status,
                        'featured_courses' => $usedFeatured
                    ];
                });


            return response()->json([
                'success' => true,
                'data' => $featuredList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolRequestFeaturedList(Request $request)
    {
        try {
            $request->validate([
                'search' => "nullable|string",
                'featured_type' => "nullable|integer",
                "status" => "nullable|integer"
            ]);
            $authUser = Auth::user();
            $featuredList = stp_featured_request::where('school_id', $authUser->id)
                ->where('request_type', 84)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('request_name', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('featured_type'), function ($query) use ($request) {
                    $query->where('featured_type', $request->featured_type);
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('request_status', $request->status);
                })
                ->get()
                ->map(function ($item) {
                    $usedFeatured = stp_featured::where('request_id', $item->id)->get()->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'course_name' => $item->courses['course_name'] ?? null,
                            'end_date' => $item['featured_endTime'] ?? null,
                            'day_left' => abs(Carbon::now()->startOfDay()->diffInDays(Carbon::parse($item['featured_endTime'])->startOfDay())),
                        ];
                    });

                    $numberUsed = count($usedFeatured);
                    $featuredType = [
                        'featured_id' => $item->featured['id'],
                        'featured_type' => $item->featured['core_metaName']
                    ];

                    return [
                        'id' => $item->id,
                        'name' => $item->request_name,
                        'featured_type' => $featuredType,
                        'duration' => $item->request_featured_duration,
                        'quantity_used' => $numberUsed,
                        'total_quantity' => $item->request_quantity,
                        'request_status' => $item->request_status,
                        'featured_courses' => $usedFeatured
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $featuredList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage('request_type', 83)

            ]);
        }
    }

    public function testFeaturedRequest(Request $request)
    {
        $data = stp_featured_request::find(1);
        return $data->featuredCourse;
        return 'test';
    }

    public function featuredCourseAvailable(Request $request)
    {
        try {
            $request->validate([
                'request_id' => 'required|integer'
            ]);
            $authUser = Auth::user();
            $requestId = stp_featured_request::find($request->request_id);
            $coursesRequest = $requestId->featuredCourse
                ->pluck('course_id') // Extract the course_id values
                ->unique()           // Remove duplicate values
                ->values()           // Re-index the array (optional)
                ->toArray();
            $courseAvailable = stp_course::where('school_id', $authUser->id)
                ->where('course_status', 1)  // Add this line to only get active courses
                ->whereNotIn('id', $coursesRequest)
                ->get()
                ->map(function ($query) {
                    return [
                        'id' => $query->id,
                        'course_name' => $query->course_name,
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $courseAvailable
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function editFeaturedCourseSetting(Request $request)
    {
        try {
            $request->validate([
                'featured_id' => 'required|integer',
                'newCourse_id' => 'required|integer',
                'startDate' => 'date'
            ]);

            $authUser = Auth::user();
            $validateCourse = stp_course::where('id', $request->newCourse_id)
                ->where('school_id', $authUser->id)
                ->first();
            if (empty($validateCourse)) {
                throw new \Exception('You do not register such course');
            }




            $findFeatured = stp_featured::find($request->featured_id);
            if (!$findFeatured) {
                throw new \Exception('Featured record not found.');
            }
            $updateData = [
                'course_id' => $request->newCourse_id
            ];

            if ($request->filled('startDate')) {
                if ($findFeatured['featured_startTime'] < now()) {
                    throw new \Exception('You cant change ongoing featured course date');
                }

                $updateData['featured_startTime'] = $request->startDate;
            }







            $updateFeaturedData = $findFeatured->update($updateData);
            if ($updateFeaturedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully Update'
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

    public function editSchoolFeaturedSetting(Request $request)
    {
        try {
            $request->validate([
                'featured_id' => 'required|integer',
                'startDate' => 'required|date'
            ]);


            $findFeatured = stp_featured::find($request->featured_id);
            if ($findFeatured == null) {
                throw new Exception('Featured Not Found');
            }


            if (now() > $findFeatured['featured_startTime']) {
                throw new Exception('You are not allow to change the date of ongoing featured');
            }

            $startDate = Carbon::parse($request->startDate);

            $updateFeaturedDate = $findFeatured->update([
                'featured_startTime' => $request->startDate,
                'featured_endTime' => $startDate->copy()->addDays(10)
            ]);

            if ($updateFeaturedDate) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => 'Successfully update'
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



    public function schoolFeaturedType(Request $request)
    {
        try {
            $featuredTypeList = stp_core_meta::where('core_metaType', 'featured_type')->get()->map(function ($query) {
                return [
                    'id' => $query['id'],
                    'featured_name' => $query['core_metaName']
                ];
            });
            return response()->json([
                'success' => true,
                'data' => $featuredTypeList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function schoolFeaturedRequestLists(Request $request)
    {
        try {
            $request->validate([
                'search' => "nullable|string",
                'featured_type' => "nullable|integer",
                'status' => "nullable|integer",
                'request_type' => 'required|string'
            ]);
            $authUser = Auth::user();

            $requestType = $request->request_type == "school" ? 84 : 83;

            // Set items per page (can be dynamic)
            $perPage = 10;

            $featuredList = stp_featured_request::where('school_id', $authUser['id'])
                ->where('request_type', $requestType)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('request_name', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('featured_type'), function ($query) use ($request) {
                    $query->where('featured_type', $request->featured_type);
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('request_status', $request->status);
                })
                ->paginate($perPage);

            // Transform the paginated collection
            $featuredList->getCollection()->transform(function ($item) use ($requestType) {
                if ($requestType == 84) {
                    // School featured logic
                    $usedFeatured = stp_featured::where('request_id', $item->id)->get()->map(function ($item) {
                        // $featuredCourseStatus = $item['featured_endTime'] < now() ? "Expired" : "Ongoing";

                        if ($item['featured_startTime'] > now() && $item['featured_endTime'] > now()) {
                            $featuredSchoolStatus = "Schedule";
                        }

                        if ($item['featured_startTime'] < now() && $item['featured_endTime'] > now()) {
                            $featuredSchoolStatus = "Ongoing";
                        }

                        if ($item['featured_startTime'] < now() && $item['featured_endTime'] < now()) {
                            $featuredSchoolStatus = "Expired";
                        }


                        return [
                            'id' => $item->id,
                            'school_name' => $item->school['school_name'] ?? null,
                            'start_date' => $item['featured_startTime'] ?? null,
                            'end_date' => $item['featured_endTime'] ?? null,
                            'status' => $featuredSchoolStatus ?? null,
                            'day_left' => abs(Carbon::now()->startOfDay()->diffInDays(Carbon::parse($item['featured_endTime'])->startOfDay())),
                        ];
                    });

                    $numberUsed = count($usedFeatured);

                    $featuredType = [
                        'featured_id' => $item->featured['id'],
                        'featured_type' => $item->featured['core_metaName']
                    ];

                    return [
                        'id' => $item->id,
                        'name' => $item->request_name,
                        'featured_type' => $featuredType,
                        'duration' => $item->request_featured_duration,
                        'quantity_used' => $numberUsed,
                        'total_quantity' => $item->request_quantity,
                        'request_status' => $item->request_status,
                        'school_id' => $item->school['id'] ?? null,
                        'school_name' => $item->school['school_name'] ?? null,
                        'featured' => $usedFeatured
                    ];
                } else {
                    // Course featured logic
                    $usedFeatured = stp_featured::where('request_id', $item->id)->get()->map(function ($item) {
                        // $featuredCourseStatus = $item['featured_endTime'] < now() ? "Expired" : "Ongoing";
                        if ($item['featured_startTime'] > now() && $item['featured_endTime'] > now()) {
                            $featuredCourseStatus = "Schedule";
                        }

                        if ($item['featured_startTime'] < now() && $item['featured_endTime'] > now()) {
                            $featuredCourseStatus = "Ongoing";
                        }

                        if ($item['featured_startTime'] < now() && $item['featured_endTime'] < now()) {
                            $featuredCourseStatus = "Expired";
                        }

                        return [
                            'id' => $item->id,
                            'course_id' => $item->courses['id'] ?? null,
                            'course_name' => $item->courses['course_name'] ?? null,
                            'start_date' => $item['featured_startTime'],
                            'end_date' => $item['featured_endTime'] ?? null,
                            'status' => $featuredCourseStatus,
                            'day_left' => abs(Carbon::now()->startOfDay()->diffInDays(Carbon::parse($item['featured_endTime'])->startOfDay())),
                        ];
                    });

                    $numberUsed = count($usedFeatured);

                    $featuredType = [
                        'featured_id' => $item->featured['id'],
                        'featured_type' => $item->featured['core_metaName']
                    ];

                    $requestId = stp_featured_request::find($item->id);
                    $coursesRequest = $requestId->featuredCourse
                        ->pluck('course_id')
                        ->unique()
                        ->values()
                        ->toArray();

                    $courseAvailable = stp_course::where('school_id', $item->school['id'])
                        ->whereNotIn('id', $coursesRequest)
                        ->get()
                        ->map(function ($query) {
                            return [
                                'id' => $query->id,
                                'course_name' => $query->course_name,
                            ];
                        });

                    return [
                        'id' => $item->id,
                        'name' => $item->request_name,
                        'featured_type' => $featuredType,
                        'duration' => $item->request_featured_duration,
                        'quantity_used' => $numberUsed,
                        'total_quantity' => $item->request_quantity,
                        'request_status' => $item->request_status,
                        'featured' => $usedFeatured,
                        'courseAvailable' => $courseAvailable
                    ];
                }
            });

            // Return paginated response
            return response()->json([
                'success' => true,
                'data' => $featuredList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }


    public function schoolFeaturedPriceList(Request $request)
    {
        try {
            $request->validate([
                'featured_type' => 'required|string'
            ]);

            // return $request->featured_type;
            if ($request->featured_type !== "course" && $request->featured_type !== "school") {
                throw new Exception('Only Accept course and school');
            }


            $getPrice = stp_featured_price::where('featured_type', $request->featured_type)
                ->where('stp_featured_price_status', 1)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'featured_id' => $item->featured_name['id'],
                        'featured_name' => $item->featured_name['core_metaName'],
                        'price' => $item->featured_price
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $getPrice
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
