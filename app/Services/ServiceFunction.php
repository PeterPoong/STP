<?php

namespace App\Services;

use App\Models\stp_school_otp;
use App\Models\stp_student_otp;
use App\Models\stp_user_otp;
use Illuminate\Support\Str;
use App\Mail\OtpMail;
use App\Mail\SendSchoolEmail;
use App\Mail\SendAcceptanceEmail;
use App\Mail\SendRejectEmail;
use App\Mail\SendReminder;
use App\Mail\SendEnquiryEmail;
use App\Mail\ReplyEnquiryEmail;
use App\Mail\SendInterestedCourseCategoryEmail;
use App\Mail\AdminCourseCategoryInterested;
use App\Mail\SendCustomSchoolApplicationAdmin;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ServiceFunction
{
    public function generateOtpAndSendEmail($id, $type, $email)
    {
        $current_time = now()->setTimezone('Asia/Kuala_Lumpur')->addMinutes(5)->format('Y-m-d H:i:s');
        $otp = rand(100000, 999999);
        switch ($type) {
            case "student":
                $createOtp = stp_student_otp::create([
                    'student_id' => $id,
                    'otp' => $otp,
                    'otp_expired_time' => $current_time
                ]);
                break;
            case "school":
                $createOtp = stp_school_otp::create([
                    'school_id' => $id,
                    'otp' => $otp,
                    'otp_expired_time' => $current_time
                ]);
                break;
            case "admin":
                $createOtp = stp_user_otp::create([
                    'user_id' => $id,
                    'otp' => $otp,
                    'otp_expired_time' => $current_time
                ]);
                break;
        }
        Mail::to($email)->send(new OtpMail($otp));
    }


    public function sendEnquiryEmail($fullName, $email, $contact, $emailSubject, $messageContent)
    {

        Mail::to('admin@studypal.my')->send(new SendEnquiryEmail($emailSubject, $fullName, $email, $contact, $messageContent));
    }

    public function replyEnquiryEmail($subject, $email, $messageContent)
    {
        Mail::to($email)->send(new ReplyEnquiryEmail($subject, $messageContent));
    }

    public function sendAppliedCourseEmail($school, $course, $student, $newApplicantId)
    {
        try {
            $institute_email = $school->school_email;
            $data = [
                'institute_name' => $school->school_name,
                'course_name' => $course->course_name,
                'student_name' => $student->student_userName,
                'student_email' => $student->student_email,
                'student_phone' => $student->student_countryCode . " " . $student->student_contactNo,
                'application_date' => now()->format('Y-m-d H:i:s'),
                'actionUrl' => "https://studypal.my/school/ApplicantDetail/" . $newApplicantId // Concatenate the student ID
            ];

            Mail::to($institute_email)->send(new SendSchoolEmail($data));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function notifyAdminCustomSchoolApplication($school, $course, $student)
    {
        try {
            $personInChargeEmail = $school->person_inChargeEmail;
            $data = [
                'institute_name' => $school->school_name,
                'course_name' => $course->course_name,
                'student_name' => $student->student_userName,
                'student_email' => $student->student_email,
                'student_phone' => $student->student_countryCode . " " . $student->student_contactNo,
                'application_date' => now()->format('Y-m-d H:i:s'),
            ];
            Mail::to($personInChargeEmail)->send(new SendCustomSchoolApplicationAdmin($data));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendStudentEmail($studentName, $courseName, $schoolName, $studentEmail, $status, $feedback)
    {
        try {
            $data = [
                'studentName' => $studentName,
                'courseName' => $courseName,
                'schoolName' => $schoolName,
                'feedback' => $feedback
            ];
            if ($status == 4) {
                Mail::to($studentEmail)->send(new SendAcceptanceEmail($data));
            } else {
                Mail::to($studentEmail)->send(new SendRejectEmail($data));
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendReminder($schoolEmail, $studentName, $courseName, $schoolName, $newApplicantId)
    {
        try {
            $data = [
                'courseName' => $courseName,
                'studentName' => $studentName,
                'schoolName' => $schoolName,
                // 'reviewLink' => "http://192.168.0.70:5173/schoolPortalLogin"
                'reviewLink' => "https://studypal.my/school/ApplicantDetail/" . $newApplicantId // Concatenate the student ID

            ];
            Mail::to($schoolEmail)->send(new SendReminder($data));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendInterestedCourseCategoryEmail($email, $schoolName, $data, $totalCourse)
    {
        Mail::to($email)->send(new SendInterestedCourseCategoryEmail($schoolName, $data, $totalCourse));
    }

    public function adminCourseCategoryInterested($category, $totalNumber, $schoolEmail, $schoolName)
    {
        Mail::to($schoolEmail)->send(new AdminCourseCategoryInterested($category, $totalNumber, $schoolName));
    }
}
