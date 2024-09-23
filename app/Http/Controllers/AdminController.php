<?php

namespace App\Http\Controllers;

use App\Models\stp_advertisement_banner;
use App\Models\stp_city;
use App\Models\stp_core_meta;
use App\Models\stp_intake;
use App\Models\stp_package;
use App\Models\stp_student_detail;
use App\Models\stp_user_detail;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\stp_student;

use App\Models\stp_country;
use App\Models\stp_course;
use App\Models\stp_course_tag;
use App\Models\stp_courses_category;
use App\Models\stp_cocurriculum;
use App\Models\stp_featured;
use App\Models\stp_school;
use App\Models\stp_school_media;
use App\Models\stp_submited_form;
use App\Models\stp_state;
use App\Models\stp_subject;
use App\Models\stp_tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image as Image;
use Illuminate\Support\Facades\Storage;

// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

use function PHPSTORM_META\type;

class AdminController extends Controller
{
    public function addStudent(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'string|max:255',
                'last_name' => 'string|max:255',
                'gender' => 'integer',
                'postcode' => 'string',
                'country_code' => 'required|string|max:255',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'country' => 'integer',
                'email' => 'required|string|email|max:255',
                'state' => 'integer',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'city' => 'integer',

            ]);
            $authUser = Auth::user();
            $checkingIc = stp_student::where('student_icNumber', $request->ic)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingIc) {
                throw ValidationException::withMessages([
                    'ic' => ['ic has been used'],
                ]);
            }

            $checkingUserContact = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }

            if ($request->hasFile('student_profilePic')) {
                $image = $request->file('student_profilePic');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('student_profilePic', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $checkingEmail = stp_student::where('student_email', $request->email)
                ->where('id', '!=', $request->id)
                ->exists();


            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['Email has been taken'],
                ]);
            }
            $student = stp_student::create([
                "student_userName" => $request->name,
                "student_password" => Hash::make($request->password),
                'student_icNumber' => $request->ic,
                'student_email' => $request->email,
                'student_countryCode' => $request->country_code,
                'student_contactNo' => $request->contact_number,
                'student_status' => 3,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);

            stp_student_detail::create([
                "student_id" => $student->id,
                "student_detailFirstName" => $request->first_name ?? "",
                "student_detailLastName" => $request->last_name ?? "",
                "student_detailAddress" => $request->address ?? "",
                "country_id" => $request->country ?? "",
                'gender' => $request->gender ?? "",
                "city_id" => $request->city ?? "",
                "state_id" => $request->state ?? "",
                "student_detailPostcode" => $request->postcode ?? "",
                'student_detailStatus' => 1,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the New Student']
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
                'message' => 'Internal Sever Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStudent() {}

    public function studentList(Request $request)
    {
        $user = $request->user();
        $studentList = stp_student::where('student_status', 1)->get();
        return response()->json([
            "success" => true,
            "data" => $studentList
        ]);
    }
    public function studentListAdmin(Request $request)

    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_student::count() : (int)$request->per_page)
                : 10;

            $studentList = stp_student::when($request->filled('search'), function ($query) use ($request) {
                $query->where('student_userName', 'like', '%' . $request->search . '%');
            })

                ->paginate($perPage)
                ->through(function ($student) {
                    switch ($student->student_status) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                        case 3:
                            $status = "Temporary";
                            break;
                        case 4:
                            $status = "Temporary-Disable";
                            break;
                        default:
                            $status = null;
                    }

                    return [
                        'id' => $student->id,
                        'name' => $student->student_userName,
                        'email' => $student->student_email,
                        'status' => $status
                    ];
                });
            return response()->json($studentList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function editStudent(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'firt_name' => 'string|max:255',
                'last_name' => 'string|max:255',
                'address' => 'string|max:255',
                'country' => 'integer',
                'city' => 'integer',
                'state' => 'integer',
                'gender' => 'integer',
                'postcode' => 'string',
                'ic' => 'string|min:6|',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048' // Image validationt
            ]);
            $authUser = Auth::user();

            //check ic
            $checkingIc = stp_student::where('student_icNumber', $request->ic)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingIc) {
                throw ValidationException::withMessages([
                    'ic' => ['ic has been used'],
                ]);
            }

            //checking contact number
            $checkingUserContact = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }


            $student = stp_student::find($request->id);
            $studentDetail = $student->detail;


            if ($request->hasFile('image')) {
                if (!empty($student->student_profilePic)) {
                    Storage::delete('public/' . $student->student_profilePic);
                }

                $image = $request->file('image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                // $resizedImage = Image::make($image)->fit(300, 300);

                $imagePath = $image->storeAs('studentProfilePic', $imageName, 'public'); // Store in 'storage/app/public/images'
                $student->student_profilePic = $imagePath; // Save the path to the database
            }

            $checkingEmail = stp_student::where('student_email', $request->email)
                ->where('id', '!=', $request->id)
                ->exists();


            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['Contact has been taken'],
                ]);
            }

            $updateingStudent = $student->update([
                "student_userName" => $request->name,
                "student_password" => Hash::make($request->password),
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

    public function editStudentStatus(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required'
            ]);

            $student = stp_student::find($request->id);
            if (!$student) {
                return response()->json([
                    "success" => false,
                    "message" => "School not found"
                ], 404);
            }

            switch ($request->type) {
                case 'disable':
                    if ($student->student_status == 3) {
                        $status = 4;
                        $message = "successfully disabled (status changed from 3 to 4)";
                    } else {
                        $status = 0;
                        $message = "successfully disabled (status set to 0)";
                    }
                    break;

                case 'enable':
                    if ($student->student_status == 4) {
                        $status = 3;
                        $message = "successfully enabled (status changed from 4 to 3)";
                    } else {
                        $status = 1;
                        $message = "successfully enabled";
                    }
                    break;

                default:
                    return response()->json([
                        "success" => false,
                        "message" => "Invalid type"
                    ], 400);
            }

            $student->update([
                "student_status" => $status,
                "updated_by" => $authUser->id
            ]);

            return response()->json([
                "success" => true,
                "data" => $message
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                "success" => false,
                "message" => "validation error",
                "error" => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal server error",
                'error' => $e
            ]);
        }
    }

    public function schoolList(Request $request)
    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_school::count() : (int)$request->per_page)
                : 10;

            $schoolList = stp_school::when($request->filled('country'), function ($query) use ($request) {
                $query->orWhere('country_id', $request->country);
            })
                ->when($request->filled('state'), function ($query) use ($request) {
                    $query->orWhere('state_id', $request->state);
                })
                ->when($request->filled('city'), function ($query) use ($request) {
                    $query->orWhere('city_id', $request->city);
                })
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->orWhere('institue_category', $request->category);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('school_name', 'like', '%' . $request->search . '%');
                })
                ->paginate($perPage)
                ->through(function ($school) {
                    switch ($school->school_status) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                        case 2:
                            $status = "Pending";
                            break;
                        case 3:
                            $status = "Temporary";
                            break;
                        case 4:
                            $status = "Temporary-Disable";
                            break;
                        default:
                            $status = null;
                    }
                    return [
                        'id' => $school->id,
                        'name' => $school->school_name,
                        'category' => $school->institueCategory->core_metaName ?? null,
                        'country' => $school->country->country_name ?? null,
                        'email' => $school->school_email,
                        'contact' => $school->school_countryCode . $school->school_contactNo,
                        'state' => $school->state->state_name ?? null,
                        'city' => $school->city->city_name ?? null,
                        'status' => $status
                    ];
                });

            return response()->json($schoolList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addSchool(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
                'school_fullDesc' => 'required|string|max:5000',
                'school_shortDesc' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Add cover photo validation
                'album.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Add album photo validation
                'featured' => 'nullable|array', // Validate as an array
                'featured.*' => 'integer', // Validate each element as an integer and existing in the features table
                'person_in_charge_name' => 'required|string|max:255',
                'person_in_charge_contact' => 'required|string|max:255',
                'person_in_charge_email' => 'required|email',
                'category' => 'required|integer',
                'account' => 'required|integer'
            ]);

            $authUser = Auth::user();

            // Check email
            $checkingEmail = stp_school::where('school_email', $request->email)->where('school_status', 1)->exists();
            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['Email has been used'],
                ]);
            }

            // Check contact number
            $checkingUser = stp_school::where('school_countryCode', $request->country_code)
                ->where('school_contactNo', $request->contact_number)
                ->where('school_status', 1)
                ->exists();
            if ($checkingUser) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact number has been used'],
                ]);
            }

            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolLogo', $imageName, 'public');
            }

            $school = stp_school::create([
                'school_name' => $request->name,
                'school_email' => $request->email,
                'school_countryCode' => $request->country_code,
                'school_contactNo' => $request->contact_number,
                'school_password' => Hash::make($request->password),
                'school_fullDesc' => $request->school_fullDesc,
                'country_id' => $request->country,
                'state_id' => $request->state,
                'city_id' => $request->city,
                'institue_category' => $request->category,
                'school_shortDesc' => $request->school_shortDesc,
                'school_address' => $request->school_address,
                'school_officialWebsite' => $request->school_website,
                'person_inChargeName' => $request->person_in_charge_name,
                'person_inChargeNumber' => $request->person_in_charge_contact,
                'person_inChargeEmail' => $request->person_in_charge_email,
                'school_logo' => $imagePath ?? null,
                'account' => $request->account_type,
                'school_status' => 3,
                'created_by' => $authUser->id
            ]);

            // Handle cover photo
            if ($request->hasFile('cover')) {
                // Check if a cover already exists for the school
                $existingCover = stp_school_media::where('school_id', $school->id)->where('schoolMedia_type', 66)->first();
                if ($existingCover) {
                    // Delete the old cover image from storage
                    Storage::delete('public/' . $existingCover->schoolMedia_location);
                    // Delete the old record from the database
                    $existingCover->delete();
                }

                // Upload the new cover
                $cover = $request->file('cover');
                $coverName = $school->school_name . '_cover.' . $cover->getClientOriginalExtension();
                $coverPath = $cover->storeAs('schoolMedia', $coverName, 'public');

                // Extract the file extension
                $coverFormat = $cover->getClientOriginalExtension();

                // Save cover photo in the stp_school_media table
                stp_school_media::create([
                    'school_id' => $school->id,
                    'schoolMedia_type' => 66, // Cover photo type
                    'schoolMedia_name' => $coverName,
                    'schoolMedia_location' => $coverPath,
                    'schoolMedia_format' => $coverFormat, // Save file extension
                    'schoolMedia_status' => 1,
                    'created_by' => $authUser->id,
                    'created_at' => now()
                ]);
            }

            // Handle photo album
            if ($request->hasFile('album')) {
                foreach ($request->file('album') as $albumPhoto) {
                    $albumPhotoName = $albumPhoto->getClientOriginalName();
                    $albumPhotoPath = $albumPhoto->storeAs('schoolMedia', $albumPhotoName, 'public');

                    // Extract the file extension
                    $albumPhotoFormat = $albumPhoto->getClientOriginalExtension();

                    // Save each photo in the stp_school_media table
                    stp_school_media::create([
                        'school_id' => $school->id,
                        'schoolMedia_type' => 67, // Album photo type
                        'schoolMedia_name' => $albumPhotoName,
                        'schoolMedia_location' => $albumPhotoPath,
                        'schoolMedia_format' => $albumPhotoFormat, // Save file extension
                        'schoolMedia_status' => 1,
                        'created_by' => $authUser->id,
                        'created_at' => now()
                    ]);
                }
            }
            // Insert each featured type into the stp_featureds table
            if ($request->has('featured')) {
                foreach ($request->featured as $featureId) {
                    stp_featured::create([
                        'school_id' => $school->id,
                        'featured_type' => $featureId,
                        'featured_status' => 1
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => ['message' => 'School registered successfully']
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
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

    public function editSchool(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);

            $authUser = Auth::user();
            //checking email 
            $checkingEmail = stp_school::where('id', '!=', $request->id)
                ->where('school_email', $request->email)
                ->exists();

            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['email had been used']
                ]);
            }

            //cheking contact 
            $checkingUserContact = stp_school::where('school_countryCode', $request->country_code)
                ->where('school_contactNo', $request->contact_number)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }

            //check logo if there is logo being upload will delete the old one 
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
                'school_countryCode' => $request->country_code,
                'school_contactNo' => $request->contact_number,
                'school_password' => Hash::make($request->password),
                'school_fullDesc' => $request->school_fullDesc,
                'country_id' => $request->country,
                'state_id' => $request->state,
                'city_id' => $request->city,
                'institue_category' => $request->category,
                'school_shortDesc' => $request->school_shortDesc,
                'school_address' => $request->school_address,
                'school_officalWebsite' => $request->school_website,
                'school_logo' => $imagePath ?? null,
                'updated_by' => $authUser->id
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

    public function editSchoolStatus(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                "id" => 'required|integer',
                "type" => 'required|string'
            ]);

            $school = stp_school::find($request->id);
            if (!$school) {
                return response()->json([
                    "success" => false,
                    "message" => "School not found"
                ], 404);
            }

            switch ($request->type) {
                case 'disable':
                    if ($school->school_status == 3) {
                        $status = 4;
                        $message = "successfully disabled (status changed from 3 to 4)";
                    } else {
                        $status = 0;
                        $message = "successfully disabled (status set to 0)";
                    }
                    break;

                case 'enable':
                    if ($school->school_status == 4) {
                        $status = 3;
                        $message = "successfully enabled (status changed from 4 to 3)";
                    } else
                        $status = 1;
                    $message = "successfully enabled ";
                    break;

                default:
                    return response()->json([
                        "success" => false,
                        "message" => "Invalid type"
                    ], 400);
            }

            $school->update([
                "school_status" => $status,
                "updated_by" => $authUser->id
            ]);

            return response()->json([
                "success" => true,
                "data" => $message
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function schoolDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);

            // Fetch the school along with its courses, course featured data, and school featured data
            $school = stp_school::with(['courses.featured', 'featured', 'media'])->find($request->id);

            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'School not found'
                ]);
            }

            $medias = $school->media->filter(function($media){
                return $media->schoolMedia_status===1;
            })->map(function($media){
                return [
                    'schoolMedia_name' => $media->schoolMedia_name,
                    'schoolMedia_location' => $media->schoolMedia_location,
                    'schoolMedia_type'=> $media->schoolMedia_type,
                    
                ];
            });

            // Prepare the courses data
            $courses = $school->courses->map(function ($course) {
                return [
                    'course_name' => $course->course_name,
                    'course_featured' => $course->featured->map(function ($featured) {
                        return [
                            'featured_type' => $featured->featured_type,
                            'featured_startTime' => $featured->featured_startTime,
                            'featured_endTime' => $featured->featured_endTime,
                        ];
                    }),
                ];
            });

            // Prepare the school featured data
            $schoolFeatured = $school->featured->map(function ($featured) {
                return [
                    'featured_type' => $featured->featured_type,
                    'featured_startTime' => $featured->featured_startTime,
                    'featured_endTime' => $featured->featured_endTime,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $school->id,
                    'name' => $school->school_name,
                    'email' => $school->school_email,
                    'country_code' => $school->school_countryCode,
                    'contact_number'=>$school->school_contactNo,
                    'fullDescripton' => $school->school_fullDesc,
                    'shortDescription' => $school->school_shortDesc,
                    'school_address' => $school->school_address,
                    'country' => $school->country->country_name ?? '',
                    'country_id'=>$school->country_id,
                    'state' => $school->state->state_name ?? '',
                    'state_id'=> $school->state_id,
                    'city' => $school->city->city_name ?? '',
                    'city_id'=>$school->city_id,
                    'logo' => $school->school_logo ?? '',
                    'school_website' => $school->school_officalWebsite ?? '',
                    'courses' => $courses, // List of courses with featured data
                    'schoolFeatured' => $schoolFeatured, // List of school's featured data
                    'category'=>$school->institue_category,
                    'PIC_name'=>$school->person_inChargeName,
                    'PIC_number'=>$school->person_inChargeNumber,
                    'PIC_email'=>$school->person_inChargeEmail,
                    'account'=>$school->account_type,
                    'media'=>$medias
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ]);
        }
    }


    public function editSchoolFeatured(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                '*.featureType' => 'required|integer'
            ]);

            $hp = stp_featured::where('featured_type', 28)->where('featured_status', 1)->get();
            $secondPage = stp_featured::where('featured_type', 30)->where('featured_status', 1)->get();
            $thirdPage = stp_featured::where('featured_type', 31)->where('featured_status', 1)->get();


            $hpArray = $hp->pluck('school_id')->toArray();
            if (empty($hpArray)) {
                $hpArray = [];
            }

            $secondArray = $secondPage->pluck('school_id')->toArray();
            if (empty($secondArray)) {
                $secondArray = [];
            }

            $thirdArray = $thirdPage->pluck('school_id')->toArray();
            if (empty($thirdArray)) {
                $thirdArray = [];
            }

            $addhp = array_diff($request[0]['schoolID'], $hpArray);
            $addSecond = array_diff($request[1]['schoolID'], $secondArray);
            $addThird = array_diff($request[2]['schoolID'], $thirdArray);



            $removehp = array_diff($hpArray, $request[0]['schoolID']);
            $removeSecond = array_diff($secondArray, $request[1]['schoolID']);
            $removeThird = array_diff($secondArray, $request[2]['schoolID']);



            $newFeaturedData = [];
            foreach ($addhp as $newData) {
                $checkhp = stp_featured::where('school_id', $newData)->where('featured_type', 28)->first();

                if (!empty($checkhp)) {
                    $checkhp->update(['featured_status' => 1]);
                } else {
                    $newFeaturedData[] = [
                        'school_id' => $newData,
                        'featured_type' => 28,
                        'created_by' => $authUser->id
                    ];
                }
            }

            $newSecondData = [];
            foreach ($addSecond as $secondData) {

                $checkSecond = stp_featured::where('school_id', $secondData)->where('featured_type', 30)->first();

                if (!empty($checkSecond) > 0) {
                    $checkSecond->update(['featured_status' => 1]);
                } else {
                    $newSecondData[] = [
                        'school_id' => $secondData,
                        'featured_type' => 30,
                        'created_by' => $authUser->id
                    ];
                }
            }

            $newThirdData = [];
            foreach ($addThird as $thirdData) {

                $checkThird = stp_featured::where('school_id', $thirdData)->where('featured_type', 31)->first();

                if (!empty($checkThird) > 0) {
                    $checkThird->update(['featured_status' => 1]);
                } else {
                    $newThirdData[] = [
                        'school_id' => $thirdData,
                        'featured_type' => 31,
                        'created_by' => $authUser->id
                    ];
                }
            }


            $addNewFeaturedSchool = stp_featured::insert($newFeaturedData);
            $addNewSecondFeaturedCourses = stp_featured::insert($newSecondData);
            $addNewThiedFeaturedCourses = stp_featured::insert($newThirdData);

            $removeFeaturedCourse = stp_featured::whereIn('school_id', $removehp)->where('featured_type', 28)->update(['featured_status' => 0]);
            $removeSecondFeaturedCourse = stp_featured::whereIn('school_id', $removeSecond)->where('featured_type', 30)->update(['featured_status' => 0]);
            $removeThirdFeaturedCourse = stp_featured::whereIn('school_id', $removeThird)->where('featured_type', 31)->update(['featured_status' => 0]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'successfully edit the school featured']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'errors' => $e->getMessage()
            ]);
        }
    }

    public function addCourse(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'schoolID' => 'required|integer',
                'description' => 'string|max:5000',
                'requirement' => 'string|max:255',
                'cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
                'period' => 'required|string|max:255',
                'intake' => 'required|array',
                'intake.*' => 'integer|between:41,52', // Validate each element in the intake array
                'category' => 'required|integer',
                'qualification' => 'required|integer',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
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
            if ($request->hasFile('course_logo')) {
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }
            $course = stp_course::create([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'study_mode'=> $request->mode,
                'logo' => $imagePath ?? '',
                'created_by' => $authUser->id,
                'course_status' => 1,
                'created_at' => now()
            ]);

            foreach ($request->intake as $intakeMonth) {
                stp_intake::create([
                    'course_id' => $course->id,
                    'intake_month' => $intakeMonth,
                    'created_by' => $authUser->id,
                    'intake_status' => 1,
                    'created_at' => now()
                ]);
            }
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the Courses']
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

    public function coursesList(Request $request)
    {
        try {
            $courseList = stp_course::when($request->filled('category'), function ($query) use ($request) {
                $query->where('category_id', $request->category);
            })
                ->when($request->filled('qualification'), function ($query) use ($request) {
                    $query->orWhere('qualification_id', $request->qualification);
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->orWhere('course_status', $request->status);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('course_name', 'like', $request->search . '%');
                })
                ->paginate(10)
                ->through(function ($courses) {
                    $status = ($courses->course_status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $courses->course_name,
                        "school" => $courses->school->school_name,
                        "category" => $courses->category->category_name,
                        "qualification" => $courses->qualification->qualification_name,
                        "status" => $status
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

    public function courseListAdmin(Request $request)

    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_course::count() : (int)$request->per_page)
                : 10;

            $courseList = stp_course::when($request->filled('search'), function ($query) use ($request) {
                $query->where('course_name', 'like', '%' . $request->search . '%');
            })

                ->paginate($perPage)
                ->through(function ($course) {
                    switch ($course->course_status) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                        default:
                            $status = null;
                    }

                    return [
                        'id' => $course->id,
                        'name' => $course->course_name,
                        'school' => $course->school->school_name,
                        "category" => $course->category->category_name,
                        "qualification" => $course->qualification->qualification_name,
                        "status" => $status
                    ];
                });
            return response()->json($courseList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function courseDetail(Request $request)
    {
        try {
            $request->validate([
                'courseID' => 'required|integer'
            ]);

            $courseList = stp_course::find($request->courseID);

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
                $intakeList[] = $intake->month->core_metaName;
            }
            $courseListDetail = [
                'id' => $courseList->id,
                'course' => $courseList->course_name,
                'description' => $courseList->course_description,
                'requirement' => $courseList->course_requirement,
                'cost' => $courseList->course_cost,
                'period' => $courseList->course_period,
                'intake' => $intakeList, // Updated to include all intakes
                'category' => $courseList->category->id,
                'school' => $courseList->school->school_name,
                'qualification' => $courseList->qualification->qualifiation_name,
                'mode' => $courseList->studyMode->id?? null,
                'logo' => $logo,
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

    public function editCourse(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'requirement' => 'required|string|max:255',
                'schoolID' => 'required|integer',
                'description' => 'string|max:255',
                'cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
                'period' => 'required|string|max:255',
                'intake' => 'required|array',
                'intake.*' => 'integer|between:41,52',
                'category' => 'required|integer',
                'qualification' => 'required|integer',
                'course_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            $checkingCourse = stp_course::where('school_id', $request->schoolID)
                ->where('course_name', $request->name)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingCourse) {
                throw ValidationException::withMessages([
                    "courses" => ['Course already exists in the school']
                ]);
            }

            $courses = stp_course::find($request->id);

            $imagePath = $courses->course_logo; // Default to current course logo
            if ($request->hasFile('course_logo')) {
                if (!empty($courses->course_logo)) {
                    Storage::delete('public/' . $courses->course_logo);
                }
                $image = $request->file('course_logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseLogo', $imageName, 'public');
            }

            $courses->update([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'course_logo' => $imagePath ?? '',
                'updated_by' => $authUser->id,
                'updated_at' => now()
            ]);

            $getIntake = stp_intake::where('course_id', $request->id)
                ->where('intake_status', 1)
                ->get();

            $existingMonth = $getIntake->pluck('intake_month')->toArray();

            // Identify new intakes and intakes to remove
            $new = array_diff($request->intake, $existingMonth);
            $remove = array_diff($existingMonth, $request->intake);

            // Handle existing new intakes (reactivate if necessary)
            $checkExistingNewIntake = stp_intake::where('course_id', $request->id)
                ->whereIn('intake_month', $new)
                ->get();

            if (count($checkExistingNewIntake) > 0) {
                foreach ($checkExistingNewIntake as $exist) {
                    $new = array_diff($new, [$exist['intake_month']]);
                    $exist->update([
                        'intake_status' => 1,
                        'updated_by' => $authUser->id
                    ]);
                }
            }

            // Insert new intakes
            $newIntakeData = [];
            foreach ($new as $newIntake) {
                $newIntakeData[] = [
                    'course_id' => $request->id,
                    'intake_month' => $newIntake,
                    'created_by' => $authUser->id,
                    'updated_at' => now()
                ];
            }

            stp_intake::insert($newIntakeData);

            // Deactivate intakes that are no longer associated with the course
            stp_intake::where('course_id', $request->id)
                ->whereIn('intake_month', $remove)
                ->update([
                    'intake_status' => 0,
                    'updated_by' => $authUser->id,
                    'updated_at' => now()
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

    public function editCoursesFeatured(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                '*.featureType' => 'required|integer'
            ]);

            $hp = stp_featured::where('featured_type', 29)->where('featured_status', 1)->get();
            $secondPage = stp_featured::where('featured_type', 30)->where('featured_status', 1)->get();
            $thirdPage = stp_featured::where('featured_type', 31)->where('featured_status', 1)->get();

            $hpArray = $hp->pluck('course_id')->toArray();
            if (empty($hpArray)) {
                $hpArray = [];
            }

            $secondArray = $secondPage->pluck('course_id')->toArray();
            if (empty($secondArray)) {
                $secondArray = [];
            }

            $thirdArray = $thirdPage->pluck('course_id')->toArray();
            if (empty($thirdArray)) {
                $thirdArray = [];
            }


            $addhp = array_diff($request[0]['courseID'], $hpArray);
            $addSecond = array_diff($request[1]['courseID'], $secondArray);
            $addThird = array_diff($request[2]['courseID'], $thirdArray);



            $removehp = array_diff($hpArray, $request[0]['courseID']);
            $removeSecond = array_diff($secondArray, $request[1]['courseID']);
            $removeThird = array_diff($secondArray, $request[2]['courseID']);



            $newFeaturedData = [];
            foreach ($addhp as $newData) {
                $checkhp = stp_featured::where('course_id', $newData)->where('featured_type', 29)->first();
                if (!empty($checkhp)) {
                    $checkhp->update(['featured_status' => 1]);
                } else {
                    $newFeaturedData[] = [
                        'course_id' => $newData,
                        'featured_type' => 29,
                        'created_by' => $authUser->id
                    ];
                }
            }

            $newSecondData = [];
            foreach ($addSecond as $secondData) {

                $checkSecond = stp_featured::where('course_id', $secondData)->where('featured_type', 30)->first();

                if (!empty($checkSecond) > 0) {
                    $checkSecond->update(['featured_status' => 1]);
                } else {
                    $newSecondData[] = [
                        'course_id' => $secondData,
                        'featured_type' => 30,
                        'created_by' => $authUser->id
                    ];
                }
            }

            $newThirdData = [];
            foreach ($addThird as $thirdData) {

                $checkThird = stp_featured::where('course_id', $thirdData)->where('featured_type', 31)->first();

                if (!empty($checkThird) > 0) {
                    $checkThird->update(['featured_status' => 1]);
                } else {
                    $newThirdData[] = [
                        'course_id' => $thirdData,
                        'featured_type' => 31,
                        'created_by' => $authUser->id
                    ];
                }
            }

            $addNewFeaturedCourses = stp_featured::insert($newFeaturedData);
            $addNewSecondFeaturedCourses = stp_featured::insert($newSecondData);
            $addNewThiedFeaturedCourses = stp_featured::insert($newThirdData);


            $removeFeaturedCourse = stp_featured::whereIn('course_id', $removehp)->where('featured_type', 29)->update(['featured_status' => 0]);
            $removeSecondFeaturedCourse = stp_featured::whereIn('course_id', $removeSecond)->where('featured_type', 30)->update(['featured_status' => 0]);
            $removeThirdFeaturedCourse = stp_featured::whereIn('course_id', $removeThird)->where('featured_type', 31)->update(['featured_status' => 0]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'successfully edit the courses featured']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function addCategory(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:stp_courses_categories,category_name',
                'icon' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Image validationt
                'description'=>'string|max:5000'
            ]);
            $authUser = Auth::user();

            if ($request->hasFile('icon')) {
                $image = $request->file('icon');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseCategoryIcon', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $data = [
                "category_name" => $request->name,
                "category_icon" => $imagePath ?? null,
                "category_description"=>$request->description,
                "created_by" => $authUser->id
            ];

            stp_courses_category::create($data);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Successfully added the category"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Validation',
                'error' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editCategory(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'icon' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048' // Image validationt
            ]);
            $authUser = Auth::user();

            $checkName = stp_courses_category::where('category_name', $request->name)
                ->where('id', '!=', $request->id)
                ->exists();
            if ($checkName) {
                throw ValidationException::withMessages(['category' => 'Category name had been used']);
            }

            $category = stp_courses_category::find($request->id);

            if ($request->hasFile('icon')) {
                if (!empty($category->category_icon)) {
                    Storage::delete('public/' . $category->category_icon);
                }
                $image = $request->file('icon');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseCategoryIcon', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $updateData = [
                'category_name' => $request->name,
                'category_icon' => $imagePath,
                'updated_by' => $authUser->id
            ];

            $category->update($updateData);
            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Validation',
                'error' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editHotPick(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string'
            ]);
            $authUser = Auth::user();
            $category = stp_courses_category::find($request->id);
            if ($request->type == 'enable') {
                $hotpick = 1;
            } else {
                $hotpick = 0;
            }

            $category->update(
                [
                    'course_hotPick' => $hotpick,
                    'updated_by' => $authUser->id
                ]
            );

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Update HotPick successfully']
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error',
                    'error' => $e->getMessage()
                ]
            );
        }
    }

    public function editCategoryStatus(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string'
            ]);

            if ($request->type == 'disable') {
                $status = 0;
            } else {
                $status = 1;
            }
            $authUser = Auth::user();
            $category = stp_courses_category::find($request->id);
            $category->update([
                'category_status' => $status,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully updated Status']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Imterna'
            ]);
        }
    }

    public function addTag(Request $request)
    {
        try {
            $request->validate([
                'tagID' => 'array',
                'newTag' => 'array',
                'courseID' => 'required|integer'
            ]);

            //create new tag and assign to the course
            if (isset($request->newTag)) {
                foreach ($request->newTag as $tag) {
                    //checking need to create new tag or not
                    $checkTag = stp_tag::where('tag_name', 'like', $tag)
                        ->where('tag_status', 1)->get()->first();

                    if (empty($checkTag)) {
                        $createNewTag = stp_tag::create([
                            'tag_name' => $tag
                        ]);
                        stp_course_tag::create([
                            'course_id' => $request->courseID,
                            'tag_id' => $createNewTag->id
                        ]);
                    }
                }
            }

            //assign exisitng tag to course
            if (isset($request->tagID)) {
                $getCourseTag = stp_course_tag::where('course_id', $request->courseID)
                    ->where('courseTag_status', 1)
                    ->pluck('tag_id')
                    ->toArray();


                $newTag = array_diff($request->tagID, $getCourseTag);
                $removeTag = array_diff($getCourseTag, $request->tagID);


                $newTagData = [];
                foreach ($newTag as $tag) {
                    $newTagData[] = [
                        'course_id' => $request->courseID,
                        'tag_id' => $tag
                    ];
                };


                $createNewCourseTag = stp_course_tag::insert($newTagData);

                foreach ($removeTag as $disableTag) {
                    $findData = stp_course_tag::where('course_id', $request->courseID)
                        ->where('tag_id', $disableTag)
                        ->first();

                    $disableData = $findData->update([
                        'courseTag_status' => 0
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => ['message' => 'success added the tag']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function searchTag(Request $request)
    {
        try {
            $request->validate(
                [
                    'search' => 'required|string|max:255'
                ]
            );

            $searchTag = stp_tag::where('tag_name', 'like', $request->search . '%')
                ->where('tag_status', 1)
                ->get();
            return response()->json([
                'success' => true,
                'data' => $searchTag
            ]);
            return $searchTag;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function courseTag(Request $request)
    {
        try {
            $request->validate([
                'courseID' => 'required|integer'
            ]);

            $course = stp_course::find($request->courseID);
            $courseTag = $course->tag;

            $tagList = [];
            foreach ($courseTag as $tag) {
                $tagList[] = [
                    "id" => $tag->tag['id'],
                    "tagName" => $tag->tag['tag_name']
                ];
            }
            return response()->json([
                'success' => true,
                'data' => $tagList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addSubject(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'category' => 'required|integer'
            ]);

            $checkSubject = stp_subject::where('subject_name', $request->name)
                ->where('subject_category', $request->category)
                ->exists();

            if ($checkSubject) {
                throw validationException::withMessages([
                    'subject' => 'Subject already exist in the category'
                ]);
            }

            $authUser = Auth::user();

            stp_subject::create([
                'subject_name' => $request->name,
                'subject_category' => $request->category,
                'created_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ["message" => "successfully created subject"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editSubject(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string',
                'category' => 'required|integer'
            ]);
            $authUser = Auth::user();
            $findSubject = stp_subject::find($request->id);
            $findSubject->update([
                'subject_name' => $request->name,
                'subject_category' => $request->category,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successfully"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editSubjectStatus(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string'
            ]);
            if ($request->type == 'disable') {
                $status = 0;
                $message = "Successfully disable the subject";
            } else {
                $status = 1;
                $message = "Successfully enable the subject";
            }
            $authUser = Auth::user();
            $findSubject = stp_subject::find($request->id);
            $findSubject->update([
                'subject_status' => $status,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => $message]
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
            $subjectList = stp_subject::where('subject_status', 1)->get();

            $list = [];
            foreach ($subjectList as $subject) {
                if ($subject->subject_status == 1) {
                    $status = "Active";
                } else {
                    $status = "Disable";
                }
                $list[] = [
                    'id' => $subject->id,
                    'name' => $subject->subject_name,
                    'category' => $subject->category->core_metaName,
                    'status' => $status
                ];
            }
            return response()->json([
                'success' => true,
                'data' => $list
            ]);
            return $list;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function subjectListAdmin(Request $request)

    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_subject::count() : (int)$request->per_page)
                : 10;

            $subjectList = stp_subject::when($request->filled('search'), function ($query) use ($request) {
                $query->where('subject_name', 'like', '%' . $request->search . '%');
            })

                ->paginate($perPage)
                ->through(function ($subject) {
                    switch ($subject->subject_status) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                        default:
                            $status = null;
                    }

                    return [
                        'id' => $subject->id,
                        'name' => $subject->subject_name,
                        'category' => $subject->category->core_metaName ?? '',
                        'status' => $status
                    ];
                });
            return response()->json($subjectList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function categoryList(Request $request)
    {
        try {
            $request->validate([
                'search' => 'string',
                'hotpick' => 'integer',
                'status' => 'integer'
            ]);

            $categoryList = stp_courses_category::when($request->filled('search'), function ($query) use ($request) {
                $query->orWhere('category_name', 'like', '%' . $request->search . '%');
            })
                ->when($request->filled('hotpick'), function ($query) use ($request) {
                    $query->orWhere('course_hotPick', $request->hotpick);
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->orWhere('category_status', $request->status);
                })
                ->paginate(10)
                ->through(function ($category) {
                    switch ($category->category_status) {
                        case 0:
                            $satus = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                    }
                    return [
                        "id" => $category->id,
                        "category_name" => $category->category_name,
                        "course_hotPick" => $category->course_hotPick ?? 0,
                        "category_status" => $status
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $categoryList
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "validation Error",
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' > $e->getMessage()
            ]);
        }
    }

    public function categoryListAdmin(Request $request)

    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_courses_category::count() : (int)$request->per_page)
                : 10;

            $categoryList = stp_courses_category::when($request->filled('search'), function ($query) use ($request) {
                $query->where('category_name', 'like', '%' . $request->search . '%');
            })

                ->paginate($perPage)
                ->through(function ($category) {
                    switch ($category->category_status) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                        default:
                            $status = null;
                    }

                    return [
                        'id' =>  $category->id,
                        'name' =>  $category->category_name,
                        "course_hotPick" => $category->course_hotPick ?? 0,
                        "category_status" => $status
                    ];
                });
            return response()->json($categoryList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetAdminPassword(Request $request)
    {
        try {
            $request->validate([
                'currentPassword' => 'required|string|min:8',
                'newPassword' => 'required|string|min:8',
                'confirmPassword' => 'required|string|min:8|same:newPassword'
            ]);
            $authUser = Auth::user();
            if (!Hash::check($request->currentPassword, $authUser->password)) {
                throw ValidationException::withMessages(["password does not match"]);
            }

            $authUser->update([
                'password' => Hash::make($request->newPassword),
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

    public function applicantDetailInfo(Request $request)   //Header and basic information for the applicant
    {
        try {
            // Get the authenticated user
            $authUser = Auth::user();

            $request->validate([
                'form_status' => 'integer|nullable',
                'student_id' => 'integer|nullable',
                'courses_id' => 'integer|nullable',
                 'search' => 'string|nullable'
            ]);

            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_submited_form::count() : (int)$request->per_page)
                : 10;

            $studentList = stp_student_detail::when($request->filled('search'), function ($query) use ($request) {
                $query->where('student_detailFirstName', 'like', '%' . $request->search . '%');
            });

            $applicantInfo = stp_submited_form::query()
                ->when($request->filled('student_id'), function ($query) use ($request) {
                    $query->where('student_id', $request->student_id);
                })
                ->when($request->filled('courses_id'), function ($query) use ($request) {
                    $query->where('courses_id', $request->courses_id);
                })
                ->when($request->filled('form_status'), function ($query) use ($request) {
                    $query->where('form_status', $request->form_status);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->search;
                    $query->whereHas('student.detail', function ($query) use ($search) {
                        $query->where('student_detailFirstName', 'like', '%' . $search . '%')
                              ->orWhere('student_detailLastName', 'like', '%' . $search . '%');
                    });
                })
                ->paginate($perPage)
                ->through(function ($applicant) {
                    switch ($applicant->form_status) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                        case 2:
                            $status = "Pending";
                            break;
                        case 3:
                            $status = "Rejected";
                            break;
                        case 4:
                            $status = "Accepted";
                            break;
                        default:
                            $status = null;
                    }
                    return [
                        "id" => $applicant->id ?? 'N/A',
                        "course_name" => $applicant->course->course_name ?? 'N/A',
                        "institution" => $applicant->course->school->school_name,
                        "form_status" => $status,
                        "student_name" => $applicant->student->detail->student_detailFirstName . ' ' . $applicant->student->detail->student_detailLastName,
                        "country_code" => $applicant->student->student_countryCode ?? 'N/A',
                        "contact_number" => $applicant->student->student_contactNo ?? 'N/A',
                        'student_id' => $applicant->student->id, // Add student_id to the result
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


    public function editApplicantStatus(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255',
                'feedback' => 'string|max:255'
            ]);
            $authUser = Auth::user();

            if ($request->type == 'Active') {
                $status = 1;
                $message = "Successfully Set the Application Status to Active";
            } elseif ($request->type == 'Pending') {
                $status = 2;
                $message = "Successfully Set the Applicantion status to Pending";
            } elseif ($request->type == 'Reject') {
                $status = 3;
                $message = "Successfully Rejected the Applicant";
            } elseif ($request->type == 'Accept') {
                $status = 4;
                $message = "Successfully Accepted the Applicant";
            } elseif ($request->type == 'Delete') {
                $status = 0;
                $message = "Successfully Deleted the Applicant";
            }


            $applicant = stp_submited_form::find($request->id);

            $applicant->update([
                'form_status' => $status,
                'form_feedback' => $request->feedback,
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

    public function editApplicantForm(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'courses_id' => 'required|integer',
                'school_id' => 'required|integer',
                'feedback' => 'string|max:255',
                'created_at' => 'required|date_format:Y-m-d'
            ]);

            // Retrieve the course based on the provided courses_id
            $course = stp_course::find($request->courses_id);

            // Check if the course exists and belongs to the specified school_id
            if (!$course || $course->school_id != $request->school_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The course does not exist in this institute'
                ], 400);
            }

            // Retrieve the applicant form by ID
            $editApplication = stp_submited_form::find($request->id);

            // Temporarily disable automatic timestamps
            $editApplication->timestamps = false;

            // Update the applicant form
            $editApplication->update([
                'courses_id' => $request->courses_id,
                'school_id' => $course->school_id, // Use the school_id from the course
                'created_at' => $request->created_at,
                'form_feedback' => $request->feedback,
                'updated_by' => Auth::id(),
                'updated_at' => now()
            ]);

            // Re-enable automatic timestamps
            $editApplication->timestamps = true;

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Applicant Successfully"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function addPackage(Request $request)
    {
        try {
            $request->validate([
                'package_name' => 'required|string|max:255',
                'package_detail' => 'required|string|max:5000',
                'package_type' => 'required|integer',
                'package_price' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/'
            ]);


            $authUser = Auth::user();

            stp_package::create([
                'package_name' => $request->package_name,
                'package_detail' => $request->package_detail, // Save the HTML list
                'package_type' => $request->package_type,
                'package_price' => $request->package_price,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => ["message" => "Succesfully Created a Package"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validation Error",
                'error' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function editPackage(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'package_name' => 'required|string|max:255',
                'package_detail' => 'required|string|max:5000',
                'package_type' => 'required|integer',
                'package_price' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/'
            ]);

            $authUser = Auth::user();

            $findPackage = stp_package::find($request->id);
            $findPackage->update([
                'package_name' => $request->package_name,
                'package_detail' => $request->package_detail, // Save the HTML list
                'package_type' => $request->package_type,
                'package_price' => $request->package_price,
                'updated_by' => $authUser->id,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Package Successfully"]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deletePackage(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255',
            ]);
            $authUser = Auth::user();

            if ($request->type == 'Delete') {
                $status = 0;
                $message = "Successfully Deleted the Package";
            }


            $applicant = stp_package::find($request->id);

            $applicant->update([
                'package_status' => $status,
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
    public function packageList(Request $request)
    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_package::count() : (int)$request->per_page)
                : 10;
    
            // Check if the 'id' parameter is provided to get a specific package
            if ($request->filled('id')) {
                $package = stp_package::find($request->id);
    
                if (!$package) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Package not found'
                    ], 404);
                }
    
                $status = ($package->package_status == 1) ? "Active" : "Disable";
    
                return response()->json([
                    "id" => $package->id,
                    "package_name" => $package->package_name,
                    "package_detail" => $package->package_detail,
                    "package_type" => $package->package_type,
                    "package_price" => $package->package_price,
                    "package_status" => $status
                ]);
            }
    
            // If 'id' is not provided, return the paginated package list
            $packageList = stp_package::query()
                ->when($request->filled('package_type'), function ($query) use ($request) {
                    $query->where('package_type', $request->package_type);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('package_name', 'like', '%' . $request->search . '%');
                })
                ->paginate($perPage)
                ->through(function ($package) {
                    $status = ($package->package_status == 1) ? "Active" : "Disable";
                    return [
                        "id" => $package->id,
                        "package_name" => $package->package_name,
                        "package_detail" => $package->package_detail,
                        "package_type" => $package->package_type,
                        "package_price" => $package->package_price,
                        "package_status" => $status
                    ];
                });
    
            return response()->json($packageList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
    
    public function resetAdminDummyPassword(Request $request)
    {
        try {
            $authUser = Auth::user();

            // Check if the user's status is 3
            if ($authUser->status == 3) {
                // Force user to reset password
                $request->validate([
                    'currentPassword' => 'required|string|min:8',
                    'newPassword' => 'required|string|min:8',
                    'confirmPassword' => 'required|string|min:8|same:newPassword'
                ]);

                if (!Hash::check($request->currentPassword, $authUser->password)) {
                    throw ValidationException::withMessages(["password does not match"]);
                }

                $authUser->update([
                    'password' => Hash::make($request->newPassword),
                    'status' => 1,  // Change status to 1 after resetting password
                    'updated_by' => $authUser->id
                ]);

                return response()->json([
                    'success' => true,
                    'data' => ['message' => "Successfully reset password"]
                ]);
            }

            // If the status is not 3, ignore the password reset
            return response()->json([
                'success' => true,
                'data' => ['message' => "No need to reset password"]
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

    public function dataList(Request $request)
    {
        try {
            $request->validate([
                'core_meta_type' => 'required|string'
            ]);
            $dataList = stp_core_meta::where('core_metaType', $request->core_meta_type)->get()
                ->map(function ($list) {
                    return [
                        'id' => $list->id,
                        'core_metaType' => $list->core_metaType,
                        'core_metaName' => $list->core_metaName,
                        'status' => $list->core_metaStatus
                    ];
                });
            return response()->json([
                'success' => true,
                'data' => $dataList
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

    public function dataFilterList(Request $request)
    {
        try {
            $getList = stp_core_meta::pluck('core_metaType')->unique()->values();
            return response()->json([
                'success' => true,
                'data' => $getList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function addDataList(Request $request)
    {
        try {
            $request->validate([
                'metaType' => 'required|string',
                'metaName' => 'required|string'
            ]);

            $authUser = Auth::user();
            $newData = stp_core_meta::create([
                'core_metaType' => $request->metaType,
                'core_metaName' => $request->metaName,
                'created_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully added new meta'
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

    public function editData(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'metaName' => 'required|string'
            ]);

            $getData = stp_core_meta::find($request->id);
            $type = $getData->core_metaType;
            $validateNewData = stp_core_meta::where('core_metaType', $type)->where('core_metaName', $request->metaName)->exists();
            if ($validateNewData) {
                throw ValidationException::withMessages([
                    'data' => 'data with the meta name already exist'
                ]);
            }

            $updateData = $getData->update([
                'core_metaName' => $request->metaName
            ]);

            return response()->json([
                'success' => true,
                'message' => "successfully update the data"
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
                'message' => 'Internal server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function editDataStatus(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|string',
                'id' => 'required|integer'
            ]);

            $findData = stp_core_meta::find($request->id);
            $authUser = Auth::user();

            if ($request->action == 'disable') {
                $status = 0;
            } else {
                $status = 1;
            }

            $findData->update([
                'core_metaStatus' => $status,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully ' . $request->action
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

    public function addAdmin(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'string|max:255',
                'country_code' => 'required|string|max:255',
                'contact_no' => 'required|string|max:255',
                'password' => 'required|string|max:255',
                'user_detailPostcode' => 'required|string|max:255',
                'user_detailCountry' => 'required|string|max:255',
                'user_detailCity' => 'required|string|max:255',
                'user_detailState' => 'required|string|max:255',
                'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Image validation
            ]);

            $authUser = Auth::user();
            $checkingName = User::where('id',  $authUser->id)
                ->where('name', $request->name)
                ->exists();

            if ($checkingName) {
                throw ValidationException::withMessages([
                    "names" => ['This Name Already Exist.']
                ]);
            }

            if ($request->hasFile('profile_pic')) {
                $image = $request->file('profile_pic');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('profilePic', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'ic_number' => $request->ic_number,
                'country_code' => $request->country_code,
                'contact_no' => $request->contact_no,
                'password' => $request->password,
                'profile_pic' => $imagePath ?? '', // Image validation
                'user_role' => 1,
                'status' => 3,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);



            stp_user_detail::create([
                'user_detailPostcode' => $request->user_detailPostcode,
                'user_detailCountry' => $request->user_detailCountry,
                'user_detailCity' => $request->user_detailCity,
                'user_detailState' => $request->user_detailState,
                'user_detailStatus' => 1,
                'user_id' => $user->id,
                'created_by' => $authUser->id,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the New Admin']
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
    public function disableAdmin(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255'
            ]);

            $authUser = Auth::user();

            if ($request->type == 'disable') {
                $status = 0;
                $message = "Successfully Disable the Admin";
            }
            $adminStatus = User::find($request->id);

            $adminStatus->update([
                'status' => $status,
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

    public function adminList(Request $request)
    {
        try {
            $adminList = User::query()
                ->where('status', 1)
                ->where('user_role', 1)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%');
                })
                ->paginate(10)
                ->through(function ($admin) {
                    $status = ($admin->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $admin->name,
                        "email" => $admin->email,
                        "ic_number" => $admin->ic_number,
                        "contact_no" => $admin->contact_no,
                        "status" => "Active"
                    ];
                });

            return $adminList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function adminListAdmin(Request $request)

    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? User::count() : (int)$request->per_page)
                : 10;

            $adminList = User::when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            })

                ->paginate($perPage)
                ->through(function ($admin) {
                    switch ($admin->status) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Active";
                            break;
                        case 2:
                            $status = "Pending";
                            break;
                        case 3:
                            $status = "Temporary";
                            break;
                        case 4:
                            $status = "Temporary-Disable";
                            break;
                        default:
                            $status = null;
                    }

                    return [
                        'id' => $admin->id,
                        "name" => $admin->name,
                        "email" => $admin->email,
                        "ic_number" => $admin->ic_Number,
                        "contact_no" => $admin->contact_no,
                        'status' => $status
                    ];
                });
            return response()->json($adminList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function editAdmin(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'email' => 'string|max:255',
                'ic_number' => 'nullable|string',
                'country_code' => 'required|string|max:255',
                'contact_no' => 'required|string|max:255',
                'password' => 'nullable|string|max:255', // Make password nullable for edits
                'user_detailPostcode' => 'required|string|max:255',
                'user_detailCountry' => 'required|string|max:255',
                'user_detailCity' => 'required|string|max:255',
                'user_detailState' => 'required|string|max:255',
                'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Image validation
            ]);

            // Check if the name already exists for another user
            $checkingName = User::where('id', '!=', $request->id)
                ->where('name', $request->name)
                ->exists();

            if ($checkingName) {
                throw ValidationException::withMessages([
                    "names" => ['This Name Already Exists.']
                ]);
            }

            $admin = User::findOrFail($request->id); // Find the user or throw a 404 error

            // Handle profile picture upload
            $imagePath = $admin->profile_pic; // Default to current profile picture
            if ($request->hasFile('profile_pic')) {
                if (!empty($admin->profile_pic)) {
                    Storage::delete('public/' . $admin->profile_pic);
                }
                $image = $request->file('profile_pic');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('profilePic', $imageName, 'public');
            }

            // Update the admin user
            $admin->update([
                'name' => $request->name,
                'email' => $request->email,
                'ic_number' => $request->ic_number,
                'country_code' => $request->country_code,
                'contact_no' => $request->contact_no,
                'password' => $request->password ? bcrypt($request->password) : $admin->password, // Only update password if provided
                'profile_pic' => $imagePath,
                'status' => 1,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ]);

            // Update the admin user's details
            $admin->detail->update([
                'user_detailPostcode' => $request->user_detailPostcode,
                'user_detailCountry' => $request->user_detailCountry,
                'user_detailCity' => $request->user_detailCity,
                'user_detailState' => $request->user_detailState,
                'user_detailStatus' => 1,
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

    public function bannerListAdmin(Request $request)
{
    try {
        // Get the per_page value from the request, default to 10 if not provided or empty
        $perPage = $request->filled('per_page') && $request->per_page !== ""
            ? ($request->per_page === 'All' ? stp_advertisement_banner::count() : (int)$request->per_page)
            : 10;

        // Build the query
        $query = stp_advertisement_banner::query();

        // Filter by search term if provided
        if ($request->filled('search')) {
            $query->where('banner_name', 'like', '%' . $request->search . '%');
        }

        // Filter by ID if provided
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Paginate the results
        $bannerList = $query->paginate($perPage)
            ->through(function ($banner) {
                // Map banner_status to status label
                switch ($banner->banner_status) {
                    case 0:
                        $status = "Disable";
                        break;
                    case 1:
                        $status = "Active";
                        break;
                    default:
                        $status = null;
                }

                // Get the featured metadata
                $featured = $banner->banner ? [
                    'featured_id' => $banner->banner->id,
                    'core_metaName' => $banner->banner->core_metaName
                ] : [];

                return [
                    'id' => $banner->id,
                    'name' => $banner->banner_name,
                    'url' => $banner->banner_url,
                    'file' => $banner->banner_file,
                    'banner_duration' => $banner->banner_start . ' - ' . $banner->banner_end,
                    'banner_start' => $banner->banner_start,
                    'banner_end' => $banner->banner_end,
                    'featured' => $featured, // Single featured_id and core_metaName
                    'status' => $status
                ];
            });

        return response()->json($bannerList);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Internal Server Error',
            'error' => $e->getMessage()
        ], 500);
    }
}

    
    public function addBanner(Request $request)
    {
        try {
            // Validate the incoming request data
            $request->validate([
                'banner_name' => 'required|string|max:255',
                'banner_file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'banner_url' => 'required|string|max:255',
                'featured_id' => 'required|array',  // Validate as array
                'featured_id.*' => 'integer',       // Each item in the array should be an integer
                'banner_start' => 'required|date_format:Y-m-d H:i:s',
                'banner_end' => 'required|date_format:Y-m-d H:i:s'
            ]);

            $authUser = Auth::user();
            $imagePath = null;

            // Handle the banner file upload
            if ($request->hasFile('banner_file')) {
                $image = $request->file('banner_file');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('bannerFile', $imageName, 'public');
            }

            // Loop through each featured_id and create a banner for each
            foreach ($request->featured_id as $featuredId) {
                stp_advertisement_banner::create([
                    'banner_name' => $request->banner_name,
                    'banner_file' => $imagePath,
                    'banner_url' => $request->banner_url,
                    'featured_id' => $featuredId,  // Insert each featured_id here
                    'banner_start' => $request->banner_start,
                    'banner_end' => $request->banner_end,
                    'created_by' => $authUser->id,
                    'banner_status' => 1,
                    'created_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Successfully Added the Banner(s)']
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

    public function editBanner(Request $request)
    {
        try {
            $authUser = Auth::user();

            // Adjust validation rules
            $request->validate([
                'id' => 'required|integer',
                'banner_name' => 'required|string|max:255',
                'banner_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Make banner_file nullable
                'banner_url' => 'required|string|max:255',
                'featured_id' => 'required|array',  // Validate as array
                'featured_id.*' => 'integer',       // Each item in the array should be an integer
                'banner_start' => 'required|date_format:Y-m-d H:i:s',
                'banner_end' => 'required|date_format:Y-m-d H:i:s'
            ]);

            // Find the existing banner by id
            $adBanner = stp_advertisement_banner::find($request->id);

            if (!$adBanner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banner not found'
                ]);
            }

            // Handle file upload if a new file is provided
            if ($request->hasFile('banner_file')) {
                // Delete the old file if it exists
                if (!empty($adBanner->banner_file)) {
                    Storage::delete('public/' . $adBanner->banner_file);
                }

                // Upload the new file
                $image = $request->file('banner_file');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('bannerFile', $imageName, 'public'); // Store in 'storage/app/public/bannerFile'
            } else {
                // Use the existing banner file if no new file is uploaded
                $imagePath = $adBanner->banner_file;
            }

            // Update the banner with the new or existing file path and other details
            foreach ($request->featured_id as $featuredId) {
            $adBanner->update([
                'banner_name' => $request->banner_name,
                'banner_file' => $imagePath, // Use the existing or new file path
                'banner_url' => $request->banner_url,
               'featured_id' => $featuredId,  // Insert each featured_id here
                'banner_start' => $request->banner_start,
                'banner_end' => $request->banner_end,
                'updated_by' => $authUser->id,
                'updated_at' => now()
            ]);
        }

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Banner Successfully"]
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
    public function disableBanner(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'type' => 'required|string|max:255'
            ]);

            $authUser = Auth::user();
            if ($request->type == 'disable') {
                $status = 0;
                $message = "Successfully Disable the Banner";
            }

            $banner = stp_advertisement_banner::find($request->id);
            $banner->update([
                'banner_status' => $status,
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

    public function courseFeaturedList(Request $request)
    {
        try {
            $featuredList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [29, 30, 31])
                ->paginate(10)
                ->through(function ($featured) {
                    $status = ($featured->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $featured->core_metaName,
                        "id" => $featured->id,
                        "status" => "Active"
                    ];
                });

            return $featuredList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    public function universityFeaturedList(Request $request)
    {
        try {
            $featuredList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [28, 30, 31])
                ->paginate(10)
                ->through(function ($featured) {
                    $status = ($featured->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $featured->core_metaName,
                        "id" => $featured->id,
                        "status" => "Active"
                    ];
                });

            return $featuredList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    public function bannerFeaturedList(Request $request)
    {
        try {
            $featuredList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [68, 69, 70, 71, 72, 73, 74])
                ->paginate(10)
                ->through(function ($featured) {
                    $status = ($featured->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $featured->core_metaName,
                        "id" => $featured->id,
                        "status" => "Active"
                    ];
                });

            return $featuredList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function instituteCategoryList(Request $request)
    {
        try {
            $categoryList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [14, 15, 16])
                ->paginate(10)
                ->through(function ($category) {
                    $status = ($category->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $category->core_metaName,
                        "id" => $category->id,
                        "status" => "Active"
                    ];
                });

            return $categoryList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function accountTypeList(Request $request)
    {
        try {
            $categoryList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [64, 65])
                ->paginate(10)
                ->through(function ($category) {
                    $status = ($category->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $category->core_metaName,
                        "id" => $category->id,
                        "status" => "Active"
                    ];
                });

            return $categoryList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function packageTypeList(Request $request)
    {
        try {
            $categoryList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [60, 61, 62, 63, 76, 77])
                ->paginate(10)
                ->through(function ($category) {
                    $status = ($category->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $category->core_metaName,
                        "id" => $category->id,
                        "status" => "Active"
                    ];
                });

            return $categoryList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
    
    public function intakeList(Request $request)
    {
        try {
            $categoryList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52])
                ->paginate(20)
                ->through(function ($category) {
                    $status = ($category->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $category->core_metaName,
                        "id" => $category->id,
                        "status" => "Active"
                    ];
                });

            return $categoryList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function schoolListAdmin(Request $request)
    {
        try {
            $schoolList = stp_school::query()
                ->where('school_status', 1)
                ->get()
                ->map(function ($school) {
                    $status = ($school->status == 1) ? "Active" : "Inactive";
                    return [
                        "name" => $school->school_name,
                        "id" => $school->id,
                        "status" => "Active"
                    ];
                });

            return $schoolList;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
