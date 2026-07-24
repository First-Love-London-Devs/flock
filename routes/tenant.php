<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BirthdayController;
use App\Http\Controllers\Api\BishopController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GovernorController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupTypeController;
use App\Http\Controllers\Api\LeaderController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NonMemberController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UnderstandingCampaignController;
use App\Http\Controllers\Web\AttendanceCounterController;
use App\Http\Controllers\Web\WelcomeFormController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\InitializeLeaderScope;
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

    // Public first-timer / convert capture form (Understanding Campaign), per Stream.
    Route::get('/welcome', [WelcomeFormController::class, 'index'])->name('welcome.index');
    Route::get('/welcome/{stream}', [WelcomeFormController::class, 'show'])->name('welcome-form.show');
    Route::post('/welcome/{stream}', [WelcomeFormController::class, 'store'])->name('welcome-form.store');

    // Public ushers attendance counter (kiosk tap-counter), per Stream.
    Route::get('/attendance-counter', [AttendanceCounterController::class, 'index'])->name('attendance-counter.index');
    Route::get('/attendance-counter/{stream}', [AttendanceCounterController::class, 'show'])->name('attendance-counter.show');
    Route::post('/attendance-counter/{stream}/increment', [AttendanceCounterController::class, 'increment'])->name('attendance-counter.increment');
    Route::post('/attendance-counter/{stream}/counts', [AttendanceCounterController::class, 'counts'])->name('attendance-counter.counts');
});

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api/v1')->group(function () {
    // Public routes (no auth required)
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/branding', [BrandingController::class, 'index']);

    // Protected routes
    Route::middleware(['auth:sanctum', InitializeLeaderScope::class])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Group Types
        Route::apiResource('group-types', GroupTypeController::class);

        // Groups
        Route::get('/groups/{id}/children', [GroupController::class, 'children']);
        Route::get('/groups/{id}/ancestors', [GroupController::class, 'ancestors']);
        Route::get('/groups/{id}/members', [GroupController::class, 'members']);
        Route::get('/groups/{id}/hierarchy', [GroupController::class, 'hierarchy']);
        Route::apiResource('groups', GroupController::class);

        // Members
        Route::get('/members/search', [MemberController::class, 'search']);
        Route::post('/members/{id}/assign-group', [MemberController::class, 'assignGroup']);
        Route::delete('/members/{id}/remove-group/{groupId}', [MemberController::class, 'removeGroup']);
        Route::apiResource('members', MemberController::class);

        // Non-Members
        Route::apiResource('non-members', NonMemberController::class);

        // Leaders
        Route::post('/leaders/{id}/assign-role', [LeaderController::class, 'assignRole']);
        Route::delete('/leaders/{id}/remove-role/{roleId}', [LeaderController::class, 'removeRole']);
        Route::apiResource('leaders', LeaderController::class);

        // Attendance
        Route::post('/attendance/submit', [AttendanceController::class, 'submit']);
        Route::get('/attendance/group/{groupId}', [AttendanceController::class, 'groupHistory']);
        Route::get('/attendance/defaulters/{parentGroupId}/{date}', [AttendanceController::class, 'defaulters']);
        Route::get('/attendance/{summaryId}', [AttendanceController::class, 'show']);
        Route::put('/attendance/{summaryId}', [AttendanceController::class, 'update']);
        Route::delete('/attendance/{summaryId}', [AttendanceController::class, 'destroy']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/attendance-trends', [DashboardController::class, 'attendanceTrends']);
        Route::get('/dashboard/defaulters', [DashboardController::class, 'defaulters']);

        // Settings
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings/{key}', [SettingController::class, 'update']);

        // Birthdays
        Route::get('/birthdays/upcoming', [BirthdayController::class, 'upcoming']);

        // Push Notifications
        Route::post('/push-token', [PushNotificationController::class, 'storeToken']);
        Route::delete('/push-token', [PushNotificationController::class, 'removeToken']);
        Route::post('/notifications/send', [PushNotificationController::class, 'send']);

        Route::prefix('governor')->middleware([CheckRole::class.':governor'])->group(function () {
            Route::get('dashboard', [GovernorController::class, 'dashboard']);
            Route::get('groups', [GovernorController::class, 'groups']);
            Route::get('groups/{id}', [GovernorController::class, 'groupDetail'])->whereNumber('id');
            Route::get('members', [GovernorController::class, 'members']);
            Route::get('attendance', [GovernorController::class, 'attendance']);
            Route::get('attendance-trends', [GovernorController::class, 'attendanceTrend']);
            Route::get('attendance/pulse', [GovernorController::class, 'attendancePulse']);
            Route::get('first-timers', [GovernorController::class, 'firstTimers']);
        });

        Route::prefix('bishop')->middleware([CheckRole::class.':bishop'])->group(function () {
            Route::get('governors', [BishopController::class, 'governors']);
            Route::get('attendance', [BishopController::class, 'attendance']);
            Route::get('attendance-counter', [BishopController::class, 'attendanceCounter']);
            Route::get('summary', [BishopController::class, 'summary']);
            Route::get('members', [BishopController::class, 'members']);
            Route::get('governors/{govId}/dashboard', [BishopController::class, 'governorDashboard'])->whereNumber('govId');
            Route::get('governors/{govId}/groups', [BishopController::class, 'governorGroups'])->whereNumber('govId');
            Route::get('governors/{govId}/groups/{groupId}', [BishopController::class, 'groupDetail'])->whereNumber('govId')->whereNumber('groupId');
            Route::get('governors/{govId}/attendance', [BishopController::class, 'governorAttendance'])->whereNumber('govId');
        });

        Route::prefix('admin')->middleware([CheckRole::class.':admin'])->group(function () {
            Route::get('members', [AdminController::class, 'listMembers']);
            Route::get('members/{id}', [AdminController::class, 'showMember'])->whereNumber('id');
            Route::post('members', [AdminController::class, 'createMember']);
            Route::put('members/{id}', [AdminController::class, 'updateMember'])->whereNumber('id');
            Route::put('members/{id}/groups', [AdminController::class, 'updateMemberGroups'])->whereNumber('id');
            Route::delete('members/{id}', [AdminController::class, 'deactivateMember'])->whereNumber('id');

            Route::get('sontas', [AdminController::class, 'listSontas']);
            Route::get('bacentas', [AdminController::class, 'listBacentas']);
            Route::get('bacentas/{id}', [AdminController::class, 'showBacenta'])->whereNumber('id');
            Route::post('bacentas', [AdminController::class, 'createBacenta']);
            Route::put('bacentas/{id}', [AdminController::class, 'updateBacenta'])->whereNumber('id');
            Route::delete('bacentas/{id}', [AdminController::class, 'deactivateBacenta'])->whereNumber('id');
        });

        Route::prefix('understanding-campaigns')->middleware([CheckRole::class.':understanding-campaign'])->group(function () {
            Route::get('/', [UnderstandingCampaignController::class, 'index']);
            Route::get('/assignable-groups', [UnderstandingCampaignController::class, 'assignableGroups']);
            Route::patch('/{id}/assign', [UnderstandingCampaignController::class, 'assign'])->whereNumber('id');
        });
    });
});
