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
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SupervisorRegistrationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InternshipStatusController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MisdAdminController;
use App\Http\Controllers\Api\SecureFileController;
use App\Http\Controllers\Api\PublicAvatarController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\ClassListUploadController;
use App\Http\Controllers\Api\RequirementController;
use App\Http\Controllers\Api\RequirementTemplateController;
use App\Http\Controllers\Api\SignatureController;
use App\Http\Controllers\Api\DtrPdfController;
use App\Http\Controllers\Api\JournalPdfController;
use App\Http\Controllers\Api\PortfolioPdfController;
use App\Http\Controllers\Api\StudentPortfolioController;



// ─── Public: Auth ─────────────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    Route::post('/auth/login',  [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/confirm-password-change', [AuthController::class, 'confirmPasswordChange'])->middleware('throttle:10,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/user',    [AuthController::class, 'user'])->middleware('auth:sanctum');

    // Public avatar media (does not require the public/storage symlink)
    Route::get('/media/avatars/{filename}', [PublicAvatarController::class, 'show'])
        ->where('filename', '[A-Za-z0-9._-]+');

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
    Route::post('/supervisor-register/validate', [SupervisorRegistrationController::class, 'validateToken'])
        ->middleware('throttle:10,1');
    Route::post('/supervisor-register',          [SupervisorRegistrationController::class, 'register'])
        ->middleware('throttle:5,1');

    // ─── Protected Routes ────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {

        Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
        Route::post('/auth/request-password-change', [AuthController::class, 'requestPasswordChange'])->middleware('throttle:5,1');
        Route::post('/auth/avatar',          [AuthController::class, 'uploadAvatar'])->middleware('throttle:5,1');
        Route::put('/auth/profile',          [AuthController::class, 'updateProfile']);
        Route::get('/auth/notification-preferences', [AuthController::class, 'notificationPreferences']);
        Route::put('/auth/notification-preferences', [AuthController::class, 'updateNotificationPreferences']);

        // Signature upload (student & supervisor profiles)
        Route::post('/auth/signature',        [SignatureController::class, 'upload']);
        Route::delete('/auth/signature',      [SignatureController::class, 'destroy']);
        Route::get('/auth/signature/status',  [SignatureController::class, 'status']);
        Route::get('/auth/signature/view',    [SignatureController::class, 'view']);

        // Role-aware dashboard summary (all portal roles including MISD admin)
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        // Notifications — shared across all roles
        Route::get('/notifications',                [NotificationController::class, 'index']);
        Route::post('/notifications/mark-read',     [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read',     [NotificationController::class, 'markRead']);

        // Messages — shared across roles; controller enforces internship participant checks
        // Director messaging is enabled on MERGE-ONLY (participant ACL patched).
        Route::get('/messages/conversations',                              [MessageController::class, 'conversations']);
        Route::get('/messages/conversations/{internshipId}/{peerId}',      [MessageController::class, 'thread']);
        Route::post('/messages/conversations/{internshipId}/{peerId}/archive', [MessageController::class, 'setArchived']);
        Route::post('/messages/conversations/{internshipId}/{peerId}/clear',   [MessageController::class, 'clearThread']);
        Route::post('/messages',                                           [MessageController::class, 'send'])->middleware('throttle:messages');
        Route::post('/messages/{id}/unsend',                               [MessageController::class, 'unsend']);

        // Meetings / orientation scheduler
        // Meetings / orientation scheduler
        Route::get('/meetings',             [MeetingController::class, 'index']);
        Route::post('/meetings',            [MeetingController::class, 'store']);
        Route::patch('/meetings/{id}',      [MeetingController::class, 'update']);
        Route::patch('/meetings/{id}/rsvp', [MeetingController::class, 'rsvp']);

        // Private uploads (journals, documents, signatures, portfolio)
        Route::get('/files/download', [SecureFileController::class, 'download']);

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
            Route::get('/requirements/{id}/template', [RequirementTemplateController::class, 'downloadTemplate']);
            Route::get('/evaluations',           [StudentController::class, 'evaluations']);
            Route::get('/records',               [StudentController::class, 'records']);
            Route::get('/companies',             [StudentController::class, 'companies']);
            Route::post('/applications',         [StudentController::class, 'applyCompany']);
            Route::get('/applications',          [StudentController::class, 'applications']);
            Route::post('/hte-requests',         [StudentController::class, 'submitHteRequest']);
            Route::get('/hte-requests',          [StudentController::class, 'hteRequests']);
            Route::post('/absorption/declare',   [StudentController::class, 'declareAbsorption']);

            // Supervisor Invite (QR Code)
            Route::post('/supervisor-invite',       [SupervisorRegistrationController::class, 'generateInvite']);
            Route::get('/supervisor-invite/status',  [SupervisorRegistrationController::class, 'inviteStatus']);

            // PDF generation
            Route::get('/dtr/generate',       [DtrPdfController::class, 'generate']);
            Route::get('/journal/generate',   [JournalPdfController::class, 'generate']);
            Route::get('/portfolio/generate', [PortfolioPdfController::class, 'generate']);

            // Portfolio Builder
            Route::get('/portfolio',               [StudentPortfolioController::class, 'show']);
            Route::post('/portfolio',              [StudentPortfolioController::class, 'update']);
            Route::get('/portfolio/builder',       [StudentPortfolioController::class, 'show']);
            Route::post('/portfolio/builder',      [StudentPortfolioController::class, 'update']);
            Route::post('/portfolio/photos',       [StudentPortfolioController::class, 'uploadPhoto']);
            Route::delete('/portfolio/photos/{id}', [StudentPortfolioController::class, 'deletePhoto']);
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
            Route::get('/absorption',                     [SupervisorController::class, 'absorptionList']);
            // Absorption finalize is Director-only; stub route removed from supervisor API surface.

            // PDF generation for supervisor
            Route::get('/dtr/generate',                   [DtrPdfController::class, 'generate']);
            Route::get('/journal/generate',               [JournalPdfController::class, 'generate']);
        });

        // Faculty
        Route::prefix('faculty')->middleware('role:faculty')->group(function () {
            Route::get('/dashboard',                   [FacultyController::class, 'dashboard']);
            Route::get('/assigned-students',           [FacultyController::class, 'assignedStudents']);
            Route::patch('/students/{userId}/archive', [FacultyController::class, 'setStudentArchived']);
            Route::get('/students/{userId}/progress',  [FacultyController::class, 'studentProgress']);
            Route::get('/attendance',                  [FacultyController::class, 'attendance']);
            Route::get('/journals',                    [FacultyController::class, 'journals']);
            Route::patch('/journals/{id}/review',      [FacultyController::class, 'reviewJournal']);
            Route::get('/evaluations',                 [FacultyController::class, 'evaluations']);
            Route::post('/evaluations/{internshipId}', [FacultyController::class, 'submitEvaluation']);
            Route::get('/feedback',                    [FacultyController::class, 'feedback']);
            Route::post('/feedback/{internshipId}',    [FacultyController::class, 'submitFeedback']);
            Route::get('/documents',                   [FacultyController::class, 'documents']);
            Route::patch('/documents/{id}/verify',     [FacultyController::class, 'verifyDocument']);
            Route::patch('/documents/{id}/reject',     [FacultyController::class, 'rejectDocument']);
            Route::get('/reports/student-summary',     [FacultyController::class, 'reportStudentSummary']);
            Route::get('/reports/compliance',          [FacultyController::class, 'reportCompliance']);
            Route::get('/reports/performance',         [FacultyController::class, 'reportPerformance']);
            // Dynamic OJT Requirement Management
            Route::get('/requirements/options',   [RequirementTemplateController::class, 'options']);
            Route::get('/requirements',           [RequirementTemplateController::class, 'index']);
            Route::post('/requirements',          [RequirementTemplateController::class, 'store']);
            Route::match(['put', 'post'], '/requirements/{id}', [RequirementTemplateController::class, 'update']);
            Route::delete('/requirements/{id}',   [RequirementTemplateController::class, 'destroy']);

            // PDF generation for faculty
            Route::get('/dtr/generate',     [DtrPdfController::class, 'generate']);
            Route::get('/journal/generate', [JournalPdfController::class, 'generate']);

            // Supervisor registration approvals (faculty only)
            Route::get('/supervisor-approvals',              [SupervisorRegistrationController::class, 'pendingList']);
            Route::patch('/supervisor-approvals/{id}/approve', [SupervisorRegistrationController::class, 'approve']);
            Route::patch('/supervisor-approvals/{id}/reject',  [SupervisorRegistrationController::class, 'reject']);
        });

        // Coordinator
        Route::prefix('coordinator')->middleware('role:coordinator')->group(function () {
            Route::get('/dashboard',                 [CoordinatorController::class, 'dashboard']);
            Route::post('/class-list/upload',        [ClassListUploadController::class, 'upload']);
            Route::get('/monitoring',                [CoordinatorController::class, 'monitoring']);
            Route::get('/announcements',             [AnnouncementController::class, 'index']);
            Route::post('/announcements',            [AnnouncementController::class, 'store']);
            // POST allowed for multipart attachment replace (PHP file uploads require POST).
            Route::match(['put', 'post'], '/announcements/{id}', [AnnouncementController::class, 'update']);
            Route::delete('/announcements/{id}',     [AnnouncementController::class, 'destroy']);
            Route::get('/records',                   [CoordinatorController::class, 'records']);
            Route::patch('/students/{userId}/archive',[CoordinatorController::class, 'setStudentArchived']);
            Route::get('/placement-options',         [CoordinatorController::class, 'placementOptions']);
            Route::post('/internships/{id}/place',   [CoordinatorController::class, 'assignPlacement']);
            Route::get('/internships/{id}/status-history', [InternshipStatusController::class, 'history']);
            Route::patch('/internships/{id}/status', [InternshipStatusController::class, 'update']);
            Route::get('/applications',              [CoordinatorController::class, 'applications']);
            Route::patch('/applications/{id}/status',[CoordinatorController::class, 'updateApplicationStatus']);
            Route::get('/hte-requests',              [CoordinatorController::class, 'hteRequests']);
            Route::patch('/hte-requests/{id}/status',[CoordinatorController::class, 'updateHteRequestStatus']);
            Route::get('/absorption',                    [CoordinatorController::class, 'absorptionList']);
            // Absorption finalize is Director-only on V2; keep overview + filtered reports from develop.
            Route::get('/reports/overview',          [CoordinatorController::class, 'reportsOverview']);
            Route::get('/reports/student-summary',   [CoordinatorController::class, 'reportStudentSummary']);
            Route::get('/reports/compliance',        [CoordinatorController::class, 'reportCompliance']);
            Route::get('/reports/performance',       [CoordinatorController::class, 'reportPerformance']);
            Route::get('/evaluations',               [CoordinatorController::class, 'evaluations']);
            Route::get('/supervisor-feedback',       [CoordinatorController::class, 'supervisorFeedback']);
            Route::get('/logbook',                   [CoordinatorController::class, 'logbook']);
            Route::patch('/logbook/{id}/review',     [CoordinatorController::class, 'reviewLogbook']);
            Route::get('/documents',                 [CoordinatorController::class, 'documents']);
            Route::patch('/documents/bulk-approve',  [CoordinatorController::class, 'bulkApproveDocuments']);
            Route::patch('/documents/bulk-reject',   [CoordinatorController::class, 'bulkRejectDocuments']);
            Route::patch('/documents/{id}/approve',  [CoordinatorController::class, 'approveDocument']);
            Route::patch('/documents/{id}/reject',   [CoordinatorController::class, 'rejectDocument']);

            // Dynamic OJT Requirement Management
            Route::get('/requirements/options',   [RequirementTemplateController::class, 'options']);
            Route::get('/requirements',           [RequirementTemplateController::class, 'index']);
            Route::post('/requirements',          [RequirementTemplateController::class, 'store']);
            Route::match(['put', 'post'], '/requirements/{id}', [RequirementTemplateController::class, 'update']);
            Route::delete('/requirements/{id}',   [RequirementTemplateController::class, 'destroy']);
        });

        // Director
        Route::prefix('director')->middleware('role:director')->group(function () {
            Route::get('/dashboard',       [DirectorController::class, 'dashboard']);
            Route::get('/evaluations',     [DirectorController::class, 'hteEvaluations']);
            Route::get('/analytics',       [DirectorController::class, 'analytics']);
            Route::get('/companies',       [DirectorController::class, 'companies']);
            Route::post('/companies',      [DirectorController::class, 'storeCompany']);
            Route::put('/companies/{id}',  [DirectorController::class, 'updateCompany']);
            Route::get('/moa-monitoring',  [DirectorController::class, 'moaMonitoring']);
            Route::get('/reports/placement-trends', [DirectorController::class, 'placementTrends']);
            Route::get('/reports/ched-data',      [DirectorController::class, 'chedReportData']);
            Route::get('/records',         [DirectorController::class, 'records']);
            Route::get('/placement-options', [DirectorController::class, 'placementOptions']);
            Route::post('/internships/{id}/place', [DirectorController::class, 'assignPlacement']);
            Route::patch('/students/{userId}/archive', [DirectorController::class, 'setStudentArchived']);
            Route::get('/internships',     [DirectorController::class, 'internships']);
            Route::get('/internships/{id}/status-history', [InternshipStatusController::class, 'history']);
            Route::patch('/internships/{id}/status', [InternshipStatusController::class, 'update']);
            Route::get('/absorption',                    [DirectorController::class, 'absorptionList']);
            Route::patch('/internships/{id}/absorption', [DirectorController::class, 'recordAbsorption']);
            Route::get('/announcements',             [AnnouncementController::class, 'index']);
            Route::post('/announcements',            [AnnouncementController::class, 'store']);
            Route::match(['put', 'post'], '/announcements/{id}', [AnnouncementController::class, 'update']);
            Route::delete('/announcements/{id}',     [AnnouncementController::class, 'destroy']);
        });

        // MISD Admin portal
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('/dashboard',                     [MisdAdminController::class, 'dashboard']);
            Route::get('/directors',                     [MisdAdminController::class, 'directors']);
            Route::post('/directors',                    [MisdAdminController::class, 'assignDirector']);
            Route::get('/coordinators',                  [MisdAdminController::class, 'coordinators']);
            Route::post('/coordinators',                 [MisdAdminController::class, 'assignCoordinator']);
            Route::put('/staff/{id}',                    [MisdAdminController::class, 'updateStaff']);
            Route::post('/staff/{id}/revoke',            [MisdAdminController::class, 'revokeStaff']);
            Route::post('/staff/{id}/sync',              [MisdAdminController::class, 'syncStaff']);
            Route::post('/staff/{id}/reset-password',    [MisdAdminController::class, 'resetPassword']);

            Route::get('/users',                         [MisdAdminController::class, 'users']);
            Route::patch('/users/{id}/active',           [MisdAdminController::class, 'setUserActive']);
            Route::post('/users/{id}/reset-password',    [MisdAdminController::class, 'resetPassword']);

            Route::get('/section-assignments',           [MisdAdminController::class, 'sectionAssignments']);
            Route::post('/section-assignments',          [MisdAdminController::class, 'storeSectionAssignment']);
            Route::put('/section-assignments/{id}',      [MisdAdminController::class, 'updateSectionAssignment']);
            Route::delete('/section-assignments/{id}',   [MisdAdminController::class, 'destroySectionAssignment']);
            Route::get('/faculty-options',               [MisdAdminController::class, 'facultyOptions']);

            Route::get('/misd/status',                   [MisdAdminController::class, 'misdStatus']);
            Route::get('/misd/faculty/{employeeNumber}', [MisdAdminController::class, 'previewFaculty']);
            Route::get('/misd/students/{studentNumber}', [MisdAdminController::class, 'previewStudent']);
            Route::post('/misd/sync/student/{id}',       [MisdAdminController::class, 'syncStudent']);
            Route::post('/misd/directory',               [MisdAdminController::class, 'syncDirectory']);
            Route::get('/misd/unmapped-sections',        [MisdAdminController::class, 'unmappedSections']);
            Route::get('/audit-log',                     [MisdAdminController::class, 'auditLog']);
            Route::get('/provisioning-log',              [MisdAdminController::class, 'provisioningLog']);
        });
    });


});
