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
        if ($user && ($user->hasRole('coordinator') || $user->hasRole('faculty'))) {
            $deptId = \App\Support\DepartmentScope::departmentIdFor($user);
            if (! $deptId) {
                return response()->json([]);
            }
            $query->where('id', $deptId);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function programs(Request $request)
    {
        $query = Program::where('is_active', true);
        $user = $request->user();

        if ($user && ($user->hasRole('coordinator') || $user->hasRole('faculty'))) {
            $deptId = \App\Support\DepartmentScope::departmentIdFor($user);
            if (! $deptId) {
                return response()->json([]);
            }
            $query->where('department_id', $deptId);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function sections()
    {
        return response()->json([]);
    }
}
