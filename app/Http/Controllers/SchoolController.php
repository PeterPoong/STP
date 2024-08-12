<?php

namespace App\Http\Controllers;

use App\Models\stp_city;
use Illuminate\Http\Request;
use App\Models\stp_student;
use App\Models\stp_country;
use App\Models\stp_course;
use App\Models\stp_course_tag;
use App\Models\stp_courses_category;
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
use Validator;

class SchoolController extends Controller
{
    public function coursesList(Request $request)
    {
        try {
            $courseList = stp_course::query()
                ->where('course_status', 1)
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
                'school_name' => $request->name,
                'school_email' => $request->email,
                'school_countryCode' => $request->countryCode,
                'school_password' => Hash::make($request->password),
                'school_fullDesc' => $request->fullDesc,
                'school_shortDesc' => $request->shortDesc,
                'school_address' => $request->address,
                'country_id' => $request->country,
                'state_id' => $request->state,
                'city_id' => $request->city,
                'institue_category' => $request->category,
                'school_lg' => $request->lg,
                'school_lat' => $request->lat,
                'person_inChargeName' => $request->PICName,
                'person_inChargeNumber' => $request->PICNo,
                'person_inChargeEmail' => $request->PICEmail,
                'account_type' => $request->account,
                'school_officialWebsite' => $request->website,
                'school_logo' => $imagePath ?? null,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
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
                throw ValidationException::withMessages(["password does not match"]);
            }

            $authUser->update([
                'school_password' => Hash::make($request->newPassword),
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
