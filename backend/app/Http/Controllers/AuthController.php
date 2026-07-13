<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_number' => ['required', 'string', 'max:20', 'unique:students,student_number'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'full_name' => ['required', 'string', 'max:255'],
            'course_year_section' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:255'],
        ]);

        // 'hashed' cast on the model bcrypts the password automatically.
        $student = Student::create($data);

        $token = $student->createToken('student-api')->plainTextToken;

        return response()->json([
            'student' => $student,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_number' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $student = Student::where('student_number', $data['student_number'])->first();

        if (! $student || ! Hash::check($data['password'], $student->password)) {
            throw ValidationException::withMessages([
                'student_number' => ['Invalid student number or password.'],
            ]);
        }

        $token = $student->createToken('student-api')->plainTextToken;

        return response()->json([
            'student' => $student,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['student' => $request->user()]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'course_year_section' => ['sometimes', 'string', 'max:100'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $student = $request->user();
        $student->update($data);

        return response()->json(['student' => $student]);
    }
}
