<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\stp_student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
                'country' => 'string',
                'city' => 'string',
                'state' => 'string',
                'postcode' => 'string',
                'ic' => 'integer|min:6|unique:stp_students,student_icNumber',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048' // Image validationt
            ]);


            $checkingUserContact = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->where('id', '!=', $request->id)
                ->exists();

            $student = stp_student::find(1);
            $studentDetail = $student->detail;



            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                // return $imageName;
                $imagePath = $image->storeAs('studentProfilePic', $imageName, 'public'); // Store in 'storage/app/public/images'
                $student->student_proilePic = $imagePath; // Save the path to the database
            }


            if ($checkingUserContact) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
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
            ]);

            $updatingDetail = $studentDetail->update([
                "student_detailFirstName" => $request->first_name ?? "",
                "student_detailLastName" => $request->last_name ?? "",
                "student_detailAddress" => $request->address ?? "",
                "student_detailCountry" => $request->country ?? "",
                "student_detailCity" => $request->city ?? "",
                "student_detailState" => $request->state ?? "",
                "student_detailPostcode" => $request->postcode ?? "",

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
}
