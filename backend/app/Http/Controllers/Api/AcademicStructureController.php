<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Program;

class AcademicStructureController extends Controller
{
    public function departments(Request $request)
    {
        $query = Department::where('is_active', true);
        
        $user = $request->user();
        $user?->loadMissing('facultyProfile');
        if ($user && ($user->hasRole('coordinator') || $user->hasRole('faculty'))) {
            $deptId = $user->facultyProfile?->department_id;
            if ($deptId) {
                $query->where('id', $deptId);
            }
        }
        
        return response()->json($query->orderBy('name')->get());
    }

    public function programs(Request $request)
    {
        $query = Program::where('is_active', true);
        $user = $request->user();
        $user?->loadMissing('facultyProfile');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        } elseif ($user && ($user->hasRole('coordinator') || $user->hasRole('faculty'))) {
            $deptId = $user->facultyProfile?->department_id;
            if ($deptId) {
                $query->where('department_id', $deptId);
            }
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function sections()
    {
        return response()->json([]);
    }
}
