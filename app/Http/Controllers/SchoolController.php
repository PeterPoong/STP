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
        
            $checkingUserContact = stp_school::where('school_countryCode', $request->countryCode)
            ->where('school_contactNo', $request->contact)
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
    public function applicantDetailHeader(Request $request)
{
    try{
        // Get the authenticated user
        $authUser = Auth::user();
        $schoolID = $authUser->id;
        $request->validate([
            'form_status' => 'integer|nullable',
                'student_id' => 'integer|nullable'
        ]);

        $studentInfo = stp_submited_form::with('student', 'course')
        ->whereHas('course', function ($query) use ($schoolID) {
            $query->where('school_id', $schoolID);
        })
        ->when($request->filled('student_id'), function ($query) use ($request) {
            $query->where('student_id', $request->student_id);
        })
        ->when($request->filled('form_status'), function ($query) use ($request) {
            $query->where('form_status', $request->form_status);
        })
        ->paginate(10);
        $formattedStudentInfo = $studentInfo->map(function ($submittedForm) {
            $student = $submittedForm->student;
            $course = $submittedForm->course;
            return [
                "courses_id" => $course->id ?? 'N/A',
                "course_name" => $course->course_name ?? 'N/A',
                "form_status" => $submittedForm->form_status == 2 ? "Pending" : ($submittedForm->form_status == 3 ? "Rejected" : "Accepted"),
                "student_name" => $student->detail->student_detailFirstName . ' ' . $student->detail->student_detailLastName,
                "country_code" => $student->student_countryCode ?? 'N/A',
                "contact_number" => $student->student_contactNo ?? 'N/A',
                'school_id' => $course->school_id ?? 'N/A',
                'student_id' => $student->id ?? 'N/A', // Add student_id to the result
            ];
        });
        return response()->json([
            'success' => true,
            'data' => $studentInfo
        ]);
    }catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Internal Sever Error',
            'error' => $e
        ]);
    }
}

