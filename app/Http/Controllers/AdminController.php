<?php

namespace App\Http\Controllers;

use Carbon\Carbon; // Import the Carbon library at the top
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
use App\Models\stp_featured_request;
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

    public function studentDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $authUser = Auth::user();
            $student = stp_student::with(['detail'])->find($request->id);
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $request->id,
                    'name' => $student->student_userName,
                    'first_name' => $student->detail->student_detailFirstName ?? null,
                    'last_name' => $student->detail->student_detailLastName ?? null,
                    'ic' => $student->student_icNumber,
                    'email' => $student->student_email,
                    'country_code' => $student->student_countryCode,
                    'contact_number' => $student->student_contactNo,
                    'gender' => $student->detail->studentGender->core_metaName ?? null,
                    'gender_id' => $student->detail->studentGender->id ?? null,
                    'address' => $student->detail->student_detailAddress ?? null,
                    'country' => $student->detail->country_id ?? null,
                    'state' => $student->detail->state_id ?? null,
                    'city' => $student->detail->city_id ?? null,
                    'postcode' => $student->detail->student_detailPostcode ?? '',
                    'password' => $student->student_password
                ]
            ]);
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
                'first_name' => 'string|max:255',
                'last_name' => 'string|max:255',
                'address' => 'string|max:255',
                'country' => 'integer',
                'city' => 'integer',
                'state' => 'integer',
                'gender' => 'integer',
                'postcode' => 'string',
                'ic' => 'string|min:6',
                'password' => 'string|min:8|nullable', // Allow password to be nullable
                'confirm_password' => 'string|min:8|nullable|same:password', // Allow confirm_password to be nullable
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000' // Image validation
            ]);

            $authUser = Auth::user();

            // Check IC number uniqueness
            $checkingIc = stp_student::where('student_icNumber', $request->ic)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingIc) {
                throw ValidationException::withMessages([
                    'ic' => ['IC has been used'],
                ]);
            }

            // Check contact number uniqueness
            $checkingUserContact = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_number' => ['Contact number has been used'],
                ]);
            }

            $student = stp_student::find($request->id);
            $studentDetail = $student->detail;

            // Handle image upload
            if ($request->hasFile('image')) {
                if (!empty($student->student_profilePic)) {
                    Storage::delete('public/' . $student->student_profilePic);
                }

                $image = $request->file('image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('studentProfilePic', $imageName, 'public');
                $student->student_profilePic = $imagePath;
            }

            // Check email uniqueness
            $checkingEmail = stp_student::where('student_email', $request->email)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['Email has been taken'],
                ]);
            }

            // Update student information
            $updateData = [
                "student_userName" => $request->name,
                'student_icNumber' => $request->ic,
                'student_email' => $request->email,
                'student_countryCode' => $request->country_code,
                'student_contactNo' => $request->contact_number,
                'updated_by' => $authUser->id
            ];

            // Update password only if provided
            if (!empty($request->password)) {
                $updateData['student_password'] = Hash::make($request->password);
            }

            $updateingStudent = $student->update($updateData);

            // Update student details
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
                    "data" => ["message" => "Update successful"]
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
                'message' => 'Internal Server Error',
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
                'school_fullDesc' => 'required',
                'school_shortDesc' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
                'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Add cover photo validation
                'album.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Add album photo validation
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

            $placeName =  $request->name; // E.g., 'Eiffel Tower, Paris, France'

            // Encode the place name to ensure it's URL safe
            $encodedPlace = urlencode($placeName);

            $embedUrl = "https://www.google.com/maps?q={$placeName}&output=embed";

            // Generate the Google Maps link
            $googleMapsLink = "https://www.google.com/maps/search/?api=1&query={$encodedPlace}";

            // Generate the iframe embed code
            $iframeCode = "<iframe src='{$embedUrl}' width='600' height='450' style='border:0;' allowfullscreen='' loading='lazy'></iframe>";

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
                'school_officalWebsite' => $request->school_website,
                'person_inChargeName' => $request->person_in_charge_name,
                'person_inChargeNumber' => $request->person_in_charge_contact,
                'person_inChargeEmail' => $request->person_in_charge_email,
                'school_google_map_location' => $googleMapsLink,
                'school_logo' => $imagePath ?? null,
                'account_type' => $request->account,
                'school_location' => $iframeCode,
                'school_status' => 3,
                'created_by' => $authUser->id
            ]);

            // Handle cover photo
            if ($request->hasFile('cover')) {
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
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255|email',
                'school_fullDesc' => 'required|string|max:5000',
                'school_shortDesc' => 'required|string|max:255',
                'school_location' => 'required|string',
                'school_google_map_location' => 'required|string',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
                'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
                'album.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
                'featured' => 'nullable|array',
                'person_in_charge_name' => 'required|string|max:255',
                'person_in_charge_contact' => 'required|string|max:255',
                'person_in_charge_email' => 'required|email',
                'category' => 'required|integer',
                'account' => 'required|integer',

            ]);

            $authUser = Auth::user();
            // Check if email is used by another school
            // $checkingEmail = stp_school::where('id', '!=', $request->id)
            //     ->where('school_email', $request->email)
            //     ->exists();
            // if ($checkingEmail) {
            //     throw ValidationException::withMessages([
            //         'email' => ['Email has been used'],
            //     ]);
            // }

            // Check if contact number is used by another school
            $checkingUserContact = stp_school::where('school_countryCode', $request->country_code)
                ->where('school_contactNo', $request->contact_number)
                ->where('id', '!=', $request->id)
                ->exists();
            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact number has been used'],
                ]);
            }

            $school = stp_school::find($request->id);

            // Handle logo update
            if ($request->hasFile('logo')) {
                if (!empty($school->school_logo)) {
                    Storage::delete('public/' . $school->school_logo);
                }
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolLogo', $imageName, 'public');
            }

            // Handle cover photo update
            if ($request->hasFile('cover')) {
                $existingCover = stp_school_media::where('school_id', $school->id)->where('schoolMedia_type', 66)->first();
                if ($existingCover) {
                    Storage::delete('public/' . $existingCover->schoolMedia_location);
                    $existingCover->delete();
                }

                $cover = $request->file('cover');
                $coverName = $school->school_name . '_cover.' . $cover->getClientOriginalExtension();
                $coverPath = $cover->storeAs('schoolMedia', $coverName, 'public');
                $coverFormat = $cover->getClientOriginalExtension();

                stp_school_media::create([
                    'school_id' => $school->id,
                    'schoolMedia_type' => 66,
                    'schoolMedia_name' => $coverName,
                    'schoolMedia_location' => $coverPath,
                    'schoolMedia_format' => $coverFormat,
                    'schoolMedia_status' => 1,
                    'created_by' => $authUser->id,
                    'created_at' => now()
                ]);
            }

            // Handle album photo update
            if ($request->hasFile('album')) {
                foreach ($request->file('album') as $albumPhoto) {
                    // Check if this specific album photo already exists for the school
                    $albumPhotoName = $albumPhoto->getClientOriginalName();
                    $existingAlbumPhoto = stp_school_media::where('school_id', $school->id)
                        ->where('schoolMedia_name', $albumPhotoName)
                        ->where('schoolMedia_type', 67) // Ensure it's an album photo
                        ->first();

                    // If the album photo doesn't exist, store and create a new record
                    if (!$existingAlbumPhoto) {
                        $albumPhotoPath = $albumPhoto->storeAs('schoolMedia', $albumPhotoName, 'public');
                        $albumPhotoFormat = $albumPhoto->getClientOriginalExtension();

                        stp_school_media::create([
                            'school_id' => $school->id,
                            'schoolMedia_type' => 67,
                            'schoolMedia_name' => $albumPhotoName,
                            'schoolMedia_location' => $albumPhotoPath,
                            'schoolMedia_format' => $albumPhotoFormat,
                            'schoolMedia_status' => 1,
                            'created_by' => $authUser->id,
                            'created_at' => now()
                        ]);
                    }
                }
            }
            // // Handle featured types update
            // if ($request->has('featured')) {
            //     // First, remove existing featured records for the school
            //     stp_featured::where('school_id', $school->id)->delete();

            //     // Insert new featured records
            //     foreach ($request->featured as $featureId) {
            //         stp_featured::create([
            //             'school_id' => $school->id,
            //             'featured_type' => $featureId,
            //             'featured_status' => 1
            //         ]);
            //     }
            // }
            // Handle featured types update
            $existingFeatured = stp_featured::where('school_id', $request->id)
                ->pluck('featured_type')
                ->toArray();

            if (!empty($request->featured)) {
                $newFeatured = array_diff($request->featured, $existingFeatured);
                $existingToKeep = array_intersect($request->featured, $existingFeatured);

                stp_featured::where('school_id', $request->id)
                    ->whereIn('featured_type', $existingToKeep)
                    ->update([
                        'featured_status' => 1,
                        'updated_at' => now()
                    ]);
                foreach ($newFeatured as $featured) {
                    stp_featured::create([
                        'school_id' => $request->id,
                        'featured_type' => $featured,
                        'featured_status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),

                    ]);
                }
                $removeFeatured = array_diff($existingFeatured, $request->featured);
                stp_featured::where('school_id', $request->id)
                    ->whereIn('featured_type', $removeFeatured)
                    ->update([
                        'featured_status' => 0,
                        'updated_at' => now()
                    ]);
            } else {
                stp_featured::where('school_id', $request->id)
                    ->update([
                        'featured_status' => 0,
                        'updated_at' => now()
                    ]);
            }

            // Update school details
            $school->update([
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
                'school_location' => $request->school_location,
                'school_google_map_location' => $request->school_google_map_location,
                'school_logo' => $imagePath ?? $school->school_logo,
                'account_type' => $request->account,
                'updated_by' => $authUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'School updated successfully']
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
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
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

            // Filter school media by schoolMedia_status = 1
            $medias = $school->media->filter(function ($media) {
                return $media->schoolMedia_status === 1;
            })->map(function ($media) {
                return [
                    'id' => $media->id,
                    'schoolMedia_name' => $media->schoolMedia_name,
                    'schoolMedia_location' => $media->schoolMedia_location,
                    'schoolMedia_type' => $media->schoolMedia_type,
                ];
            });

            //Prepare the courses data, filter featured by featured_status = 1
            $courses = $school->courses->map(function ($course) {
                return [
                    'course_name' => $course->course_name,
                    // 'course_featured' => $course->featured->filter(function ($featured) {
                    //     return $featured->featured_status === 1;  // Filter by featured_status = 1
                    // })->map(function ($featured) {
                    //     return [
                    //         'featured_type' => $featured->featured_type,
                    //         'featured_startTime' => $featured->featured_startTime,
                    //         'featured_endTime' => $featured->featured_endTime,
                    //     ];
                    // }),
                ];
            });

            // Prepare the school featured data, filter by featured_status = 1
            $schoolFeatured = $school->featured->filter(function ($featured) {
                return $featured->featured_status === 1;  // Filter by featured_status = 1
            })->map(function ($featured) {
                return [
                    'featured_type' => $featured->featured_type,
                    'featured_startTime' => $featured->featured_startTime,
                    'featured_endTime' => $featured->featured_endTime,
                ];
            });

            // Return the final response
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $school->id,
                    'name' => $school->school_name,
                    'email' => $school->school_email,
                    'country_code' => $school->school_countryCode,
                    'contact_number' => $school->school_contactNo,
                    'fullDescription' => $school->school_fullDesc,
                    'shortDescription' => $school->school_shortDesc,
                    'school_address' => $school->school_address,
                    'country' => $school->country->country_name ?? '',
                    'country_id' => $school->country_id,
                    'state' => $school->state->state_name ?? '',
                    'state_id' => $school->state_id,
                    'city' => $school->city->city_name ?? '',
                    'city_id' => $school->city_id,
                    'logo' => $school->school_logo ?? '',
                    'school_website' => $school->school_officalWebsite ?? '',
                    'courses' => $courses, // List of courses with filtered featured data
                    'schoolFeatured' => $schoolFeatured, // List of filtered school's featured data
                    'school_google_map_location' => $school->school_google_map_location ?? '',
                    'category' => $school->institue_category,
                    'PIC_name' => $school->person_inChargeName,
                    'PIC_number' => $school->person_inChargeNumber,
                    'PIC_email' => $school->person_inChargeEmail,
                    'account' => $school->account_type,
                    'location' => $school->school_location,
                    'media' => $medias // Filtered media data
                ]
            ]);
        } catch (\Exception $e) {
            // Handle any errors
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
                'courseFeatured' => 'nullable|array',
                'courseFeatured.*' => 'integer',
                'category' => 'required|integer',
                'qualification' => 'required|integer',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
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
            $course = stp_course::create([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'study_mode' => $request->mode,
                'course_logo' => $imagePath ?? '',
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

            foreach ($request->courseFeatured as $courseFeatured) {
                stp_featured::create([
                    'course_id' => $course->id,
                    'featured_type' => $courseFeatured,
                    'featured_status' => 1,
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
                'errors' => $e->errors()
            ], 422); // Explicitly return 422 for validation errors
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
    public function courseListFeatured(Request $request)
    {
        try {
            $courseList = stp_course::when($request->filled('search'), function ($query) use ($request) {
                $query->where('course_name', 'like', '%' . $request->search . '%');
            })
                ->when($request->filled('school_id'), function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->where('course_status', 1) // Only get active courses
                ->whereHas('school', function ($query) {
                    $query->whereIn('school_status', [1, 2, 3]); // Only include courses from active schools
                })
                ->get()
                ->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'name' => $course->course_name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $courseList
            ]);
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
                ->when($request->filled('school_id'), function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->whereHas('school', function ($query) {
                    $query->whereIn('school_status', [1, 2, 3]); // Only include courses from active schools
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
                        'school_status' => $course->school->school_status,
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
                'id' => 'required|integer'
            ]);

            $courseList = stp_course::find($request->id);

            // Determine the logo to use (course logo or fallback to school logo)
            if (empty($courseList->course_logo)) {
                $logo = $courseList->school->school_logo;
            } else {
                $logo = $courseList->course_logo;
            }

            // Prepare the tag list
            $courseTag = $courseList->tag;
            $tagList = [];
            foreach ($courseTag as $tag) {
                $tagList[] = [
                    "id" => $tag->tag['id'],
                    "tagName" => $tag->tag['tag_name']
                ];
            }

            // Fetch all intakes associated with the course, filtering by intake_status = 1
            $intakeList = [];
            foreach ($courseList->intake as $intake) {
                if ($intake->intake_status == 1) {
                    $intakeList[] = $intake->month->id;
                }
            }

            // Fetch all featured items associated with the course, filtering by featured_status = 1
            $featuredList = [];
            foreach ($courseList->featured as $courseFeatured) {
                if ($courseFeatured->featured_status == 1) {
                    $featuredList[] = $courseFeatured->featured->id;
                }
            }

            // Prepare the course details
            $courseListDetail = [
                'id' => $courseList->id,
                'course' => $courseList->course_name,
                'description' => $courseList->course_description,
                'requirement' => $courseList->course_requirement,
                'cost' => $courseList->course_cost,
                'period' => $courseList->course_period,
                'intake' => $intakeList, // Updated to include filtered intakes
                'courseFeatured' => $featuredList, // Updated to include filtered featured items
                'category' => $courseList->category->id,
                'school' => $courseList->school->school_name,
                'schoolID' => $courseList->school_id,
                'qualification' => $courseList->qualification->id,
                'qualification_name' => $courseList->qualification->qualification_name,
                'mode' => $courseList->studyMode->id,
                'logo' => $logo,
                'tag' => $tagList
            ];

            // Return the final response
            return response()->json([
                'success' => true,
                'data' => $courseListDetail
            ]);
        } catch (\Exception $e) {
            // Handle any errors
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

            // Validate request
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'schoolID' => 'required|integer',
                'description' => 'nullable|string|max:5000',
                'requirement' => 'required|string|max:255',
                'cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
                'period' => 'required|string|max:255',
                'intake' => 'required|array',
                'intake.*' => 'integer|between:41,52',
                'courseFeatured' => 'nullable|array',
                'courseFeatured.*' => 'integer',
                'category' => 'required|integer',
                'qualification' => 'required|integer',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
            ]);

            // Check if the course already exists with a different ID
            $checkingCourse = stp_course::where('school_id', $request->schoolID)
                ->where('course_name', $request->name)
                ->where('id', '!=', $request->id)
                ->exists();

            if ($checkingCourse) {
                throw ValidationException::withMessages([
                    "courses" => ['Course already exists in the school']
                ]);
            }

            // Find the course to update
            $course = stp_course::findOrFail($request->id);

            // Handle logo upload
            $imagePath = $course->logo; // Default to current course logo
            if ($request->hasFile('logo')) {
                // Delete old image if it exists
                if ($imagePath && Storage::exists('public/' . $imagePath)) {
                    Storage::delete('public/' . $imagePath);
                }
                // Store new image
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseLogo', $imageName, 'public');
            }

            // Update course details
            $course->update([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requirement,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'course_logo' => $imagePath ?? $course->course_logo,
                'updated_by' => $authUser->id,
                'updated_at' => now()
            ]);

            // Handle intake months
            $existingIntakes = stp_intake::where('course_id', $request->id)
                ->where('intake_status', 1)
                ->pluck('intake_month')
                ->toArray();

            $newIntakes = array_diff($request->intake, $existingIntakes);
            $removeIntakes = array_diff($existingIntakes, $request->intake);
            // Insert new intakes
            foreach ($newIntakes as $intake) {
                stp_intake::updateOrCreate(
                    ['course_id' => $request->id, 'intake_month' => $intake],
                    ['intake_status' => 1, 'created_by' => $authUser->id, 'updated_at' => now()]
                );
            }
            // Deactivate removed intakes
            stp_intake::where('course_id', $request->id)
                ->whereIn('intake_month', $removeIntakes)
                ->update([
                    'intake_status' => 0,
                    'updated_by' => $authUser->id,
                    'updated_at' => now()
                ]);

            // Handle featured courses
            $existingFeatured = stp_featured::where('course_id', $request->id)
                ->pluck('featured_type')
                ->toArray();

            // Check if any featured courses are provided
            if (!empty($request->courseFeatured)) {
                $newFeatured = array_diff($request->courseFeatured, $existingFeatured);
                $existingToKeep = array_intersect($request->courseFeatured, $existingFeatured);

                // Set the featured status to 1 for the existing featured types provided in the request
                stp_featured::where('course_id', $request->id)
                    ->whereIn('featured_type', $existingToKeep)
                    ->update([
                        'featured_status' => 1,
                        'updated_at' => now()
                    ]);

                // Create new featured entries for those not in the database
                foreach ($newFeatured as $featured) {
                    stp_featured::create([
                        'course_id' => $request->id,
                        'featured_type' => $featured,
                        'featured_status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Set the featured status to 0 for all features not included in the request
                $removeFeatured = array_diff($existingFeatured, $request->courseFeatured);
                stp_featured::where('course_id', $request->id)
                    ->whereIn('featured_type', $removeFeatured)
                    ->update([
                        'featured_status' => 0,
                        'updated_at' => now()
                    ]);
            } else {
                // If no featured courses are provided, deactivate all existing ones for the course
                stp_featured::where('course_id', $request->id)
                    ->update([
                        'featured_status' => 0,
                        'updated_at' => now()
                    ]);
            }


            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // Explicitly return 422 for validation errors
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'errors' => $e->getMessage()
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
    public function courseDetailApplicant(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'school_id' => 'integer|required'  // Ensure school_id is required and integer
            ]);

            // Find all courses based on the school_id
            $courses = stp_course::where('school_id', $request->school_id)->get();

            if ($courses->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No courses found for this school'
                ]);
            }

            // Format the data to return
            $courseData = $courses->map(function ($course) {
                return [
                    'id' => $course->id,
                    'school_id' => $course->school_id,
                    'name' => $course->course_name
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $courseData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ]);
        }
    }

    public function addCategory(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:stp_courses_categories,category_name',
                'icon' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Image validationt
                'description' => 'nullable|string|max:5000'
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
                "category_description" => $request->description,
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

    public function editCategory(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Make icon optional
                'description' => 'string|max:5000'
            ]);

            $authUser = Auth::user();

            // Check for duplicate category name
            $checkName = stp_courses_category::where('category_name', $request->name)
                ->where('id', '!=', $request->id)
                ->exists();
            if ($checkName) {
                throw ValidationException::withMessages(['category' => 'Category name has already been used']);
            }

            // Find the category by ID
            $category = stp_courses_category::find($request->id);

            // If a new icon is uploaded, store the image and delete the old one
            if ($request->hasFile('icon')) {
                if (!empty($category->category_icon)) {
                    Storage::delete('public/' . $category->category_icon); // Delete the old icon
                }

                // Store the new icon
                $image = $request->file('icon');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('courseCategoryIcon', $imageName, 'public'); // Store in 'storage/app/public/courseCategoryIcon'
            } else {
                // If no new icon is uploaded, use the existing icon
                $imagePath = $category->category_icon;
            }

            // Prepare the update data
            $updateData = [
                'id' => $request->id,
                'category_name' => $request->name,
                'category_icon' => $imagePath, // This could be the new or existing icon
                'category_description' => $request->description,
                'updated_by' => $authUser->id
            ];

            // Update the category with new data
            $category->update($updateData);

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successful"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Validation',
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
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
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
                'name' => 'required|string|max:255',
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
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
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

    public function subjectDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $subject = stp_subject::find($request->id);
            if (!$subject) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject not found'
                ]);
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $subject->id,
                    'name' => $subject->subject_name,
                    'category' => $subject->subject_category,

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
    public function categoryDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $category = stp_courses_category::find($request->id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ]);
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => $category->category_name,
                    'icon' => $category->category_icon,
                    'description' => $category->category_description
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
                'qualification_id' => 'integer|nullable',
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

    public function applicantDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $applicant = stp_submited_form::find($request->id);
            if (!$applicant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Applicant not found'
                ]);
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $applicant->id,
                    "course_name" => $applicant->course->course_name ?? 'N/A',
                    "courseID" => $applicant->course->id,
                    'email' => $applicant->student->student_email,
                    "institution" => $applicant->course->school->school_name,
                    "schoolID" => $applicant->course->school_id,
                    'name' => $applicant->student->detail->student_detailFirstName . ' ' . $applicant->student->detail->student_detailLastName,
                    "country_code" => $applicant->student->student_countryCode ?? 'N/A',
                    "contact_number" => $applicant->student->student_contactNo ?? 'N/A',
                    'qualification' => $applicant->course->qualification->qualification_name,
                    'student_id' => $applicant->student->id,
                    'feedback' => $applicant->form_feedback,
                    'applied' => $applicant->created_at,
                    'status' => $applicant->form_status
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
                'feedback' => 'string|max:255|nullable',
                'created_at' => 'required|date_format:Y-m-d',
                'status' => 'integer'
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
                'id' => $request->id,
                'courses_id' => $request->courses_id,
                'school_id' => $course->school_id, // Use the school_id from the course
                'created_at' => $request->created_at,
                'form_feedback' => $request->feedback,
                'form_status' => $request->status,
                'updated_by' => Auth::id(),
                'updated_at' => now()
            ]);

            // Re-enable automatic timestamps
            $editApplication->timestamps = true;

            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Applicant Successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // Explicitly return 422 for validation errors
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
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // Explicitly return 422 for validation errors
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
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $e->errors()
                ], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'succcess' => false,
                    'message' => 'Internal Server Error',
                    'errors' => $e->getMessage()
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
                'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Image validation
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
                'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000', // Image validation
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

                    // Convert banner_start and banner_end to datetime-local format
                    $bannerStart = Carbon::parse($banner->banner_start)->format('Y-m-d\TH:i');
                    $bannerEnd = Carbon::parse($banner->banner_end)->format('Y-m-d\TH:i');

                    // Get the featured metadata
                    $featured = $banner->banner ? [
                        'featured_id' => $banner->banner->id ?? '',
                        'core_metaName' => $banner->banner->core_metaName ?? ''
                    ] : null;

                    return [
                        'id' => $banner->id,
                        'name' => $banner->banner_name ?? '',
                        'url' => $banner->banner_url ?? '',
                        'file' => $banner->banner_file ?? '',
                        'banner_duration' => $banner->banner_start . ' - ' . $banner->banner_end,
                        'banner_start' => $bannerStart ?? '', // Formatted start date
                        'banner_end' => $bannerEnd ?? '',     // Formatted end date
                        'featured' => $featured ?? '', // Single featured_id and core_metaName
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

    public function bannerDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);

            $authUser = Auth::user();
            $banner = stp_advertisement_banner::find($request->id);

            // Check if the banner exists
            if (!$banner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banner not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $request->id,
                    'name' => $banner->banner_name,
                    'url' => $banner->banner_url,
                    'file' => $banner->banner_file,
                    'banner_start' => Carbon::parse($banner->banner_start)->format('Y-m-d\TH:i'),
                    'banner_end' => Carbon::parse($banner->banner_end)->format('Y-m-d\TH:i'),
                    'featured_id' => $banner->featured_id
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
    public function addBanner(Request $request)
    {
        try {
            // Validate the incoming request data
            $request->validate([
                'banner_name' => 'required|string|max:255',
                'banner_file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
                'banner_url' => 'required|string|max:255',
                'featured_id' => 'required|array',  // Validate as an array
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
            $bannersCreated = [];
            foreach ($request->featured_id as $featuredId) {
                $banner = stp_advertisement_banner::create([
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
                $bannersCreated[] = $banner; // Collect created banners for potential logging or response
            }
    
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Successfully Added the Banner(s)',
                    'banners' => $bannersCreated // Optionally return the created banners
                ]
            ], 201); // Use 201 for successful resource creation
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // Explicitly return 422 for validation errors
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500); // Use 500 for unexpected server errors
        }
    }
    
    public function editBanner(Request $request)
    {
        try {
            // Validation rules
            $request->validate([
                'id' => 'required|integer',
                'banner_name' => 'required|string|max:255',
                'banner_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10000',
                'banner_url' => 'required|string|max:255',
                'featured_id' => 'required|integer',
                'banner_start' => 'required|date_format:Y-m-d H:i:s',
                'banner_end' => 'required|date_format:Y-m-d H:i:s'
            ]);

            $authUser = Auth::user();
            $adBanner = stp_advertisement_banner::find($request->id);

            if (!$adBanner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banner not found'
                ]);
            }

            // Check if the banner_file is present
            if ($request->hasFile('banner_file')) {
                // If there is an existing file, delete it
                if (!empty($adBanner->banner_file)) {
                    Storage::delete('public/' . $adBanner->banner_file);
                }

                // Handle file upload
                $image = $request->file('banner_file');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('bannerFile', $imageName, 'public');

                // Update the banner file path
                $adBanner->banner_file = $imagePath;
            }

            // Update the other fields, but don't change the banner_file if no new file is uploaded
            $adBanner->banner_name = $request->banner_name;
            $adBanner->banner_url = $request->banner_url;
            $adBanner->banner_start = $request->banner_start;
            $adBanner->banner_end = $request->banner_end;
            $adBanner->featured_id = $request->featured_id;
            $adBanner->updated_by = $authUser->id;
            $adBanner->updated_at = now();

            // Save the changes to the database
            $adBanner->save();

            return response()->json([
                'success' => true,
                'data' => ['message' => "Banner updated successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // Explicitly return 422 for validation errors
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
                ->whereIn('school_status', [1, 2, 3])
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
    public function transcriptCategoryList(Request $request)
    {
        try {
            $categoryList = stp_core_meta::query()
                ->where('core_metaStatus', 1)
                ->whereIn('id', [32, 33, 34, 35, 36, 37])
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
    public function removeSchoolPhoto(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $authUser = Auth::user();
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

    public function featuredRequestList(Request $request)
    {
        try {
            $request->validate([
                'featured_type' => 'integer',
                'status' => 'integer',
                'school_id' => 'integer'
            ]);

            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_featured_request::count() : (int)$request->per_page)
                : 10;

            $featuredRequestList = stp_featured_request::query()
                ->withCount('featuredItems')
                ->when($request->filled('featured_type'), function ($query) use ($request) {
                    $query->where('featured_type', $request->featured_type);
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('request_status', $request->status);
                })
                ->when($request->filled('school_id'), function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->paginate($perPage)
                ->through(function ($featuredRequest) {
                    switch ($featuredRequest['request_status']) {
                        case 0:
                            $status = "Disable";
                            break;
                        case 1:
                            $status = "Approved";
                            break;
                        case 2:
                            $status = "Pending";
                            break;
                        case 3:
                            $status = "Rejected";
                            break;
                        default:
                            $status = "No Data Available";
                    }

                    return [
                        'id' => $featuredRequest->id ?? 'No Data Available',
                        'school_name' => $featuredRequest->school['school_name'] ?? 'No Data Available',
                        'request_name' => $featuredRequest['request_name'] ?? 'No Data Available',
                        'featured_type' => $featuredRequest->featured['core_metaName'] ?? 'No Data Available',
                        'request_quantity' => $featuredRequest['request_quantity'] ?? 'No Data Available',
                        'featured_duration' => $featuredRequest['request_featured_duration'] ?? 'No Data Available',
                        'transaction_proof' => $featuredRequest['request_transaction_prove'] ?? 'No Data Available',
                        'request_type' => $featuredRequest['request_type'] ?? 'No Data Available',
                        'request_status' => $status,
                        'featured_count' => $featuredRequest->featured_items_count ?? 'No Data Available'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $featuredRequestList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function featuredRequestDetail(Request $request)
    {
        try {
            $request->validate([
                'request_id' => 'required|integer',
            ]);

            $requestDetail = stp_featured_request::where('id', $request->request_id)
                ->first();

            if (!$requestDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Featured request not found for this school'
                ], 404);
            }

            // Map request_status to human-readable status
            switch ($requestDetail['request_status']) {
                case 0:
                    $status = "Disable";
                    break;
                case 1:
                    $status = "Approved";
                    break;
                case 2:
                    $status = "Pending";
                    break;
                case 3:
                    $status = "Rejected";
                    break;
                default:
                    $status = "Unknown";
            }

            $detail = [
                'id' => $requestDetail['id'],
                'school_name' => $requestDetail->school['school_name'],
                'request_name' => $requestDetail['request_name'],
                'featured_type' => $requestDetail->featured['core_metaName'],
                'request_quantity' => $requestDetail['request_quantity'],
                'featured_duration' => $requestDetail['request_featured_duration'],
                'transaction_proof' => $requestDetail['request_transaction_prove'],
                'request_status' => $status
            ];

            return response()->json([
                'success' => true,
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getFeaturedList(Request $request)
    {
        try {
            // Validate request parameters
            $request->validate([
                'school_id' => 'nullable|integer'
            ]);

            // Build the query
            $query = stp_featured_request::with(['featuredCourse.courses', 'school', 'featured']);

            // Apply school_id filter if provided
            if ($request->filled('school_id')) {
                $query->where('school_id', $request->school_id);
            }

            // Execute query and transform data
            $featuredRequests = $query->get() // Use get() instead of paginate()
                ->map(function ($request) {
                    $featuredData = $request->featuredCourse;
                    $data = [];

                    // Add course names if request_type is 83 (course)
                    if ($request->request_type == 83) {
                        $data['course_names'] = $featuredData->map(function ($featured) {
                            return [
                                'id' => $featured->courses->id ?? 'No Data Available',
                                'featured_id' => $featured->id ?? 'No Data Available',
                                'request_id' => $featured->request_id ?? 'No Data Available',
                                'name' => $featured->courses->course_name ?? 'No Data Available',
                                'start_date' => $featured->featured_startTime ?? 'No Data Available',
                                'end_date' => $featured->featured_endTime ?? 'No Data Available',
                                'featured_status' => $featured->featured_status ?? 'No Data Available',
                                'request_status' => $featured->featured->request_status ?? 'No Data Available',
                            ];
                        })->filter()->values();
                    }
                    
                    // Add school name if request_type is 84 (school)
                    if ($request->request_type == 84) {
                        $data['school'] = [
                            'id' => $request->school->id ?? 'No Data Available',
                            'featured_id' => $request->id ?? 'No Data Available',
                            'request_id' => $request->id ?? 'No Data Available',
                            'name' => $request->school->school_name ?? 'No Data Available',
                            'start_date' => $request->featured_startTime ?? 'No Data Available',
                            'end_date' => $request->featured_endTime ?? 'No Data Available',
                            'featured_status' => $request->featured_status ?? 'No Data Available',
                            'request_status' => $request->request_status ?? 'No Data Available',
                        ];
                    }

                    return $data;
                });

            return response()->json([
                'success' => true,
                'data' => $featuredRequests
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
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateRequestFeatured(Request $request)
    {
        try {
            $request->validate([
                'request_id' => 'required|integer',
                'action' => 'required|string'
            ]);

            $findFeaturedRequest = stp_featured_request::find($request->request_id);

            if ($request->action == "accept") {
                $status = 1;
                $message = "You had accept the request successfully";
            } else {
                $status = 3;
                $message = "You had reject the request successfully";
            }

            $findFeaturedRequest->update([
                'request_status' => $status
            ]);

            if ($findFeaturedRequest['request_type'] == 84 && $status == 1) {
                $data = [
                    'school_id' => $findFeaturedRequest['school_id'],
                    'featured_type' => $findFeaturedRequest['featured_type'],
                    'featured_startTime' => now(),
                    'featured_endTime' => now()->addDays($findFeaturedRequest['request_featured_duration']),
                    'request_id' =>  $findFeaturedRequest['id']
                ];
                $creteFeaturedSchool = stp_featured::create($data);
            }

            if ($findFeaturedRequest) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => $message
                    ]
                ]);
            };
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }
}
