<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\StudentsImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\User;
use App\Models\FacultyProfile;

class ClassListUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'section' => 'required|string',
            'program' => 'required|string',
            'school_year' => 'required_without:academic_year|string',
            'academic_year' => 'required_without:school_year|string',
            'semester' => 'required|string',
            'faculty_user_id' => 'required|exists:users,id',
        ]);

        $schoolYear = $request->input('school_year') ?: $request->input('academic_year');

        $facultyUser = User::findOrFail($request->faculty_user_id);
        if (!$facultyUser->isFaculty()) {
            return response()->json(['error' => 'Provided user is not a faculty member.'], 422);
        }

        try {
            Excel::import(
                new StudentsImport(
                    $request->faculty_user_id,
                    $request->section,
                    $request->program,
                    $schoolYear,
                    $request->semester
                ),
                $request->file('file')
            );

            return response()->json([
                'message' => 'Class list uploaded successfully. Students assigned to the faculty.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to process the uploaded file: ' . $e->getMessage()], 500);
        }
    }
}
