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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\Else_;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationData;
use App\Services\ServiceFunction;
use Carbon\Carbon;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    protected $serviceFunction;

    public function __construct(ServiceFunction $serviceFunction)
    {
        $this->serviceFunction = $serviceFunction;
    }
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
                    'contact' => ['The provided contact number is incorrect.'],
                ]);
            }

            $user = stp_student::select('stp_students.*', 'stp_student_details.state_id')
                ->leftJoin('stp_student_details', 'stp_students.id', '=', 'stp_student_details.student_id')
                ->where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'contact' => ['The provided contact number is incorrect.'],
                ]);
            }

            if (!Hash::check($request->password, $user->student_password)) {
                throw ValidationException::withMessages([
                    'credentials' => ['The provided password is incorrect.'],
                ]);
            }

            // // Check if OTP is verified (only allow login for verified users)
            // if ($user->otp_status != 1) {
            //     throw ValidationException::withMessages([
            //         'otp' => ['Please verify your phone number with OTP before logging in.'],
            //     ]);
            // }

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
                'school_countryCode' => 'required|string',
                'school_contactNo' => 'required|string',
                'password' => 'required',
            ]);

            $user = stp_school::where('school_countryCode', $request->school_countryCode)
                ->where('school_contactNo', $request->school_contactNo)
                ->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'school_contactNo' => ['The provided country code or contact number is incorrect.'],
                ]);
            }

            if (!Hash::check($request->password, $user->school_password)) {
                throw ValidationException::withMessages([
                    'password' => ['The provided password is incorrect.'],
                ]);
            }

            switch ($user->school_status) {
                case 0:
                    throw ValidationException::withMessages([
                        'account' => ['Account had been disabled. Please contact our support.'],
                    ]);
                    break;
                case 2:
                    throw ValidationException::withMessages([
                        'account' => ['Account still pending approval from admin.'],
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
                'password' => ['required', Password::defaults()],
                'confirm_password' => ['required', 'string', 'same:password'],
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

            // Generate unique slug
            $schoolSlug = $this->generateSchoolSlug($request->name);

            $passwordSecurityStatus = app(\App\Services\PasswordSecurityService::class)->assess($request->password);
            $data = [
                'school_name' => $request->name,
                'school_slug' => $schoolSlug,
                'school_email' => $request->email,
                'school_countryCode' => $request->country_code,
                'school_contactNo' => $request->contact_number,
                'school_password' => Hash::make($request->password),
                'password_security_status' => $passwordSecurityStatus,
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
                    'data' => ['message' => 'school registered successfully'],
                    'password_security' => app(\App\Services\PasswordSecurityService::class)->response($passwordSecurityStatus),
                ],
                201
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            return response()->json([
                'success' => false,
                'message' => 'Database Error: Duplicate entry detected',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ], 500);
        }
    }

    public function studentRegister(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => ['required', Password::defaults()],
                'confirm_password' => ['required', 'string', 'same:password'],
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255',
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

            // // Generate OTP
            // $otp = rand(100000, 999999);
            // $otpExpiredTime = now()->setTimezone('Asia/Kuala_Lumpur')->addMinutes(5)->format('Y-m-d H:i:s');
            
            $passwordSecurityStatus = app(\App\Services\PasswordSecurityService::class)->assess($request->password);
            $checkEmailWithSocialLogin = stp_student::where('student_email', $request->email)
                ->whereNull('student_password')
                ->first();

            if ($checkEmailWithSocialLogin) {
                $data = [
                    'student_userName' => $request->name,
                    'student_countryCode' => $request->country_code,
                    'student_contactNo' => $request->contact_number,
                    'student_password' => Hash::make($request->password),
                    'password_security_status' => $passwordSecurityStatus,
                    // 'otp' => $otp,
                    // 'otp_expired_time' => $otpExpiredTime,
                    // 'otp_status' => 1 // Verified by default
                ];
                $checkEmailWithSocialLogin->update($data);
                $student = $checkEmailWithSocialLogin;
            } else {
                $data = [
                    'student_userName' => $request->name,
                    'student_email' => $request->email,
                    'student_countryCode' => $request->country_code,
                    'student_contactNo' => $request->contact_number,
                    'student_password' => Hash::make($request->password),
                    'password_security_status' => $passwordSecurityStatus,
                    'user_role' => 4,
                    // 'otp' => $otp,
                    // 'otp_expired_time' => $otpExpiredTime,
                    // 'otp_status' => 1 // Verified by default
                ];
                $student = stp_student::create($data);
                $userdetail = stp_student_detail::create([
                    'student_id' => $student->id
                ]);
            }

            // // Send OTP via Email
            // $this->serviceFunction->sendOtpEmail($request->email, $otp, 'registration');

            return response()->json(
                [
                    'success' => true,
                    'data' => [
                        'message' => 'Registration successful.',
                        'student_id' => $student->id,
                        'email' => $request->email
                    ],
                    'password_security' => app(\App\Services\PasswordSecurityService::class)->response($passwordSecurityStatus),
                ],
                201
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Student Registration Error: ' . $e->getMessage());
            Log::error('Stack Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function verifyStudentOtp(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer|exists:stp_students,id',
                'otp' => 'required|integer|digits:6'
            ]);

            $student = stp_student::find($request->student_id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Check if OTP is already verified
            if ($student->otp_status == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP already verified'
                ], 400);
            }

            // Check if OTP matches
            if ($student->otp != $request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP. Please try again.'
                ], 400);
            }

            // Check if OTP is expired
            $currentTime = now()->setTimezone('Asia/Kuala_Lumpur');
            $expiredTime = Carbon::parse($student->otp_expired_time)->setTimezone('Asia/Kuala_Lumpur');
            
            if ($currentTime->gt($expiredTime)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            // OTP is valid - mark as verified
            $student->otp_status = 1;
            $student->save();

            // Create Sanctum token (user is now logged in)
            $token = $student->createToken('authToken')->plainTextToken;

            // Send welcome email after successful OTP verification and login is complete
            // Email is sent synchronously after token creation to ensure it's sent
            try {
                $emailSent = $this->serviceFunction->sendWelcomeEmail($student->student_userName, $student->student_email);
                if ($emailSent) {
                    Log::info("Welcome email sent successfully after OTP verification - Student ID: {$student->id}, Email: {$student->student_email}");
                } else {
                    Log::warning("Welcome email sending returned false - Student ID: {$student->id}, Email: {$student->student_email}");
                }
            } catch (\Exception $e) {
                // Log error but don't fail the verification process
                Log::error("Failed to send welcome email during OTP verification - Student ID: {$student->id}, Error: " . $e->getMessage());
                Log::error("Exception trace: " . $e->getTraceAsString());
            }

            // Get user with state_id
            $user = stp_student::select('stp_students.*', 'stp_student_details.state_id')
                ->leftJoin('stp_student_details', 'stp_students.id', '=', 'stp_student_details.student_id')
                ->where('stp_students.id', $student->id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'message' => 'OTP verified successfully. Welcome email has been sent to your email address.'
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resendStudentOtp(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer|exists:stp_students,id'
            ]);

            $student = stp_student::find($request->student_id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Check if already verified
            if ($student->otp_status == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student already verified'
                ], 400);
            }

            // Generate new OTP
            $otp = rand(100000, 999999);
            $otpExpiredTime = now()->setTimezone('Asia/Kuala_Lumpur')->addMinutes(5)->format('Y-m-d H:i:s');

            $student->otp = $otp;
            $student->otp_expired_time = $otpExpiredTime;
            $student->save();

            // Send OTP via Email
            $this->serviceFunction->sendOtpEmail($student->student_email, $otp, 'registration');

            // Mask email for response
            $emailParts = explode('@', $student->student_email);
            $maskedEmail = substr($emailParts[0], 0, 3) . '***@' . $emailParts[1];

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'OTP resent successfully. Please check your email.',
                    'email' => $maskedEmail
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adminRegister(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:stp_users,email',
                'password' => ['required', Password::defaults()],
                'confirm_password' => ['required', 'string', 'same:password'],
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
            $passwordSecurityStatus = app(\App\Services\PasswordSecurityService::class)->assess($request->password);
            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'country_code' => $request->country_code,
                'contact_no' => $request->contact_number,
                'password' => Hash::make($request->password),
                'password_security_status' => $passwordSecurityStatus,
                'user_role' => 1
            ];
            $newUser = User::create($data);
            $userdetail = stp_user_detail::create([
                'user_id' => $newUser->id
            ]);
            return response()->json(
                [
                    'success' => true,
                    'data' => ['message' => 'User registered successfully'],
                    'password_security' => app(\App\Services\PasswordSecurityService::class)->response($passwordSecurityStatus),
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
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function studentInfoValidation(Request $request)
    {
        try {
            $request->validate([
                'studentEmail' => 'required|string',
                'studentIc' => 'required|string',
            ]);

            // Check if email is already in use
            $checkingUserEmail = stp_student::where('student_email', $request->studentEmail)
                ->exists();

            // Check if IC number is already in use and not null
            $checkingUserIC = stp_student::where('student_icNumber', $request->studentIc)
                ->whereNotNull('student_icNumber')
                ->exists();

            // Throw validation errors if the data exists
            $errors = [];

            if ($checkingUserEmail) {
                $errors['email'] = ['Email has been used'];
            }

            if ($checkingUserIC) {
                $errors['ic'] = ['IC has been used'];
            }

            // If there are errors, return them in the expected format
            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            return response()->json([
                'success' => true
            ]);
        } catch (ValidationException $e) {
            // Return the validation errors in the format frontend expects
            return response()->json([
                'success' => false,
                'errors' => $e->errors() // This will return the validation errors in the correct format
            ]);
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e->getMessage()
            ]);
        }
    }

    public function validateContactNum(Request $request)
    {

        try {
            $request->validate([
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
            ]);


            $checkingUser = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->whereNotNull('student_countryCode') // Ensure student_countryCode is not null
                ->whereNotNull('student_contactNo')  // Ensure student_contactNo is not null
                ->exists();



            if ($checkingUser) {
                return response()->json([
                    'success' => false,
                    'message' => "Internal Server Error",
                    'error' => "Contact Already been used"
                ]);
                // throw ValidationException::withMessages(['Contact already been used']);
            }



            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Internal Server Error",
                'error' => $e
            ]);
        }
    }


    public function testing()
    {
        return 'testing api';
    }

    /**
     * Generate a URL-friendly slug from school name.
     * Ensures uniqueness by appending a counter if needed.
     */
    private function generateSchoolSlug($schoolName, $schoolId = null)
    {
        $baseSlug = Str::slug($schoolName);
        $slug = $baseSlug;
        $counter = 1;

        // Check if slug exists and ensure it's unique for this school
        while (true) {
            $query = stp_school::where('school_slug', $slug);
            
            // If updating, exclude current school from check
            if ($schoolId) {
                $query->where('id', '!=', $schoolId);
            }
            
            if (!$query->exists()) {
                break;
            }
            
            // Append counter if slug is taken
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