public function applicantDetailInfo(Request $request)   //Header and basic information for the applicant
{
    try{
        // Get the authenticated user
        $authUser = Auth::user();
        $schoolID = $authUser->id;
        $request->validate([
            'form_status' => 'integer|nullable',
                'student_id' => 'integer|nullable'
        ]);

        $studentInfo = stp_submited_form::with('student', 'course')
        ->whereHas('course', function ($query) use ($schoolID) {
            $query->where('school_id', $schoolID);
        })
        ->when($request->filled('student_id'), function ($query) use ($request) {
            $query->where('student_id', $request->student_id);
        })
        ->when($request->filled('form_status'), function ($query) use ($request) {
            $query->where('form_status', $request->form_status);
        })
        ->paginate(10);
        $formattedStudentInfo = $studentInfo->map(function ($submittedForm) {
            $student = $submittedForm->student;
            $course = $submittedForm->course;
            return [
                "courses_id" => $course->id ?? 'N/A',
                "course_name" => $course->course_name ?? 'N/A',
                "form_status" => $submittedForm->form_status == 2 ? "Pending" : ($submittedForm->form_status == 3 ? "Rejected" : "Accepted"),
                "student_name" => $student->detail->student_detailFirstName . ' ' . $student->detail->student_detailLastName,
                "country_code" => $student->student_countryCode ?? 'N/A',
                "contact_number" => $student->student_contactNo ?? 'N/A',
                'school_id' => $course->school_id ?? 'N/A',
                'student_id' => $student->id ?? 'N/A', // Add student_id to the result
            ];
        });
        return response()->json([
            'success' => true,
            'data' => $studentInfo
        ]);
    }catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Internal Sever Error',
            'error' => $e
        ]);
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
        $uniqueStudents = stp_submited_form::with(['student.achievement', 'course'])
            ->whereHas('course', function ($query) use ($schoolID) {
                $query->where('school_id', $schoolID);
            })
            ->whereHas('student.achievement', function ($query) {
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

                $achievements = $student->achievement->map(function ($achievement) {
                    return [
                        'achievement_name' => $achievement->achivement_name,
                        'location' =>$achievement->awarded_by,
                        'position' => $achievement->title->core_metaName ?? '',
                        'date' => $achievement->date,
                    ];
                });

                return [
                    'cocurriculums' => $achievements,
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
   
    public function applicantDetailRelatedDocument(Request $request)
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();
            $schoolID = $authUser->id;
    
            // Validate request inputs
            $request->validate([
                'search' => 'string|nullable',
                'category' => 'integer|nullable',
                'form_status' => 'integer|nullable',
                'student_id' => 'integer|nullable',
                'date' => 'date|nullable' // timestamp
            ]);
    
            // Fetch basic information
            $studentInfoQuery = stp_submited_form::with('student', 'course')
                ->whereHas('course', function ($query) use ($schoolID) {
                    $query->where('school_id', $schoolID);
                })
                ->when($request->filled('student_id'), function ($query) use ($request) {
                    $query->where('student_id', $request->student_id);
                })
                ->when($request->filled('form_status'), function ($query) use ($request) {
                    $query->where('form_status', $request->form_status);
                });
    
            // Execute the query for basic info
            $studentInfo = $studentInfoQuery->get();
    
            // Fetch media details
            $studentIds = $studentInfo->pluck('student_id')->toArray();
    
            $mediaListQuery = stp_student_media::whereIn('student_id', $studentIds)
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->where('studentMedia_type', $request->category);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('studentMedia_name', 'like', '%' . $request->search . '%');
                });
    
            $mediaList = $mediaListQuery->get();
    
            // Fetch achievements
            $achievementsQuery = stp_achievement::whereIn('student_id', $studentIds)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('achievement_name', 'like', '%' . $request->search . '%');
                });
    
            $achievements = $achievementsQuery->get();
    
            // Format the student info
            $formattedStudentInfo = $studentInfo->map(function ($submittedForm) {
                $student = $submittedForm->student;
                $course = $submittedForm->course;
    
                return [
                    "courses_id" => $course->id ?? 'N/A',
                    "course_name" => $course->course_name ?? 'N/A',
                    "form_status" => $submittedForm->form_status == 2 ? "Pending" : ($submittedForm->form_status == 3 ? "Rejected" : "Accepted"),
                    "student_name" => $student->detail->student_detailFirstName . ' ' . $student->detail->student_detailLastName,
                    "country_code" => $student->student_countryCode ?? 'N/A',
                    "contact_number" => $student->student_contactNo ?? 'N/A',
                    'school_id' => $course->school_id ?? 'N/A',
                    'student_id' => $student->id ?? 'N/A', // Add student_id to the result
                ];
            });
    
            // Format the media list
            $formattedMediaList = $mediaList->map(function ($media) {
                return [
                    'student_id' => $media->student_id ?? 'N/A',
                    'studentMedia_name' => $media->studentMedia_name,
                    'studentMedia_location' => $media->studentMedia_location,
                    'studentMedia_type_id' => $media->studentMedia_type, // Return the ID
                ];
            });
    
            // Format the achievements
            $formattedAchievements = $achievements->map(function ($achievement) {
                return [
                    'student_id' => $achievement->student_id ?? 'N/A',
                    'achievement_name' => $achievement->achievement_name,
                    'achievement_media' => $achievement->achievement_media,
                ];
            });
    
            // Combine student info and media list
            $combinedResults = $formattedStudentInfo->map(function ($info) use ($formattedMediaList, $formattedAchievements, $request) {
                // Filter media and achievements for this student
                $mediaDetails = $formattedMediaList->filter(function ($media) use ($info) {
                    return $media['student_id'] == $info['student_id'];
                });
    
                $achievementDetails = $formattedAchievements->filter(function ($achievement) use ($info) {
                    return $achievement['student_id'] == $info['student_id'];
                });
    
                // Calculate total count (media + achievements)
                $totalCount = $mediaDetails->count() + $achievementDetails->count();
    
                // Format the achievements into strings
                $achievementName = $achievementDetails->pluck('achievement_name')->implode(', ');
                $achievementMedia = $achievementDetails->pluck('achievement_media')->implode(', ');
    
                // Nullify achievements if category filter is applied
                if ($request->filled('category')) {
                    $achievementName = null;
                    $achievementMedia = null;
                }
    
                return array_merge($info, [
                    "media_total" => $totalCount, // Total count of both media and achievements
                    "studentMedia_details" => $mediaDetails->isNotEmpty() ? $mediaDetails : null,
                    "achievement_name" => $achievementName,
                    "achievement_media" => $achievementMedia,
                ]);
            });
    
            // Return response
            return response()->json([
                'success' => true,
                'data' => $combinedResults
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


    