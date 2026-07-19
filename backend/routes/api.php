<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MockMisdController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SupervisorController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\DirectorController;
use App\Http\Controllers\Api\CoordinatorController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SupervisorRegistrationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InternshipStatusController;
use App\Http\Controllers\Api\CertificateController;

// ─── Public: Auth ─────────────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    Route::post('/auth/login',  [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/user',    [AuthController::class, 'user'])->middleware('auth:sanctum');

    // ─── Mock MISD (iEnroll simulation) — LOCAL ONLY (login provisioning hits these) ─
    // Not available in staging/production so PII mock data is not publicly exposed.
    if (app()->environment('local')) {
        Route::prefix('mock-misd')->group(function () {
            Route::get('/students/{studentNumber}', [MockMisdController::class, 'student']);
            Route::get('/faculty/{employeeNumber}', [MockMisdController::class, 'faculty']);
            Route::get('/students',                 [MockMisdController::class, 'allStudents']);
            Route::get('/faculty',                  [MockMisdController::class, 'allFaculty']);
        });
    }

    // ─── Public: Supervisor Self-Registration (QR Code Flow) ────────────────
    Route::post('/supervisor-register/validate', [SupervisorRegistrationController::class, 'validateToken']);
    Route::post('/supervisor-register',          [SupervisorRegistrationController::class, 'register']);

    // ─── Protected Routes ────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/avatar',          [AuthController::class, 'uploadAvatar']);
        Route::put('/auth/profile',          [AuthController::class, 'updateProfile']);

        // Role-aware dashboard summary (director / coordinator / faculty / student)
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        // Notifications — shared across all roles
        Route::get('/notifications',                [NotificationController::class, 'index']);
        Route::post('/notifications/mark-read',     [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read',     [NotificationController::class, 'markRead']);


        // Student
        Route::prefix('student')->middleware('role:student')->group(function () {
            Route::get('/dashboard',             [StudentController::class, 'dashboard']);
            Route::get('/attendance',            [StudentController::class, 'attendance']);
            Route::post('/attendance/clock-in',  [StudentController::class, 'clockIn']);
            Route::post('/attendance/clock-out', [StudentController::class, 'clockOut']);
            Route::get('/logbook',               [StudentController::class, 'logbook']);
            Route::post('/logbook',              [StudentController::class, 'submitJournal']);
            Route::get('/documents',             [StudentController::class, 'documents']);
            Route::post('/documents/upload',     [StudentController::class, 'uploadDocument']);
            Route::get('/evaluations',           [StudentController::class, 'evaluations']);
            Route::get('/records',               [StudentController::class, 'records']);
            Route::post('/absorption/declare',   [StudentController::class, 'declareAbsorption']);
            Route::get('/announcements',         [StudentController::class, 'announcements']);
            Route::get('/certificates/completion', [CertificateController::class, 'completion']);
            
            // Portfolio Builder
            Route::get('/portfolio', [PortfolioController::class, 'getPortfolio']);
            Route::post('/portfolio', [PortfolioController::class, 'savePortfolio']);
            Route::post('/portfolio/photos', [PortfolioController::class, 'uploadPhoto']);
            Route::delete('/portfolio/photos/{id}', [PortfolioController::class, 'deletePhoto']);

            // Supervisor Invite (QR Code)
            Route::post('/supervisor-invite',       [SupervisorRegistrationController::class, 'generateInvite']);
            Route::get('/supervisor-invite/status',  [SupervisorRegistrationController::class, 'inviteStatus']);
        });

        // Supervisor
        Route::prefix('supervisor')->middleware('role:supervisor')->group(function () {
            Route::get('/dashboard',                      [SupervisorController::class, 'dashboard']);
            Route::get('/assigned-interns',               [SupervisorController::class, 'assignedInterns']);
            Route::get('/assigned-students',              [SupervisorController::class, 'assignedStudents']);
            Route::get('/attendance',                     [SupervisorController::class, 'attendance']);
            Route::patch('/attendance/{id}/validate',     [SupervisorController::class, 'validateAttendance']);
            Route::patch('/attendance/bulk-validate',     [SupervisorController::class, 'bulkValidateAttendance']);
            Route::get('/journals',                       [SupervisorController::class, 'journals']);
            Route::patch('/journals/{id}/review',         [SupervisorController::class, 'reviewJournal']);
            Route::get('/evaluations',                    [SupervisorController::class, 'evaluations']);
            Route::post('/evaluations/{internshipId}',    [SupervisorController::class, 'submitEvaluation']);
            Route::get('/feedback',                       [SupervisorController::class, 'feedback']);
            Route::post('/feedback/{internshipId}',       [SupervisorController::class, 'submitFeedback']);
            Route::get('/notifications',                  [SupervisorController::class, 'notifications']);
            Route::get('/absorption',                     [SupervisorController::class, 'absorptionList']);
            Route::patch('/internships/{id}/absorption',  [SupervisorController::class, 'recordAbsorption']);
        });

        // Faculty
        Route::prefix('faculty')->middleware('role:faculty')->group(function () {
            Route::get('/dashboard',                   [FacultyController::class, 'dashboard']);
            Route::get('/assigned-students',           [FacultyController::class, 'assignedStudents']);
            Route::get('/journals',                    [FacultyController::class, 'journals']);
            Route::patch('/journals/{id}/review',      [FacultyController::class, 'reviewJournal']);
            Route::get('/evaluations',                 [FacultyController::class, 'evaluations']);
            Route::post('/evaluations/{internshipId}', [FacultyController::class, 'submitEvaluation']);
            Route::get('/feedback',                    [FacultyController::class, 'feedback']);
            Route::post('/feedback/{internshipId}',    [FacultyController::class, 'submitFeedback']);
            Route::get('/documents',                   [FacultyController::class, 'documents']);
            Route::patch('/documents/{id}/verify',     [FacultyController::class, 'verifyDocument']);
            Route::patch('/documents/{id}/reject',     [FacultyController::class, 'rejectDocument']);
        });

        // Coordinator
        Route::prefix('coordinator')->middleware('role:coordinator')->group(function () {
            Route::get('/dashboard',                 [CoordinatorController::class, 'dashboard']);
            Route::get('/monitoring',                [CoordinatorController::class, 'monitoring']);
            Route::get('/announcements',             [CoordinatorController::class, 'announcements']);
            Route::post('/announcements',            [CoordinatorController::class, 'createAnnouncement']);
            Route::put('/announcements/{id}',        [CoordinatorController::class, 'updateAnnouncement']);
            Route::delete('/announcements/{id}',     [CoordinatorController::class, 'deleteAnnouncement']);
            Route::get('/documents',                 [CoordinatorController::class, 'documents']);
            Route::patch('/documents/bulk-approve',  [CoordinatorController::class, 'bulkApproveDocuments']);
            Route::patch('/documents/bulk-reject',   [CoordinatorController::class, 'bulkRejectDocuments']);
            Route::patch('/documents/{id}/approve',  [CoordinatorController::class, 'approveDocument']);
            Route::patch('/documents/{id}/reject',   [CoordinatorController::class, 'rejectDocument']);
            Route::get('/logbook',                   [CoordinatorController::class, 'logbook']);
            Route::patch('/logbook/{id}/review',     [CoordinatorController::class, 'reviewLogbook']);
            Route::get('/records',                   [CoordinatorController::class, 'records']);
            Route::get('/placement-options',         [CoordinatorController::class, 'placementOptions']);
            Route::post('/internships/{id}/place',   [CoordinatorController::class, 'assignPlacement']);
            Route::get('/internships/{id}/status-history', [InternshipStatusController::class, 'history']);
            Route::patch('/internships/{id}/status', [InternshipStatusController::class, 'update']);
            Route::get('/internships/{id}/certificate', [CertificateController::class, 'completion']);
            Route::get('/absorption',                    [CoordinatorController::class, 'absorptionList']);
            Route::patch('/internships/{id}/absorption', [CoordinatorController::class, 'recordAbsorption']);
            Route::get('/reports/overview',          [CoordinatorController::class, 'reportsOverview']);
            Route::get('/reports/student-summary',   [CoordinatorController::class, 'reportStudentSummary']);
            Route::get('/reports/compliance',        [CoordinatorController::class, 'reportCompliance']);
            Route::get('/reports/performance',       [CoordinatorController::class, 'reportPerformance']);
            Route::get('/evaluations',               [CoordinatorController::class, 'evaluations']);
            Route::get('/supervisor-feedback',       [CoordinatorController::class, 'supervisorFeedback']);

            // Supervisor Registration Approvals
            Route::get('/supervisor-approvals',              [SupervisorRegistrationController::class, 'pendingList']);
            Route::patch('/supervisor-approvals/{id}/approve', [SupervisorRegistrationController::class, 'approve']);
            Route::patch('/supervisor-approvals/{id}/reject',  [SupervisorRegistrationController::class, 'reject']);
        });

        // Director
        Route::prefix('director')->middleware('role:director')->group(function () {
            Route::get('/dashboard',       [DirectorController::class, 'dashboard']);
            Route::get('/analytics',       [DirectorController::class, 'analytics']);
            Route::get('/companies',       [DirectorController::class, 'companies']);
            Route::post('/companies',      [DirectorController::class, 'storeCompany']);
            Route::put('/companies/{id}',  [DirectorController::class, 'updateCompany']);
            Route::delete('/companies/{id}', [DirectorController::class, 'destroyCompany']);
            Route::get('/moa-monitoring',  [DirectorController::class, 'moaMonitoring']);
            Route::get('/reports',         [DirectorController::class, 'reports']);
            Route::get('/internships',     [DirectorController::class, 'internships']);
            Route::get('/documents',       [DirectorController::class, 'documents']);
            Route::get('/internships/{id}/status-history', [InternshipStatusController::class, 'history']);
            Route::patch('/internships/{id}/status', [InternshipStatusController::class, 'update']);
            Route::get('/internships/{id}/certificate', [CertificateController::class, 'completion']);
        });
    });
});
