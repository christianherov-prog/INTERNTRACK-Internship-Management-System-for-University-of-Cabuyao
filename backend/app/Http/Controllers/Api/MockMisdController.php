<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Services\MockMisdRepository;

/**
 * MockMisdController
 *
 * Simulates the University of Cabuyao MISD (iEnroll) API.
 * Pulls data dynamically from MockMisdRepository so the mock endpoints
 * accurately reflect the in-process mock data used for provisioning.
 */
class MockMisdController extends Controller
{
    private MockMisdRepository $repository;

    public function __construct(MockMisdRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * GET /api/v1/mock-misd
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Mock MISD (iEnroll) API is running.',
            'endpoints' => [
                'GET /api/v1/mock-misd/students' => 'List all mock students',
                'GET /api/v1/mock-misd/students/{studentNumber}' => 'Get specific mock student',
                'GET /api/v1/mock-misd/faculty' => 'List all mock faculty',
                'GET /api/v1/mock-misd/faculty/{employeeNumber}' => 'Get specific mock faculty',
            ],
            'status' => 'success'
        ]);
    }

    /**
     * GET /api/v1/mock-misd/students/{studentNumber}
     */
    public function student(string $studentNumber): JsonResponse
    {
        $data = $this->repository->findStudent($studentNumber);

        if (!$data) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        return response()->json($data);
    }

    /**
     * GET /api/v1/mock-misd/faculty/{employeeNumber}
     */
    public function faculty(string $employeeNumber): JsonResponse
    {
        $data = $this->repository->findFaculty($employeeNumber);

        if (!$data) {
            return response()->json(['message' => 'Faculty not found'], 404);
        }

        return response()->json($data);
    }

    /**
     * GET /api/v1/mock-misd/students — list all mock students
     */
    public function allStudents(): JsonResponse
    {
        return response()->json(array_values($this->repository->students()));
    }

    /**
     * GET /api/v1/mock-misd/faculty — list all mock faculty
     */
    public function allFaculty(): JsonResponse
    {
        return response()->json(array_values($this->repository->faculty()));
    }
}
