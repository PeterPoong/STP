<?php

namespace App\Services;

use App\Models\stp_school_otp;
use App\Models\stp_student_otp;
use App\Models\stp_user_otp;
use Illuminate\Support\Str;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;

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
}
