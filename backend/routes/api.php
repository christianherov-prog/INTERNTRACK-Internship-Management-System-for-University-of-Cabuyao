<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\LogbookController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    Route::get('/student/dashboard', [DashboardController::class, 'summary']);

    Route::get('/student/attendance', [AttendanceController::class, 'index']);
    Route::post('/student/attendance', [AttendanceController::class, 'store']);
    Route::put('/student/attendance/{attendance}', [AttendanceController::class, 'update']);
    Route::delete('/student/attendance/{attendance}', [AttendanceController::class, 'destroy']);

    Route::get('/student/logbook', [LogbookController::class, 'index']);
    Route::post('/student/logbook', [LogbookController::class, 'store']);
    Route::put('/student/logbook/{logbookEntry}', [LogbookController::class, 'update']);
    Route::delete('/student/logbook/{logbookEntry}', [LogbookController::class, 'destroy']);

    Route::get('/student/documents', [DocumentController::class, 'index']);
    Route::post('/student/documents', [DocumentController::class, 'store']);
    Route::delete('/student/documents/{document}', [DocumentController::class, 'destroy']);

    Route::get('/student/evaluations', [EvaluationController::class, 'index']);

    Route::get('/announcements', [AnnouncementController::class, 'index']);
});
