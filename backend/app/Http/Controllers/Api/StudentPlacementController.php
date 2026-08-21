<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ApplyCompanyRequest;
use App\Http\Requests\Student\StoreHteRequest;
use App\Models\Company;
use App\Models\HteRequest;
use App\Models\InternshipApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPlacementController extends Controller
{
    /**
     * Get all active companies eligible for application
     */
    public function getEligibleCompanies(): JsonResponse
    {
        $companies = Company::where('moa_status', 'Active')
            ->where('is_active', true)
            ->where('slots_available', '>', 0)
            ->get();

        return response()->json(['companies' => $companies]);
    }

    /**
     * Get applications made by the student
     */
    public function myApplications(Request $request): JsonResponse
    {
        $applications = InternshipApplication::with('company')
            ->where('student_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['applications' => $applications]);
    }

    /**
     * Apply to an existing accredited company
     */
    public function applyToCompany(ApplyCompanyRequest $request): JsonResponse
    {
        // Check if student already has a pending application
        $hasPending = InternshipApplication::where('student_id', $request->user()->id)
            ->whereIn('status', ['pending_coordinator_approval', 'approved_to_apply'])
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'You already have an active application. You cannot apply to multiple companies at once.'
            ], 422);
        }

        // Check if they are already placed in their current internship
        $activeInternship = $request->user()->activeInternship()->first();
        if ($activeInternship && !in_array($activeInternship->status, ['pending_placement', 'completed'])) {
            return response()->json([
                'message' => 'You are already placed in a company for your current practicum deployment.'
            ], 422);
        }

        $application = InternshipApplication::create([
            'student_id' => $request->user()->id,
            'company_id' => $request->company_id,
            'status' => 'pending_coordinator_approval',
        ]);

        return response()->json([
            'message' => 'Application request submitted to coordinator.',
            'application' => $application->load('company')
        ], 201);
    }

    /**
     * Get HTE requests made by the student
     */
    public function myHteRequests(Request $request): JsonResponse
    {
        $requests = HteRequest::where('student_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['requests' => $requests]);
    }

    /**
     * Submit a new HTE request (Scenario 2)
     */
    public function storeHteRequest(StoreHteRequest $request): JsonResponse
    {
        $hteRequest = HteRequest::create([
            'student_id' => $request->user()->id,
            'company_name' => $request->company_name,
            'address' => $request->address,
            'contact_person' => $request->contact_person,
            'contact_email' => $request->contact_email,
            'contact_number' => $request->contact_number,
            'remarks' => $request->remarks,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'New HTE request submitted successfully.',
            'request' => $hteRequest
        ], 201);
    }
}
