<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\StudentsImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Program;
use App\Models\User;
use App\Support\DepartmentScope;

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

        $program = Program::where(function ($q) use ($request) {
            $q->where('name', $request->program)->orWhere('code', $request->program);
        })->first();

        $actor = $request->user();
        if ($actor && in_array($actor->role, ['faculty', 'coordinator'], true)) {
            $actorDept = DepartmentScope::departmentIdFor($actor);
            if (! $actorDept) {
                DepartmentScope::abortDifferentDepartment();
            }

            if (! $program || (int) $program->department_id !== $actorDept) {
                return response()->json(['error' => 'Program is not in your department.'], 422);
            }

            $facultyDept = DepartmentScope::departmentIdFor($facultyUser);
            if ((int) $facultyDept !== $actorDept) {
                DepartmentScope::abortDifferentDepartment();
            }
        }

        $facultyDept = DepartmentScope::departmentIdFor($facultyUser);
        if ($program?->department_id && $facultyDept && (int) $facultyDept !== (int) $program->department_id) {
            DepartmentScope::abortDifferentDepartment();
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
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to process the uploaded file: ' . $e->getMessage()], 500);
        }
    }
}
