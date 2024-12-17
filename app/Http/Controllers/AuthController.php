<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\stp_student;
use App\Models\stp_school;
use App\Models\stp_core_meta;
use App\Models\stp_student_detail;
use App\Models\stp_user_detail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\Else_;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationData;

class AuthController extends Controller
{
    public function test()
    {
        $user = User::find(1);
        $role = stp_core_meta::find(4);

        return response()->json([
            'user_role' => $user->role,
            'user' => $role->users
        ]);
    }

    public function login(Request $request)
    {
        try {

            if ($request->type == 'school') {
                $request->validate([
                    'email' => 'required|email',
                    'password' => 'required',
                    'type' => 'required'
                ]);
                $checkUser = User::where('email', $request->email)->exists();
                if (!$checkUser) {
                    throw ValidationException::withMessages([
                        'email not found',
                    ]);
                }

                if ($request->type == 'school') {
                    if (Auth::attempt($request->only('email', 'password'))) {
                        $user = Auth::user();
                        $token = $user->createToken('authToken')->plainTextToken;
                        return response()->json([
                            'true' => true,
                            'data' => [
                                'user' => $user,
                                'token' => $token
                            ]
                        ], 200);
                    } else {
                        return response()->json([
                            'true' => false,
                            'message' => 'Invalid credentials',
                        ], 401);
                    }
                }
            } else {
                $request->validate([

                    'password' => 'required',
                    'type' => 'required',
                    'country_code' => 'required',
                    'contact_number' => 'required|numeric|digits_between:1,15'
                ]);
                $checkUser = User::where('country_code', $request->country_code)
                    ->where('contact_no', $request->contact_number)
                    ->exists();
                if (!$checkUser) {
                    throw ValidationException::withMessages([
                        'contact not found',
                    ]);
                }

                $user = User::where('country_code', $request->country_code)
                    ->where('contact_no', $request->contact_number)
                    ->first();

                if (!$user || !Hash::check($request->password, $user->password)) {
                    throw ValidationException::withMessages([
                        'credentials' => ['The provided credentials are incorrect.'],
                    ]);
                }
                Auth::login($user);
                $token = $user->createToken('authToken')->plainTextToken;

                return response()->json([
                    'true' => true,
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                    ]
                ], 200);
            }
        } catch (\Throwable $e) {
            // Handle any exceptions that might occur
            return response()->json([
                'true' => false,
                'message' => $e->getMessage(),
                'error' => '',
            ], 500);
        }
    }

    public function adminLogin(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15'
            ]);
            $checkUser = User::where('country_code', $request->country_code)
                ->where('contact_no', $request->contact_number)
                ->exists();
            if (!$checkUser) {
                throw ValidationException::withMessages([
                    'contact not found',
                ]);
            }

            $user = User::where('country_code', $request->country_code)
                ->where('contact_no', $request->contact_number)
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'credentials' => ['The provided credentials are incorrect.'],
                ]);
            }
            Auth::login($user);
            $token = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'true' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ]
            ], 200);
        } catch (\Throwable $e) {
            // Handle any exceptions that might occur
            return response()->json([
                'true' => false,
                'message' => $e->getMessage(),
                'error' => '',
            ], 500);
        }
    }

    public function studentLogin(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15'
            ]);
            $checkUser = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->exists();
            if (!$checkUser) {
                throw ValidationException::withMessages([
                    'contact not found',
                ]);
            }

            $user = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->first();


            if (!$user || !Hash::check($request->password, $user->student_password)) {
                throw ValidationException::withMessages([
                    'credentials' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Auth::login($user);


            $token = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'true' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ]
            ], 200);
        } catch (\Throwable $e) {
            // Handle any exceptions that might occur
            return response()->json([
                'true' => false,
                'message' => $e->getMessage(),
                'error' => '',
            ], 500);
        }
    }

    public function schoolLogin(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required',
                'email' => 'required|string|email',

            ]);
            $checkUser = stp_school::where('school_email', $request->email)->exists();
            if (!$checkUser) {
                throw ValidationException::withMessages([
                    'email not found',
                ]);
            }

            $user = stp_school::where('school_email', $request->email)->first();


            if (!$user || !Hash::check($request->password, $user->school_password)) {
                throw ValidationException::withMessages([
                    'credentials' => ['The provided credentials are incorrect.'],
                ]);
            }

            switch ($user->school_status) {
                case 0:
                    throw ValidationException::withMessages([
                        'account' => ['Account had been disable. Please contact our support'],
                    ]);
                    break;
                case 2:
                    throw ValidationException::withMessages([
                        'account' => ['Account still in pending waiting for approval from admin'],
                    ]);
            }


            $token = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'true' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ]
            ], 200);
        } catch (\Throwable $e) {
            // Handle any exceptions that might occur
            return response()->json([
                'true' => false,
                'message' => $e->getMessage(),
                'error' => '',
            ], 500);
        }
    }

    public function schoolRegister(Request $request)
    {

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'country_code' => 'required',
                'country' => 'integer',
                'state' => 'integer',
                'city' => 'integer',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
                'school_fullDesc' => 'string|max:255',
                'school_shortDesc' => 'string|max:255',
                'school_address' => 'required|string|max:255',
                // 'school_website' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Image validationt
                'person_in_charge_email' => 'required|email',
                'person_in_charge_name' => 'required|string|max:255',
                'person_in_charge_contact' => 'required|string|max:255'
            ]);

            $errors = [];
            //check institute name
            $checkName = stp_school::where('school_name', $request->name)->exists();
            if ($checkName) {
                $errors['name'] = ['The institute name has already been taken. Please choose a different name.'];
            }

            //check email
            $checkingEmail = stp_school::where('school_email', $request->email)->exists();
            if ($checkingEmail) {
                $errors['email'] = ['An account with this email already exists. Try logging in instead.'];
            }

            //check user
            $checkingUser = stp_school::where('school_countryCode', $request->country_code)
                ->where('school_contactNo', $request->contact_number)
                ->exists();
            if ($checkingUser) {
                $errors['contact_no'] = ['An account with this contact number already exists. Try logging in instead.'];
            }

            //get iframe and map link
            $placeName =  $request->name; // E.g., 'Eiffel Tower, Paris, France'

            // Encode the place name to ensure it's URL safe
            $encodedPlace = urlencode($placeName);

            $embedUrl = "https://www.google.com/maps?q={$placeName}&output=embed";

            // Generate the Google Maps link
            $googleMapsLink = "https://www.google.com/maps/search/?api=1&query={$encodedPlace}";

            // Generate the iframe embed code
            $iframeCode = "<iframe src='{$embedUrl}' width='600' height='450' style='border:0;' allowfullscreen='' loading='lazy'></iframe>";

            // Return both the link and iframe in JSON response



            // //check person in charge email
            // $checkPersonInChargeEmail = stp_school::where('person_inChargeEmail', $request->person_in_charge_email)->exists();
            // if ($checkPersonInChargeEmail) {
            //     $errors['person_in_charge_email'] = ['A person in charge  already exists. Try logging in instead.'];
            // }

            // //check person in charge contact
            // $checkPersonInChargeContact = stp_school::where('person_inChargeNumber', $request->person_in_charge_contact)->exists();
            // if ($checkPersonInChargeContact) {
            //     $errors['person_in_charge_contact'] = ['A person in charge with this contact number already exists. Try logging in instead.'];
            // }

            if (count($errors) > 0) {
                throw ValidationException::withMessages($errors);
            }

            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('schoolLogo', $imageName, 'public'); // Store in 'storage/app/public/images'
            }

            $data = [
                'school_name' => $request->name,
                'school_email' => $request->email,
                'school_countryCode' => $request->country_code,
                'school_contactNo' => $request->contact_number,
                'school_password' => Hash::make($request->password),
                'school_fullDesc' => $request->school_fullDesc ?? null,
                'country_id' => $request->country ?? null,
                'state_id' => $request->state ?? null,
                'city_id' => $request->city ?? null,
                'institue_category' => $request->institue_category ?? null,
                'school_shortDesc' => $request->school_shortDesc ?? null,
                'school_address' => $request->school_address,
                'school_location' => $iframeCode,
                'school_google_map_location' => $googleMapsLink,
                // 'school_officalWebsite' => $request->school_website,
                'person_inChargeName' => $request->person_in_charge_name,
                'person_inChargeNumber' => $request->person_in_charge_contact,
                'person_inChargeEmail' => $request->person_in_charge_email,
                'account_type' => 64,
                'school_logo' => " schoolLogo/profileDefaultIcon.png",
                'school_status' => 2,

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

    public function studentRegister(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
                'ic' => 'required|string|unique:stp_students,student_icNumber'
            ]);

            $checkingUser = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->exists();

            $checkingUserEmail = stp_student::where('student_email', $request->email)
                // ->where('student_status', 1)
                ->exists();





            if ($checkingUser) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }

            if ($checkingUserEmail) {
                throw ValidationException::withMessages([
                    'email' => ['Email has been used'],
                ]);
            }

            $checkEmailWithSocialLogin = stp_student::where('student_email', $request->email)
                ->where('student_password', null)
                ->first();

            if ($checkEmailWithSocialLogin) {
                $data = [
                    'student_userName' => $request->name,
                    'student_countryCode' => $request->country_code,
                    'student_contactNo' => $request->contact_number,
                    'student_password' => Hash::make($request->password),
                    'student_icNumber' => $request->ic,
                ];
                $checkEmailWithSocialLogin->update($data);
            } else {
                $data = [
                    'student_userName' => $request->name,
                    'student_email' => $request->email,
                    'student_countryCode' => $request->country_code,
                    'student_contactNo' => $request->contact_number,
                    'student_password' => Hash::make($request->password),
                    'student_icNumber' => $request->ic,
                    'user_role' => 4
                ];
                $newUser = stp_student::create($data);
                $userdetail = stp_student_detail::create([
                    'student_id' => $newUser->id
                ]);
            }

            return response()->json(
                [
                    'success' => true,
                    'data' => ['message' => 'User registered successfully']
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

    public function adminRegister(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:stp_users,email',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
            ]);

            $checkingUser = User::where('country_code', $request->country_code)
                ->where('contact_no', $request->contact_number)
                ->exists();

            if ($checkingUser) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }
            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'country_code' => $request->country_code,
                'contact_no' => $request->contact_number,
                'password' => Hash::make($request->password),
                'user_role' => 1
            ];
            $newUser = User::create($data);
            $userdetail = stp_user_detail::create([
                'user_id' => $newUser->id
            ]);
            return response()->json(
                [
                    'success' => true,
                    'data' => ['message' => 'User registered successfully']
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

    public function testing()
    {
        return 'testing api';
    }
}
