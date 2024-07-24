<?php

namespace App\Http\Controllers;

use App\Models\stp_city;
use Illuminate\Http\Request;
use App\Models\stp_student;

use App\Models\stp_country;
use App\Models\stp_school;
use App\Models\stp_state;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image as Image;
use Illuminate\Support\Facades\Storage;

// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;


class AdminController extends Controller
{
    public function addStudent()
    {
    }

    public function updateStudent()
    {
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
                'postcode' => 'string',
                'ic' => 'integer|min:6|',
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


            $student = stp_student::find(1);
            $studentDetail = $student->detail;


            if ($request->hasFile('image')) {
                if (!empty($student->student_proilePic)) {
                    Storage::delete('public/' . $student->student_proilePic);
                }

                $image = $request->file('image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                // $resizedImage = Image::make($image)->fit(300, 300);

                $imagePath = $image->storeAs('studentProfilePic', $imageName, 'public'); // Store in 'storage/app/public/images'
                $student->student_proilePic = $imagePath; // Save the path to the database
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
                'error' => $e
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

            $user = stp_student::find($request->id);
            if ($request->type == "disable") {
                $data = [
                    "student_status" => 0,
                    "updated_by" => $authUser->id
                ];
                $successMessage = [
                    "success" => true,
                    "data" => ["message" => "successfully remove student"]
                ];
            } else {
                $data = [
                    "student_status" => 1,
                    "updated_by" => $authUser->id
                ];
                $successMessage = [
                    "success" => true,
                    "data" => ["message" => "successfully enable student"]
                ];
            }

            $disableStudent = $user->update($data);
            if ($disableStudent) {
                return response()->json($successMessage, 200);
            }
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
                ->paginate(10)
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
                    }
                    return [
                        'id' => $school->id,
                        'name' => $school->school_name,
                        'category' => $school->institueCategory->core_metaName ?? null,
                        'country' => $school->country->country_name ?? null,
                        'state' => $school->state->state_name ?? null,
                        'city' => $school->city->city_name ?? null,
                        'status' => $status ?? null
                    ];
                });

            return response()->json($schoolList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e
            ]);
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
                'country' => 'required|integer',
                'state' => 'required|integer',
                'city' => 'required|integer',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255|',
                'school_fullDesc' => 'required|string|max:255',
                'school_shortDesc' => 'required|string|max:255',
                'school_address' => 'required|string|max:255',
                'school_website' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048' // Image validationt
            ]);

            $authUser = Auth::user();

            //check email
            $checkingEmail = stp_school::where('school_email', $request->email)->where('school_status', 1)->exists();
            if ($checkingEmail) {
                throw ValidationException::withMessages([
                    'email' => ['email has been used'],
                ]);
            }


            $checkingUser = stp_school::where('school_countryCode', $request->country_code)
                ->where('school_contactNo', $request->contact_number)
                ->where('school_status', 1)
                ->exists();


            if ($checkingUser) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }

            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            // return $imagePath;


            $data = [
                'school_name' => $request->name,
                'school_email' => $request->email,
                'school_countryCode' => $request->country_code,
                'school_contactNo' => $request->contact_number,
                'school_password' => Hash::make($request->password),
                'school_fullDesc' => $request->school_fullDesc,
                'country_id' => $request->country,
                'state_id' => $request->state,
                'city_id' => $request->city,
                'institue_category' => $request->institue_category,
                'school_shortDesc' => $request->school_shortDesc,
                'school_address' => $request->school_address,
                'school_officialWebsite' => $request->school_website,
                'school_logo' => $imagePath ?? null,
                'school_status' => 3,
                'created_by' => $authUser->id
            ];

            stp_school::create($data);
            return response()->json(
                [
                    'success' => true,
                    'data' => ['message' => 'school registered successfully']
                ],
                201
            );
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
                'error' => $e
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
                'institue_category' => $request->institue_category,
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

            if ($request->type == 'disable') {
                $status = 0;
                $message = "successfully disable";
            } else {
                $status = 1;
                $message = "successfully enable";
            }

            $school = stp_school::find($request->id);
            $editingSchoolStatus = $school->update([
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
                "message" => "Internal Sever Error",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function schoolDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            $school = stp_school::find($request->id);
            return response()->json([
                'success' => true,
                // 'data' => $school
                'data' => [
                    'id' => $school->id,
                    'name' => $school->school_name,
                    'email' => $school->school_email,
                    'contactNumber' => $school->school_countryCode . $school->school_contactNo,
                    'fullDescripton' => $school->school_fullDesc,
                    'shortDescription' => $school->school_shortDesc,
                    'address' => $school->school_address,
                    'country' => $school->country->country_name,
                    'state' => $school->state->state_name,
                    'city' => $school->city->city_name,
                    'institueCategory' => $school->institueCategory->core_metaName,
                    'logo' => $school->school_logo,
                    'website' => $school->school_officalWebsite
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
    }
}
