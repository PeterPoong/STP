<?php

namespace App\Http\Controllers;

use App\Models\stp_city;
use Illuminate\Http\Request;
use App\Models\stp_student;
use App\Models\stp_country;
use App\Models\stp_course;
use App\Models\stp_course_tag;
use App\Models\stp_courses_category;
use App\Models\stp_submited_form;
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
use Validator;

class SchoolController extends Controller
{
    public function coursesList(Request $request)
    {
        try {
            $courseList = stp_course::query()
                ->where('course_status',1)
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->where('category_id', $request->category);
                })
                ->when($request->filled('qualification'), function ($query) use ($request) {
                    $query->orWhere('qualification_id', $request->qualification);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('course_name', 'like', '%' . $request->search . '%');
                })
                ->paginate(10)
                ->through(function ($courses) {
                    $status = ($courses->course_status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $courses->course_name,
                        "school" => $courses->school->school_name,
                        "category" => $courses->category->category_name,
                        "qualification" => $courses->qualification->qualification_name ?? null,
                        "status" => "Active"
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
                'description' => 'string|max:255',
                'requirement' => 'string|max:255',
                'cost' => 'required|regex:/^\d+(\.\d{1,2})?$/',
                'period' => 'required|string|max:255',
                'intake' => 'required|string|max:255',
                'category' => 'required|integer',
                'mode' => 'required|integer',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Image validation
            ]);

            $authUser = Auth::user();
            $checkingCourse = stp_course::where('school_id', $request->schoolID)
                ->where('course_name', $request->name)
                ->exists();

            if ($checkingCourse) {
                throw ValidationException::withMessages([
                    "courses" => ['Courses already exist in the school']
                ]);
            }

            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            stp_course::create([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'course_intake' => $request->intake,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'study_mode' => $request->mode,
                'course_logo' => $imagePath ?? null,
                'course_status' => 1,
                'created_by' => $authUser->id,
                'created_at' => now(),
            ]);

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

    public function editCourse(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'schoolID' => 'required|integer',
                'name' => 'required|string|max:255',
                'description' => 'string|max:255',
                'requirement' => 'string|max:255',
                'cost' => 'required|regex:/^\d+(\.\d{1,2})?$/',
                'period' => 'required|string|max:255',
                'intake' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Image validation
            ]);

