<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;

use Illuminate\Support\Facades\Auth;
use App\Models\stp_student;
use App\Models\stp_student_detail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class SocialLoginController extends Controller
{
    // Redirect to Facebook for authentication
    // public function redirectToFacebook()
    // {
    //     return Socialite::driver('facebook')->redirect();
    // }

    // // Handle Facebook callback and log the user in
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
    //         // return redirect()->intended('http://localhost:5173/FacebookSocialPageRedirectPage?data=' . $encryptedData);\
    //         return redirect()->intended('https://backendstudypal.studypal.my/FacebookSocialPageRedirectPage?data=' . $encryptedData);
    //     } catch (\Exception $e) {
    //         return redirect('/login')->withErrors('Unable to login using Facebook.');
    //     }
    // }

    // public function redirectToFacebook()
    // {
    //     // Store the redirect URL in session
    //     session(['facebook_redirect' => 'http://localhost:5173/']);

    //     return Socialite::driver('facebook')->redirect();
    // }

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

    //         return redirect()->intended('http://localhost:5173/FacebookSocialPageRedirectPage?data=' . $encryptedData);
    //     } catch (\Exception $e) {
    //         // On error (including cancellation), check session for the redirect URL
    //         $redirectUrl = session('facebook_redirect', '/login'); // Default to /login if not set
    //         session()->forget('facebook_redirect'); // Clear the session after use
    //         return redirect($redirectUrl)->withErrors('Unable to login using Facebook.');
    //     }
    // }

    //correct 
    // public function redirectToFacebook()
    // {
    //     // Store the redirect URL in session
    //     session(['facebook_redirect' => 'http://localhost:5173/']);

    //     return Socialite::driver('facebook')->redirect();
    // }

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

    //         return redirect()->intended('http://localhost:5173/FacebookSocialPageRedirectPage?data=' . $encryptedData);
    //     } catch (\Exception $e) {
    //         // On error (including cancellation), check session for the redirect URL
    //         $redirectUrl = session('facebook_redirect', '/login'); // Default to /login if not set
    //         session()->forget('facebook_redirect'); // Clear the session after use
    //         return redirect($redirectUrl)->withErrors('Unable to login using Facebook.');
    //     }
    // }

    public function redirectToFacebook()
    {
        // Store the redirect URL in session (use env variable or hardcoded URL)
        // session(['facebook_redirect' => env('FRONTEND_REDIRECT_URL', 'http://localhost:5173/')]);
        session(['facebook_redirect' => env('FRONTEND_REDIRECT_URL', 'URL')]);
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            // Get the user from Facebook
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            // Check if the user already exists in your database
            $existingUser = stp_student::where('facebook_id', $facebookUser->getId())->first();
            if ($existingUser) {
                $token = $existingUser->createToken('authToken')->plainTextToken;
                $user_id = $existingUser->id;
            } else {
                // Create a new user if not found
                $data = [
                    'student_userName' => $facebookUser->getName(),
                    'student_email' => $facebookUser->getEmail(),
                    'facebook_id' => $facebookUser->getId(),
                    'student_countryCode' => '+60',
                    'student_contactNo' => '123',
                    'user_role' => 4
                ];
                $newUser = stp_student::create($data);
                stp_student_detail::create(['student_id' => $newUser->id]);
                $token = $newUser->createToken('authToken')->plainTextToken;
                $user_id = $newUser->id;
            }

            // Prepare data for the frontend
            $data = [
                'token' => $token,
                'id' => $user_id,
                'user_name' => $facebookUser->getName()
            ];
            $jsonData = json_encode($data);

            // Encrypt the JSON string
            $encryptedData = Crypt::encryptString($jsonData);

            // Redirect to your frontend page with encrypted data
            // $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'http://localhost:5173/'));
            $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'URL'));

            session()->forget('facebook_redirect'); // Clear the session after use
            return redirect()->intended($redirectUrl . 'FacebookSocialPageRedirectPage?data=' . $encryptedData);
        } catch (\Exception $e) {
            // Handle cases like cancellation or errors during login
            // $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'http://localhost:5173/'));
            $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'URL'));

            session()->forget('facebook_redirect'); // Clear the session after use

            // Redirect to your desired frontend page with an error message
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

    public function deleteFacebookData(Request $request)
    {
        $signedRequest = $request->input('signed_request');
        $data = $this->parseSignedRequest($signedRequest);

        if (!$data || !isset($data['user_id'])) {
            return response()->json(['error' => 'Invalid signed request'], 400);
        }

        $userId = $data['user_id'];

        // Start the data deletion process for the student
        $student = stp_student::where('facebook_id', $userId)->first();

        $updateData = [
            'student_status' => 0
        ];
        $student->update($updateData);

        // Generate a unique confirmation code and status URL
        $confirmationCode = uniqid();
        $statusUrl = url('/deletion?code=' . $confirmationCode);

        // Log the deletion request for record-keeping (optional)
        Log::info("Facebook data deletion requested for user: {$userId}, confirmation code: {$confirmationCode}");

        return response()->json([
            'url' => $statusUrl,
            'confirmation_code' => $confirmationCode,
        ]);
    }
    private function parseSignedRequest($signedRequest)
    {
        list($encodedSig, $payload) = explode('.', $signedRequest, 2);
        $secret = env('FACEBOOK_APP_SECRET'); // Ensure your app secret is set in the .env file

        $sig = $this->base64UrlDecode($encodedSig);
        $data = json_decode($this->base64UrlDecode($payload), true);

        $expectedSig = hash_hmac('sha256', $payload, $secret, true);
        if ($sig !== $expectedSig) {
            Log::error('Invalid signed request signature.');
            return null;
        }

        return $data;
    }

    private function base64UrlDecode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }

    public function googlePage()
    {
        session(['google_redirect' => env('FRONTEND_REDIRECT_URL', 'URL')]);
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {

        try {
            // Get the user from Facebook
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Check if the user already exists in your database
            $existingUser = stp_student::where('google_id', $googleUser->getId())->first();
            if ($existingUser) {
                $token = $existingUser->createToken('authToken')->plainTextToken;
                $user_id = $existingUser->id;
            } else {
                $checkExistingOfEmail = stp_student::where('student_email', $googleUser->getEmail())->first();
                if ($checkExistingOfEmail) {
                    $data = [
                        'google_id' => $googleUser->getId(),
                    ];
                    $checkExistingOfEmail->update($data);
                    $token = $checkExistingOfEmail->createToken('authToken')->plainTextToken;
                    $user_id = $checkExistingOfEmail->id;
                } else {
                    $data = [
                        'student_userName' => $googleUser->getName(),
                        'student_email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'user_role' => 4
                    ];
                    $newUser = stp_student::create($data);
                    stp_student_detail::create(['student_id' => $newUser->id]);
                    $token = $newUser->createToken('authToken')->plainTextToken;
                    $user_id = $newUser->id;
                }
            }

            // Prepare data for the frontend
            $data = [
                'token' => $token,
                'id' => $user_id,
                'user_name' => $googleUser->getName()
            ];
            $jsonData = json_encode($data);

            // Encrypt the JSON string
            $encryptedData = Crypt::encryptString($jsonData);

            // Redirect to your frontend page with encrypted data
            // $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'http://localhost:5173/'));
            $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'URL'));

            session()->forget('facebook_redirect'); // Clear the session after use
            return redirect()->intended($redirectUrl . 'FacebookSocialPageRedirectPage?data=' . $encryptedData);
        } catch (\Exception $e) {
            // Handle cases like cancellation or errors during login
            // $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'http://localhost:5173/'));
            $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'URL'));

            session()->forget('facebook_redirect'); // Clear the session after use

            // Redirect to your desired frontend page with an error message
            return redirect($redirectUrl)->withErrors('Unable to login using Facebook.');
        }
    }
}
