<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ConfirmPasswordChangeRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AuthController
 *
 * Thin HTTP layer: validates input (via Form Requests), delegates to
 * AuthService, and formats output via UserResource.
 *
 * Zero business logic lives here.
 */
class AuthController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        ['token' => $token, 'user' => $user] = $this->auth->login(
            $request->input('username'),
            $request->input('password'),
            $request->ip(),
        );

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }

    /** POST /api/v1/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $user  = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        audit_log($user->id, 'logout', []);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /** GET /api/v1/auth/user */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load(AuthService::USER_RELATIONS);

        return response()->json(['user' => new UserResource($user)]);
    }

    /** POST /api/v1/auth/avatar */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->auth->uploadAvatar($request->user(), $request->file('avatar'));

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'user'    => new UserResource($user),
        ]);
    }

    /** PUT /api/v1/auth/password */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $this->auth->changePassword(
            $request->user(),
            $request->input('current_password'),
            $request->input('new_password'),
        );

        return response()->json([
            'message' => 'Password updated successfully.',
            'user'    => new UserResource($user),
        ]);
    }

    /** POST /api/v1/auth/forgot-password */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
        ]);

        $result = $this->auth->forgotPassword($request->input('identifier'));

        return response()->json($result);
    }

    /** POST /api/v1/auth/request-password-change */
    public function requestPasswordChange(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->auth->requestPasswordChange($user);

        return response()->json([
            'message' => 'Password confirmation email sent. Please check your inbox (and system notifications).',
            'email'   => $user->email,
        ]);
    }

    /** POST /api/v1/auth/confirm-password-change */
    public function confirmPasswordChange(ConfirmPasswordChangeRequest $request): JsonResponse
    {
        $user = $this->auth->confirmPasswordChange(
            $request->input('email'),
            $request->input('token'),
            $request->input('new_password'),
        );

        return response()->json([
            'message' => 'Password changed successfully! You can now log in with your new password.',
            'user'    => new UserResource($user),
        ]);
    }

    /** GET /api/v1/auth/notification-preferences */
    public function notificationPreferences(Request $request): JsonResponse
    {
        $user  = $request->user();
        $prefs = NotificationPreferences::mergeForUser($user->role, $user->notification_preferences);

        return response()->json([
            'preferences'  => $prefs,
            'allowed_keys' => NotificationPreferences::allowedKeysForRole($user->role),
        ]);
    }

    /** PUT /api/v1/auth/notification-preferences */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $request->validate(['preferences' => ['required', 'array']]);

        $user  = $request->user();
        $store = $this->auth->updateNotificationPreferences($user, $request->input('preferences'));

        $user->refresh()->load(AuthService::USER_RELATIONS);

        return response()->json([
            'message'     => 'Notification preferences saved.',
            'preferences' => $store,
            'user'        => new UserResource($user),
        ]);
    }

    /** PUT /api/v1/auth/profile */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'message' => 'Profile updates are disabled. Identity fields are managed entirely by iEnroll.',
            ], 403);
        }

        $user = $this->auth->updateProfile($user, $request->validated());

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => new UserResource($user),
        ]);
    }
}