            $checkingCourse = stp_course::where('school_id', $request->schoolID)
                ->where('course_name', $request->name)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingCourse) {
                throw ValidationException::withMessages([
                    "courses" => ['Courses already exist in the school']
                ]);
            }

            $courses = stp_course::find($request->id);

            if ($request->hasFile('logo')) {
                if (!empty($courses->course_logo)) {
                    Storage::delete('public/' . $courses->course_logo);
                }
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $courses->update([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'course_intake' => $request->intake,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'study_mode' => $request->mode,
                'course_logo' => $imagePath ?? null,
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
        try{
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

    public function editSchoolDetail(Request $request){
        try{ 
            $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
            'email' => 'required|string|max:255',
            'countryCode' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'confirm_password' => 'required|string|min:8|same:password',
            'country' => 'required|integer',
            'state' => 'required|integer',
            'city' => 'required|integer',
            'category' => 'required|integer',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);
        $authUser = Auth::user();
        
        $checkingEmail = stp_school::where('id', '!=', $request->id)
        ->where('school_email', $request->email)
        ->exists();

            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['email had been used']
                ]);
            }
        
        $checkingUserContact = stp_school::where('school_countryCode', $request->country_code)
        ->where('school_contactNo', $request->contact_number)
        ->where('id', '!=', $request->id)
        ->exists();

            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }
           
        $school = stp_school::find($request->id);
        
            if ($request->hasFile('logo')) {
                if (!empty($school->school_logo)) {
                    Storage::delete('public/' . $school->school_logo);
                }
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }
        
            $updateSchool = $school->update([
                'school_name'=>$request->name,
                'school_email'=>$request->email,
                'school_countryCode'=>$request->countryCode,
                'school_password' => Hash::make($request->password),
                'school_fullDesc' => $request->fullDesc,
                'school_shortDesc' => $request->shortDesc,
                'school_address'=>$request->address,
                'country_id' => $request->country,
                'state_id' => $request->state,
                'city_id' => $request->city,
                'institue_category' => $request->category,
                'school_lg'=>$request->lg,
                'school_lat'=>$request->lat,
                'person_inChargeName'=>$request->PICName,
                'person_inChargeNumber'=>$request->PICNo,
                'person_inChargeEmail'=>$request->PICEmail,
                'account_type'=>$request->account,
                'school_officialWebsite'=>$request->website,
                'school_logo' => $imagePath ?? null,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);

            return response()->json([
                "success" => true,
                "data" => ['message' => 'Update Successfully']
            ]);

        }catch (ValidationException $e) {
            return response()->json([
                "success" => false,
                "message" => "Validation Error",
                "errors" => $e->errors()
            ], 422);
    }catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Internal Server Error",
            "error" => $e->getMessage()
        ]);

        }
    }

    public function applicantDetailList(Request $request)
{
    try {
        // Get the authenticated user
        $authUser = Auth::user();
        $schoolID = $authUser->id;

        // Log the school ID for debugging
        \Log::info('School ID: ' . $schoolID);
        $formStatus = $request->input('form_status');
        $category = $request->input('category_id');
        $courseApplied = $request->input('courses_id');
        $searchTerm = $request->input('search');
         // Ensure formStatus is an array
         if (!is_array($formStatus) && $formStatus !== null) {
            $formStatus = [$formStatus];
        }

        // Ensure category is an array
        if (!is_array($category) && $category !== null) {
            $category = [$category];
        }

         // Ensure category is an array
         if (!is_array($courseApplied) && $courseApplied !== null) {
            $courseApplied = [$courseApplied];
        }
        // Get the total count of applicants for the school form status pending
        $totalApplicantsCount = stp_submited_form::where('form_status', 2)
        ->whereHas('course', function($query) use ($schoolID) {
            $query->where('school_id', $schoolID);
        })
        ->count();
        $studentList = stp_submited_form::with([
            'student.detail', // Relationship to student detail
            'student.higherTranscript', // Relationship to higherTranscript
            'student.cgpa', // Relationship to cgpa
            'student.achievement', // Relationship to achievement
            'student.cocurriculum', // Relationship to cocurriculum
            'course' // Ensure the course relationship is loaded
        ])
          // Apply form_status filter if provided
          ->when($formStatus, function($query) use ($formStatus) {
            return $query->whereIn('form_status', $formStatus);
        })
        // Apply category_id filter if provided
        ->when($category, function($query) use ($category) {
            return $query->whereHas('student.higherTranscript', function($query) use ($category) {
                return $query->whereIn('category_id', $category);
            });
        })
        // Apply courses_id filter if provided
        ->when($courseApplied, function($query) use ($courseApplied) {
            return $query->whereIn('courses_id', $courseApplied);
        })
        // Apply search filter if provided
        ->when($request->filled('search'), function($query) use ($searchTerm) {
            return $query->whereHas('student.detail', function($query) use ($searchTerm) {
                $query->where(DB::raw("CONCAT(student_detailFirstName, ' ', student_detailLastName)"), 'like', '%' . $searchTerm . '%');
            });
        })
        // Apply school_id filter
        ->whereHas('course', function($query) use ($schoolID) {
            $query->where('school_id', $schoolID); // Filter by school_id in the course
        })

        
        ->paginate(10)
        ->through(function ($submittedForm) {
            $student = $submittedForm->student;
            $course = $submittedForm->course;
            $categoryName = $student->higherTranscript->pluck('category.core_metaName')->first() ?? 'Unknown';
            return [
                "courses_id" => $submittedForm->courses_id,
                "course_name" => $course->course_name,
                "form_status" => $submittedForm->form_status == 2 ? "Pending" : ($submittedForm->form_status == 3 ? "Rejected" : "Accepted"),
                "category" => $categoryName,
                "highTranscript_name" => $student->higherTranscript->pluck('highTranscript_name')->first() ?? 'N/A',
                "cgpa" => $student->cgpa->pluck('cgpa')->first() ?? 'N/A',
                "achievement_total" => $student->achievement->count(), // Count achievements
                "cocurriculum_total" => $student->cocurriculum->count(), // Count cocurriculums
                "student_name" => $student->detail->student_detailFirstName . ' ' . $student->detail->student_detailLastName,
                "country_code" => $student->student_countryCode ?? 'N/A',
                "contact_number" => $student->student_contactNo ?? 'N/A',
                "email" => $student->student_email ?? 'N/A',
                'school_id' => $course->school_id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $studentList,
            'total_applicants_count' => $totalApplicantsCount, // Return the total count (form_status = Pending only)
        ]);
    } catch (\Exception $e) {
        // Log the error message for debugging
        \Log::error('Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Internal Server Error',
            'errors' => $e->getMessage()
        ], 500);
    }
}
public function applicantDetailStudentInfo(Request $request){
    try {
        // Get the authenticated user
        $authUser = Auth::user();
        $schoolID = $authUser->id;
        $studentID = $request->input('student_id');
        $formStatus = $request->input('form_status');
        // Ensure formStatus is an array
        if (!is_array($formStatus) && $formStatus !== null) {
            $formStatus = [$formStatus];
        }
        // Ensure $studentID is an array
        if ($studentID && !is_array($studentID)) {
            $studentID = [$studentID];
        }

        $applicantDetailStudentInfo = stp_submited_form::with([
                'student.detail', // Relationship to student detail
                'student.achievement', // Relationship to achievement
                'student.cocurriculum', // Relationship to cocurriculum
                'course' // Ensure the course relationship is loaded
            ])
             // Apply form_status filter if provided
            ->when($formStatus, function($query) use ($formStatus) {
                return $query->whereIn('form_status', $formStatus);
            })
            ->when($studentID, function($query) use ($studentID) {
                return $query->whereIn('student_id', $studentID);
            })
            // Apply school_id filter
            ->whereHas('course', function($query) use ($schoolID) {
                $query->where('school_id', $schoolID); // Filter by school_id in the course
            })
            ->paginate(10)
            ->through(function ($submittedForm) {
                $student = $submittedForm->student;
                $course = $submittedForm->course;

                 // Handle achievements (multiple achievements)
                 $achievements = $student->achievement->map(function($achievement) {
                    return [
                        'achievement_name' => $achievement->achievement_name,
                        'awarded_by' => $achievement->awarded_by,
                        'award_date' => $achievement->date,
                        'title_obtained' => $achievement->title ? $achievement->title->core_metaName : 'N/A',
                    ];
                });

                // Handle cocurriculars (multiple cocurriculums)
                $cocurriculums = $student->cocurriculum->map(function($cocurriculum) {
                    return [
                        'club_name' => $cocurriculum->club_name,
                        'student_position' => $cocurriculum->student_position,
                        'join_year' => $cocurriculum->year,
                        'club_location' => $cocurriculum->location,
                    ];
                });

                return [
                    "course_name" => $course->course_name,
                    "form_status" => $submittedForm->form_status == 2 ? "Pending" : ($submittedForm->form_status == 3 ? "Rejected" : "Accepted"),
                    "achievements" => $achievements, // List of achievements
                    "cocurriculums" => $cocurriculums, // List of cocurriculums
                    "student_name" => $student->detail->student_detailFirstName . ' ' . $student->detail->student_detailLastName,
                    "student_address" => $student->detail->student_detailaddress ?? 'N/A',
                    "student_country" => $student->detail->country_id->country->country_name ?? 'N/A',
                    "student_state" => $student->detail->state_id->state->state_name ?? 'N/A',
                    "student_city" => $student->detail->city_id->city->city_name ?? 'N/A',
                    "country_code" => $student->student_countryCode ?? 'N/A',
                    "contact_number" => $student->student_contactNo ?? 'N/A',
                    "email" => $student->student_email ?? 'N/A',
                    "student_icNumber" => $student->student_icNumber ?? 'N/A',
                    'school_id' => $course->school_id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $applicantDetailStudentInfo
        ]);
    } catch (\Exception $e) {
        // Log the error message for debugging
        \Log::error('Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Internal Server Error',
            'errors' => $e->getMessage()
        ], 500);
    }
}

}
    