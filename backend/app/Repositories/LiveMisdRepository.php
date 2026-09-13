<?php

namespace App\Repositories;

use App\Contracts\MisdRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveMisdRepository implements MisdRepositoryInterface
{
    private string $baseUrl;
    private int $timeout;
    private int $retries;
    private int $retryDelayMs;

    public function __construct(string $baseUrl, int $timeout = 10, int $retries = 3, int $retryDelayMs = 500)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->retries = $retries;
        $this->retryDelayMs = $retryDelayMs;
    }

    public function findStudent(string $studentNumber): ?array
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->retries, $this->retryDelayMs)
            ->get("{$this->baseUrl}/students/{$studentNumber}");

        if ($response->failed()) {
            Log::warning("MISD Live: student not found [{$studentNumber}]");
            return null;
        }

        return $response->json();
    }

    public function findFaculty(string $employeeNumber): ?array
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->retries, $this->retryDelayMs)
            ->get("{$this->baseUrl}/faculty/{$employeeNumber}");

        if ($response->failed()) {
            Log::warning("MISD Live: faculty not found [{$employeeNumber}]");
            return null;
        }

        return $response->json();
    }

    public function allStudents(): array
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->retries, $this->retryDelayMs)
            ->get("{$this->baseUrl}/students");

        if ($response->failed()) {
            Log::warning("MISD Live: failed to fetch all students");
            return [];
        }

        return $response->json() ?? [];
    }

    public function allFaculty(): array
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->retries, $this->retryDelayMs)
            ->get("{$this->baseUrl}/faculty");

        if ($response->failed()) {
            Log::warning("MISD Live: failed to fetch all faculty");
            return [];
        }

        return $response->json() ?? [];
    }
}
