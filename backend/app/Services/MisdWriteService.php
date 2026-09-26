<?php

namespace App\Services;

use App\Models\MisdSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push assignment updates (section + faculty adviser) to MISD / iEnroll.
 *
 * Mock mode writes in-process via MockMisdRepository.
 * Live mode HTTP PUTs to MISD_API_BASE_URL.
 */
class MisdWriteService
{
    private bool $useMock;
    private string $baseUrl;

    public function __construct(private MockMisdRepository $mock)
    {
        $this->useMock = (bool) config('interntrack.misd_use_mock', true);
        $this->baseUrl = rtrim((string) config('interntrack.misd_api_base_url'), '/');
    }

    /**
     * @param  array{
     *   student_number: string,
     *   section: string,
     *   faculty_number: string,
     *   academic_year?: string|null,
     *   semester?: int|null,
     *   updated_by?: string|null,
     *   reason?: string|null,
     *   actor_user_id?: int|null
     * }  $payload
     * @return array{ok: bool, data?: array, error?: string, http_status?: int}
     */
    public function updateStudentSectionFaculty(array $payload): array
    {
        $studentNumber = strtoupper(trim((string) ($payload['student_number'] ?? '')));
        $section = FacultySectionAssignmentService::normalizeSection($payload['section'] ?? null);
        $facultyEmp = strtoupper(trim((string) ($payload['faculty_number'] ?? '')));
        $actorId = $payload['actor_user_id'] ?? null;

        $request = [
            'student_number'            => $studentNumber,
            'section'                   => $section,
            'faculty_number'            => $facultyEmp,
            'academic_year'             => $payload['academic_year'] ?? null,
            'semester'                  => isset($payload['semester']) ? (int) $payload['semester'] : null,
            'updated_by'                => $payload['updated_by'] ?? null,
            'reason'                    => $payload['reason'] ?? null,
        ];

        if ($studentNumber === '' || !$section || $facultyEmp === '') {
            $this->logSync('push', 'student_assignment', $studentNumber ?: null, 'failed', $actorId, $request, null, 'Missing student_number, section, or faculty_number');

            return ['ok' => false, 'error' => 'Incomplete MISD write payload.', 'http_status' => 422];
        }

        try {
            if ($this->useMock) {
                if (!$this->mock->findStudent($studentNumber)) {
                    $this->logSync('push', 'student_assignment', $studentNumber, 'failed', $actorId, $request, null, 'Student not found in mock MISD');

                    return ['ok' => false, 'error' => 'Student not found in MISD.', 'http_status' => 404];
                }

                if (!$this->mock->findFaculty($facultyEmp)) {
                    $this->logSync('push', 'student_assignment', $studentNumber, 'failed', $actorId, $request, null, 'Faculty not found in mock MISD');

                    return ['ok' => false, 'error' => 'Faculty adviser not found in MISD.', 'http_status' => 404];
                }

                $data = $this->mock->updateStudentSectionFaculty(
                    $studentNumber,
                    $section,
                    $facultyEmp,
                    $request['academic_year'],
                    $request['semester'],
                    $request['updated_by'],
                    $request['reason']
                );

                $this->logSync('push', 'student_assignment', $studentNumber, 'success', $actorId, $request, $data);

                return ['ok' => true, 'data' => $data ?? [], 'http_status' => 200];
            }

            $response = Http::timeout(15)
                ->retry(2, 400)
                ->withHeaders($this->authHeaders())
                ->put("{$this->baseUrl}/students/{$studentNumber}/section-faculty", $request);

            if ($response->failed()) {
                $error = 'MISD write failed: HTTP ' . $response->status();
                $body = $response->json();
                if (is_array($body) && !empty($body['message'])) {
                    $error = (string) $body['message'];
                }
                $this->logSync('push', 'student_assignment', $studentNumber, 'failed', $actorId, $request, $body, $error);

                return [
                    'ok'          => false,
                    'error'       => $error,
                    'http_status' => $response->status() >= 400 ? $response->status() : 502,
                    'data'        => is_array($body) ? $body : null,
                ];
            }

            $data = $response->json() ?? [];
            $this->logSync('push', 'student_assignment', $studentNumber, 'success', $actorId, $request, is_array($data) ? $data : null);

            return ['ok' => true, 'data' => is_array($data) ? $data : [], 'http_status' => 200];
        } catch (\Throwable $e) {
            Log::error('MisdWriteService failed: ' . $e->getMessage(), ['student' => $studentNumber]);
            $this->logSync('push', 'student_assignment', $studentNumber, 'failed', $actorId, $request, null, $e->getMessage());

            return ['ok' => false, 'error' => 'MISD write unavailable: ' . $e->getMessage(), 'http_status' => 502];
        }
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        $key = (string) config('interntrack.misd_api_key', '');
        if ($key === '') {
            return [];
        }

        return ['Authorization' => 'Bearer ' . $key, 'X-API-Key' => $key];
    }

    private function logSync(
        string $direction,
        string $entityType,
        ?string $entityKey,
        string $status,
        mixed $actorUserId,
        ?array $request,
        ?array $response,
        ?string $error = null
    ): void {
        try {
            MisdSyncLog::create([
                'direction'        => $direction,
                'entity_type'      => $entityType,
                'entity_key'       => $entityKey,
                'status'           => $status,
                'actor_user_id'    => $actorUserId ? (int) $actorUserId : null,
                'request_payload'  => $request,
                'response_payload' => $response,
                'error_message'    => $error,
            ]);
        } catch (\Throwable $e) {
            Log::warning('misd_sync_logs write failed: ' . $e->getMessage());
        }
    }
}
