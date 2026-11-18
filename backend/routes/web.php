<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\LineupController;
use App\Http\Controllers\ClubMembershipController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\ActivityController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureClubAccess;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'FCMS PRO API', 'health' => '/up']));
// Keep web session and CSRF middleware on all API routes, including login/register.
Route::prefix('api/v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
        Route::get('auth/user', [AuthController::class, 'user']);
        Route::patch('auth/profile', [AuthController::class, 'profile']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/verification-notification', [AuthController::class, 'verification'])->middleware('throttle:3,1');
        Route::get('auth/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();

            return response()->json(['data' => ['message' => 'Email đã xác minh. Quay lại ứng dụng và tải lại tài khoản.']]);
        })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::get('clubs', [ClubController::class, 'index']);
        Route::post('clubs', [ClubController::class, 'store'])->middleware('throttle:30,1');
        Route::post('invitations/accept', [InvitationController::class, 'accept'])->middleware('throttle:10,1');
        Route::prefix('clubs/{club}')->whereNumber('club')->middleware(EnsureClubAccess::class)->group(function () {
            Route::patch('/', [ClubController::class, 'update']);
            Route::delete('/', [ClubController::class, 'destroy']);
            Route::get('dashboard', [ClubController::class, 'dashboard']);
            Route::get('activities', [ActivityController::class, 'index']);
            Route::post('activities/celebrations', [ActivityController::class, 'celebration']);
            Route::post('activities/trainings', [ActivityController::class, 'training']);
            Route::patch('activities/trainings/{training}/attendance', [ActivityController::class, 'attendance'])->whereNumber('training');
            Route::post('activities/awards', [ActivityController::class, 'award']);
            Route::get('finance', [FinanceController::class, 'index']);
            Route::post('finance', [FinanceController::class, 'store']);
            Route::get('finance/{entry}/receipt', [FinanceController::class, 'receipt'])->whereNumber('entry');
            Route::get('teams', [ClubController::class, 'teams']);
            Route::get('team-manager-candidates', [ClubController::class, 'managerCandidates']);
            Route::post('teams', [ClubController::class, 'storeTeam']);
            Route::patch('teams/{team}', [ClubController::class, 'renameTeam'])->whereNumber('team');
            Route::delete('teams/{team}', [ClubController::class, 'destroyTeam'])->whereNumber('team');
            Route::get('club-memberships', [ClubMembershipController::class, 'index']);
            Route::patch('club-memberships/{membership}', [ClubMembershipController::class, 'update'])->whereNumber('membership');
            Route::patch('teams/{team}/captain', [LineupController::class, 'captain'])->whereNumber('team');
            Route::patch('teams/{team}/managers', [ClubController::class, 'setManagers'])->whereNumber('team');
            Route::patch('teams/{team}/players', [ClubController::class, 'setTeamPlayers'])->whereNumber('team');
            Route::delete('teams/{team}/players/{player}', [ClubController::class, 'removeTeamPlayer'])->whereNumber('team')->whereNumber('player');
            Route::patch('teams/{team}/players/{player}/jersey', [ClubController::class, 'setTeamJersey'])->whereNumber('team')->whereNumber('player');
            Route::patch('teams/{team}/players/{player}/position', [ClubController::class, 'setTeamPosition'])->whereNumber('team')->whereNumber('player');
            Route::get('lineups', [LineupController::class, 'index']);
            Route::post('lineups', [LineupController::class, 'save']);
            Route::patch('lineups/{lineup}', [LineupController::class, 'save'])->whereNumber('lineup');
            Route::get('members', [PlayerController::class, 'index']);
            Route::post('members', [PlayerController::class, 'store']);
            Route::post('members/import', [PlayerController::class, 'import']);
            Route::post('members/import/preview', [MemberImportController::class, 'preview']);
            Route::post('members/import/commit', [MemberImportController::class, 'commit']);
            Route::get('members/{member}', [PlayerController::class, 'show'])->whereNumber('member');
            Route::patch('members/{member}', [PlayerController::class, 'update'])->whereNumber('member');
            Route::delete('members/{member}', [PlayerController::class, 'destroy'])->whereNumber('member');
            Route::post('invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1');
        });
    });
});
