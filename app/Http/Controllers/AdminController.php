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
use App\Models\stp_tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image as Image;
use Illuminate\Support\Facades\Storage;

// use Dotenv\Exception\ValidationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

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
                'description' => 'string|max:255',
                'cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
                'period' => 'required|string|max:255',
                'intake' => 'required|string|max:255',
                'category' => 'required|integer',
                'qualification' => 'required|integer',
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

            stp_course::create([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requiremnt ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'course_intake' => $request->intake,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'course_requirement' => $request->requirement,
                'created_by' => $authUser->id
            ]);

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
            // $test = stp_course::find(1);
            // return $test->category;
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

    public function editCourse(Request $request)
    {
        try {
            $authUser = Auth::user();
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'schoolID' => 'required|integer',
                'description' => 'string|max:255',
                'cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
                'period' => 'required|string|max:255',
                'intake' => 'required|string|max:255',
                'category' => 'required|integer',
                'qualification' => 'required|integer',
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

            $courses->update([
                'school_id' => $request->schoolID,
                'course_name' => $request->name,
                'course_description' => $request->description ?? null,
                'course_requirement' => $request->requiremnt ?? null,
                'course_cost' => $request->cost,
                'course_period' => $request->period,
                'course_intake' => $request->intake,
                'category_id' => $request->category,
                'qualification_id' => $request->qualification,
                'course_requirement' => $request->requirement,
                'updated_by' => $authUser->id
            ]);
            return response()->json([
                'success' => true,
                'data' => ['message' => "Update Successfully"]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Validaion Error",
                'errors' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => true,
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
                $message = "Successfully Disable";
            } else {
                $status = 1;
                $message = "Successfully Enable";
            }

            $course = stp_course::find($request->id);

            $course->update([
                'course_status' => $status,
                'updated_by' => $authUser->id,
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
                'icon' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048' // Image validationt
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
                'newTag' => 'required|array',
                'courseID' => 'required|integer'
            ]);


            foreach ($request->newTag as $tag) {
                //checking need to create new tag or not
                $checkTag = stp_tag::where('tag_name', 'like', $request->tag)
                    ->where('tag_status', 1)
                    ->exists();

                if ($checkTag) {
                } else {
                    $createNewTag = stp_tag::create([
                        'tag_name' => $tag
                    ]);
                    stp_course_tag::create([
                        'course_id' => $request->courseID,
                        'tag_id' => $createNewTag->id
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
}
