<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;

use Illuminate\Support\Facades\Auth;
use App\Models\stp_student;
use App\Models\stp_student_detail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;

class SocialLoginController extends Controller
{
    // Redirect to Facebook for authentication
    // public function redirectToFacebook()
    // {
    //     return Socialite::driver('facebook')->redirect();

    // }

    // Handle Facebook callback and log the user in
    // public function handleFacebookCallback()
    // {
    //     try {
    //         $facebookUser = Socialite::driver('facebook')->stateless()->user();

    //         $existingUser = stp_student::where('facebook_id', $facebookUser->getId())->first();
    //         if ($existingUser) {
    //             $token = $existingUser->createToken('authToken')->plainTextToken;
    //         } else {
    //             $data = [
    //                 'student_userName' => $facebookUser->getName(),
    //                 'student_email' => $facebookUser->getEmail(),
    //                 'facebook_id' => $facebookUser->getId(),
    //                 'student_countryCode' => '+60',
    //                 'student_contactNo' => '123',
    //                 'user_role' => 4
    //             ];
    //             $newUser = stp_student::create($data);
    //             $userdetail = stp_student_detail::create([
    //                 'student_id' => $newUser->id
    //             ]);
    //             $token = $newUser->createToken('authToken')->plainTextToken;
    //         }

    //         $data = [
    //             'token' => $token,
    //             'user_name' => $facebookUser->getName()
    //         ];
    //         $jsonData = json_encode($data);

    //         // Encrypt the JSON string
    //         $encryptedData = Crypt::encryptString($jsonData);
    //         // session(['token' => $token]);
    //         // dd(session('token'));
    //         return redirect()->intended('http://localhost:5173/FacebookSocialPageRedirectPage?data=' . $encryptedData);
    //     } catch (\Exception $e) {
    //         return redirect('/login')->withErrors('Unable to login using Facebook.');
    //     }
    // }

    public function redirectToFacebook()
    {
        // Store the redirect URL in session
        session(['facebook_redirect' => 'http://localhost:5173/']);

        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            $existingUser = stp_student::where('facebook_id', $facebookUser->getId())->first();
            if ($existingUser) {
                $token = $existingUser->createToken('authToken')->plainTextToken;
            } else {
                $data = [
                    'student_userName' => $facebookUser->getName(),
                    'student_email' => $facebookUser->getEmail(),
                    'facebook_id' => $facebookUser->getId(),
                    'student_countryCode' => '+60',
                    'student_contactNo' => '123',
                    'user_role' => 4
                ];
                $newUser = stp_student::create($data);
                $userdetail = stp_student_detail::create([
                    'student_id' => $newUser->id
                ]);
                $token = $newUser->createToken('authToken')->plainTextToken;
            }

            $data = [
                'token' => $token,
                'user_name' => $facebookUser->getName()
            ];
            $jsonData = json_encode($data);

            // Encrypt the JSON string
            $encryptedData = Crypt::encryptString($jsonData);

            return redirect()->intended('http://localhost:5173/FacebookSocialPageRedirectPage?data=' . $encryptedData);
        } catch (\Exception $e) {
            // On error (including cancellation), check session for the redirect URL
            $redirectUrl = session('facebook_redirect', '/login'); // Default to /login if not set
            session()->forget('facebook_redirect'); // Clear the session after use
            return redirect($redirectUrl)->withErrors('Unable to login using Facebook.');
        }
    }

    public function decryptData(Request $request)
    {
        try {
            $encryptedData = $request->input('encryptedData');
            // Decrypt the data
            $decryptedData = Crypt::decryptString($encryptedData);

            return response()->json([
                'success' => true,
                'data' => $decryptedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Decryption failed'
            ], 400);
        }
    }
}
