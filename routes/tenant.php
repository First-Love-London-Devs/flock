<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return response()->json([
            'app' => 'Flock',
            'tenant' => tenant('church_name'),
            'status' => 'ok',
        ]);
    });
});

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api/v1')->group(function () {
    // Auth (no auth required)
    Route::post('/auth/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);

        // Group Types
        Route::apiResource('group-types', App\Http\Controllers\Api\GroupTypeController::class);

        // Groups
        Route::get('/groups/{id}/children', [App\Http\Controllers\Api\GroupController::class, 'children']);
        Route::get('/groups/{id}/ancestors', [App\Http\Controllers\Api\GroupController::class, 'ancestors']);
        Route::get('/groups/{id}/members', [App\Http\Controllers\Api\GroupController::class, 'members']);
        Route::get('/groups/{id}/hierarchy', [App\Http\Controllers\Api\GroupController::class, 'hierarchy']);
        Route::apiResource('groups', App\Http\Controllers\Api\GroupController::class);

        // Members
        Route::get('/members/search', [App\Http\Controllers\Api\MemberController::class, 'search']);
        Route::post('/members/{id}/assign-group', [App\Http\Controllers\Api\MemberController::class, 'assignGroup']);
        Route::delete('/members/{id}/remove-group/{groupId}', [App\Http\Controllers\Api\MemberController::class, 'removeGroup']);
        Route::apiResource('members', App\Http\Controllers\Api\MemberController::class);

        // Leaders
        Route::post('/leaders/{id}/assign-role', [App\Http\Controllers\Api\LeaderController::class, 'assignRole']);
        Route::delete('/leaders/{id}/remove-role/{roleId}', [App\Http\Controllers\Api\LeaderController::class, 'removeRole']);
        Route::apiResource('leaders', App\Http\Controllers\Api\LeaderController::class);

        // Attendance
        Route::post('/attendance/submit', [App\Http\Controllers\Api\AttendanceController::class, 'submit']);
        Route::get('/attendance/group/{groupId}', [App\Http\Controllers\Api\AttendanceController::class, 'groupHistory']);
        Route::get('/attendance/defaulters/{parentGroupId}/{date}', [App\Http\Controllers\Api\AttendanceController::class, 'defaulters']);
        Route::get('/attendance/{summaryId}', [App\Http\Controllers\Api\AttendanceController::class, 'show']);
        Route::put('/attendance/{summaryId}', [App\Http\Controllers\Api\AttendanceController::class, 'update']);
        Route::delete('/attendance/{summaryId}', [App\Http\Controllers\Api\AttendanceController::class, 'destroy']);

        // Dashboard
        Route::get('/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'index']);
        Route::get('/dashboard/attendance-trends', [App\Http\Controllers\Api\DashboardController::class, 'attendanceTrends']);
        Route::get('/dashboard/defaulters', [App\Http\Controllers\Api\DashboardController::class, 'defaulters']);
        Route::get('/dashboard/stats', [App\Http\Controllers\Api\DashboardController::class, 'stats']);

        // Settings
        Route::get('/settings', [App\Http\Controllers\Api\SettingController::class, 'index']);
        Route::put('/settings/{key}', [App\Http\Controllers\Api\SettingController::class, 'update']);

        // Push Notifications
        Route::post('/push-token', [App\Http\Controllers\Api\PushNotificationController::class, 'storeToken']);
        Route::delete('/push-token', [App\Http\Controllers\Api\PushNotificationController::class, 'removeToken']);
        Route::post('/notifications/send', [App\Http\Controllers\Api\PushNotificationController::class, 'send']);
    });
});
