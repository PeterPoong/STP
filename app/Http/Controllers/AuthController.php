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
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255|unique:stp_schools,school_email',
            ]);
            $checkingUser = stp_school::where('school_countryCode', $request->country_code)
                ->where('school_contactNo', $request->contact_number)
                ->exists();


            if ($checkingUser) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }

            $data = [
                'school_name' => $request->name,
                'school_email' => $request->email,
                'school_countryCode' => $request->country_code,
                'school_contactNo' => $request->contact_number,
                'school_password' => Hash::make($request->password),
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
                'type' => 'required',
                'country_code' => 'required',
                'contact_number' => 'required|numeric|digits_between:1,15',
                'email' => 'required|string|email|max:255|unique:stp_students,student_email',
            ]);

            $checkingUser = stp_student::where('student_countryCode', $request->country_code)
                ->where('student_contactNo', $request->contact_number)
                ->exists();


            if ($checkingUser) {
                throw ValidationException::withMessages([
                    'contact_no' => ['Contact has been used'],
                ]);
            }


            $data = [
                'student_userName' => $request->name,
                'student_email' => $request->email,
                'student_countryCode' => $request->country_code,
                'student_contactNo' => $request->contact_number,
                'student_password' => Hash::make($request->password),
                'user_role' => 4
            ];
            $newUser = stp_student::create($data);
            $userdetail = stp_student_detail::create([
                'student_id' => $newUser->id
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

    public function adminRegister(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:stp_users,email',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
                'type' => 'required',
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
}
