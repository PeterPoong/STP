<?php

namespace App\Http\Controllers;

use App\Models\stp_course;
use App\Models\stp_school;
use App\Models\stp_school_otp;
use App\Models\stp_student;
use App\Models\stp_student_otp;
use App\Models\stp_user_otp;
use App\Services\ServiceFunction;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;

class serviceFunctionController extends Controller
{
    protected $serviceFunction;

    public function __construct(ServiceFunction $serviceFunction)
    {
        $this->serviceFunction = $serviceFunction;
    }

    public function sendingOtp(Request $request)
    {

        try {

            $request->validate([
                'email' => 'required|email',
                'type' => 'required|string'
            ]);


            if ($request->type != 'student' && $request->type != 'school' && $request->type != 'admin') {
                throw ValidationException::withMessages([
                    'type' => 'wrong type',
                ]);
            }

            $emailNotFoundError = [
                'email' => 'Email does not exist'
            ];
            switch ($request->type) {
                case 'student':
                    $findstudent = stp_student::where('student_email', $request->email)
                        ->where('student_status', '!=', 0)
                        ->first();
                    if (!empty($findstudent)) {
                        $this->serviceFunction->generateOtpAndSendEmail($findstudent->id, $request->type, $request->email);
                    } else {
                        throw ValidationException::withMessages($emailNotFoundError);
                    }
                    break;
                case 'school':
                    $findSchool = stp_school::where('school_email', $request->email)
                        ->where('school_status', '!=', 0)
                        ->first();
                    if (!empty($findSchool)) {
                        $this->serviceFunction->generateOtpAndSendEmail($findSchool->id, $request->type, $request->email);
                    } else {
                        throw ValidationException::withMessages($emailNotFoundError);
                    }
                    break;
                case 'admin':
                    $findAdmin = User::where('email', $request->email)
                        ->where('status', '!=', 0)
                        ->first();
                    if (!empty($findAdmin)) {
                        $this->serviceFunction->generateOtpAndSendEmail($findAdmin->id, $request->type, $request->email);
                    } else {
                        throw ValidationException::withMessages($emailNotFoundError);
                    }
            }
            return "OTP had  been successfully sent";
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

    public function validateOtp(Request $request)
    {
        try {

            // $test = stp_user_otp::find(1);
            // return $test->userOtp;
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|integer',
                'type' => 'required|string'
            ]);

            if ($request->type != 'admin' && $request->type != 'student' && $request->type != 'school') {
                throw ValidationException::withMessages([
                    'type' => 'Wrong Type'
                ]);
            }

            $current_time = now()->format('Y-m-d H:i:s');

            switch ($request->type) {
                case 'admin':
                    $getOtp = stp_user_otp::whereHas('userOtp', function ($query) use ($request) {
                        $query->where('email', $request->email);
                    })
                        ->where('otp_expired_time', '>', $current_time)
                        ->where('otp', $request->otp)
                        ->first();
                    break;
                case 'student':
                    $getOtp = stp_student_otp::whereHas('studentOtp', function ($query) use ($request) {
                        $query->where('student_email', $request->email);
                    })
                        ->where('otp_expired_time', '>', $current_time)
                        ->where('otp', $request->otp)
                        ->first();
                    break;
                case 'school':
                    $getOtp = stp_school_otp::whereHas('schoolOtp', function ($query) use ($request) {
                        $query->where('school_email', $request->email);
                    })
                        ->where('otp_expired_time', '>', $current_time)
                        ->where('otp', $request->otp)
                        ->first();
                    break;
            }

            if (empty($getOtp)) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Your OTP either incorrect or expired'
                    ],
                    404
                );
            }

            //check otp status
            if ($getOtp->otp_status == 0) {
                return response()->json([
                    'success' => false,
                    'message' => "OTP had been used"
                ], 404);
            }
            $getOtp->update([
                'otp_status' => 0
            ]);
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => "correct OTP"
                ]
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

    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'newPassword' => 'required|min:8',
                'confirmPassword' => 'required|same:newPassword',
                'type' => 'required|string'
            ]);

            if ($request->type != 'admin' && $request->type != 'student' && $request->type != 'school') {
                throw ValidationException::withMessages([
                    'type' => 'Wrong Type'
                ]);
            }

            switch ($request->type) {
                case 'student':
                    $user = stp_student::where('student_email', $request->email)->first();
                    $password = $user->student_password;
                    $passwordType = "student_password";
                    break;
                case 'school':
                    $user = stp_school::where('school_email', $request->email)->first();
                    $password = $user->school_password;
                    $passwordType = "school_password";

                    break;
                case 'admin':
                    $user = User::where('email', $request->email)->first();
                    $password = $user->password;
                    $passwordType = "password";

                    break;
            }

            $user->update([
                $passwordType => Hash::make($request->newPassword),
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => "Successfully Reset the password"
                ]
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

    public function sendSchoolEmail($courseID, $student)
    {
        $course = stp_course::find($courseID);
        $school = $course->school;


        $sendEmailToSchool = $this->serviceFunction->sendAppliedCourseEmail($school, $course, $student);
        return $sendEmailToSchool;
    }

    public function sendStudentApplicantStatusEmail($form, $status, $feedback)
    {
        try {
            $studentName = $form->student->student_userName;
            $courseName = $form->course->course_name;
            $schoolName = $form->course->school->school_name;
            $studentEmail = $form->student->student_email;
            $sendAcceptanceEmail = $this->serviceFunction->sendStudentEmail($studentName, $courseName, $schoolName, $studentEmail, $status, $feedback);
            return $sendAcceptanceEmail;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendReminder($form, $authUser)
    {
        try {
            $schoolEmail = $form->course->school->school_email;
            $studentName = $authUser->student_userName;
            $courseName = $form->course->course_name;
            $schoolName = $form->course->school->school_name;
            $sendReminder = $this->serviceFunction->sendReminder(
                $schoolEmail,
                $studentName,
                $courseName,
                $schoolName
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }
}