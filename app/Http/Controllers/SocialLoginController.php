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
                $findExistingOfEmail = stp_student::where('student_email', $facebookUser->getEmail())->first();
                if ($findExistingOfEmail) {
                    $data = [
                        'facebook_id' => $facebookUser->getId(),
                    ];
                    $findExistingOfEmail->update($data);
                    $token = $findExistingOfEmail->createToken('authToken')->plainTextToken;
                    $user_id = $findExistingOfEmail->id;
                } else {
                    $data = [
                        'student_userName' => $facebookUser->getName(),
                        'student_email' => $facebookUser->getEmail(),
                        'facebook_id' => $facebookUser->getId(),
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
                if ($existingUser->student_countryCode === null && $existingUser->student_contactNo === null) {
                    $contact = false;
                } else {
                    $contact = true;
                }
            } else {
                $checkExistingOfEmail = stp_student::where('student_email', $googleUser->getEmail())->first();
                if ($checkExistingOfEmail) {
                    $data = [
                        'google_id' => $googleUser->getId(),
                    ];
                    $checkExistingOfEmail->update($data);
                    $token = $checkExistingOfEmail->createToken('authToken')->plainTextToken;
                    $user_id = $checkExistingOfEmail->id;
                    if ($checkExistingOfEmail->student_countryCode === null && $checkExistingOfEmail->student_contactNo === null) {
                        $contact = false;
                    } else {
                        $contact = true;
                    }


                    // if ($checkExistingOfEmail->student_countryCode == null && $checkExistingOfEmail->student_contactNo == null) {
                    //     $contact = false;
                    // } else {
                    //     $contact = true;
                    // }
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
                    $contact = false;
                }
            }

            // Prepare data for the frontend
            $data = [
                'token' => $token,
                'id' => $user_id,
                'user_name' => $googleUser->getName(),
                'contact' => $contact

            ];
            $jsonData = json_encode($data);


            // Encrypt the JSON string
            $encryptedData = Crypt::encryptString($jsonData);

            // Redirect to your frontend page with encrypted data
            // $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'http://localhost:5173/'));
            $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'URL'));

            session()->forget('facebook_redirect'); // Clear the session after use
            // return redirect()->intended('https://www.youtube.com/');
            return redirect()->intended($redirectUrl . 'FacebookSocialPageRedirectPage?data=' . $encryptedData);
        } catch (\Exception $e) {
            // Handle cases like cancellation or errors during login
            // $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'http://localhost:5173/'));

            $redirectUrl = session('facebook_redirect', env('FRONTEND_REDIRECT_URL', 'URL'));
            // $redirectUrl = session('', 'http://localhost:5174/SocialContactPage');

            session()->forget('facebook_redirect'); // Clear the session after use

            // Redirect to your desired frontend page with an error message
            return redirect($redirectUrl)->withErrors('Unable to login using Facebook.');
        }
    }

    public function updateContact(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'country_id' => 'required|string',
                'contact_number' => 'required|string'
            ]);

            $validateContact = stp_student::where('student_countryCode', $request->country_id)
                ->where('student_contactNo', $request->contact_number)
                ->where('student_status', 1)
                ->first();

            // return $validateContact;

            if ($validateContact) {
                throw new Exception("Contact already exist");
            }
            $findUser = stp_student::find($request->id);
            $updateData = [
                'student_countryCode' => $request->country_id,
                'student_contactNo' => $request->contact_number
            ];

            $findUser->update($updateData);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => "Successfully update contact"
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }
}
