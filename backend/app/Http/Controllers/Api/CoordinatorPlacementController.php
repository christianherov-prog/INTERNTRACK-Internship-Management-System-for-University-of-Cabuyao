<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\HteRequest;
use App\Models\InternshipApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoordinatorPlacementController extends Controller
{
    /**
     * Get all applications
     */
    public function getApplications(): JsonResponse
    {
        $applications = InternshipApplication::with(['student', 'company'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['applications' => $applications]);
    }

    /**
     * Update application status
     */
    public function updateApplicationStatus(Request $request, InternshipApplication $application): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending_coordinator_approval,approved_to_apply,accepted_by_company,rejected_by_company',
            'coordinator_remarks' => 'nullable|string'
        ]);

        $application->update([
            'status' => $request->status,
            'coordinator_remarks' => $request->coordinator_remarks
        ]);

        return response()->json([
            'message' => 'Application status updated successfully.',
            'application' => $application->load(['student', 'company'])
        ]);
    }

    /**
     * Get all HTE requests
     */
    public function getHteRequests(): JsonResponse
    {
        $requests = HteRequest::with('student')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['requests' => $requests]);
    }

    /**
     * Update HTE request status
     */
    public function updateHteRequestStatus(Request $request, HteRequest $hteRequest): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'remarks' => 'nullable|string'
        ]);

        $hteRequest->update([
            'status' => $request->status,
            'remarks' => $request->remarks
        ]);

        // If approved, you might want to create a Company record with status "On Process"
        if ($request->status === 'approved') {
            // Check if company already created for this request to avoid duplicates
            // We can search by name loosely, or just create it.
            $company = Company::firstOrCreate(
                ['company_name' => $hteRequest->company_name],
                [
                    'address' => $hteRequest->address,
                    'contact_person' => $hteRequest->contact_person,
                    'contact_email' => $hteRequest->contact_email,
                    'contact_number' => $hteRequest->contact_number,
                    'moa_status' => 'On Process',
                    'is_active' => true,
                    'slots_available' => 0, // No slots until active
                ]
            );
        }

        return response()->json([
            'message' => 'HTE Request status updated.',
            'request' => $hteRequest->load('student')
        ]);
    }
}
