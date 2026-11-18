<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(AuthRequest $request): mixed
    {
        $user = User::create($request->validated());
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        event(new Registered($user));

        return response()->json(['data' => $user], 201);
    }

    public function login(AuthRequest $request): mixed
    {
        if (! Auth::guard('web')->attempt($request->validated() + ['is_active' => true])) {
            throw ValidationException::withMessages(['email' => ['Thông tin đăng nhập không chính xác.']]);
        }
        $request->session()->regenerate();

        return response()->json(['data' => $request->user()]);
    }

    public function user(Request $request): mixed
    {
        return response()->json(['data' => $request->user()]);
    }

    public function profile(AuthRequest $request): mixed
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));
        $changedEmail = $user->isDirty('email');
        if ($changedEmail) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($changedEmail) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['data' => $request->user()->fresh()]);
    }

    public function logout(Request $request): mixed
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function changePassword(AuthRequest $request): mixed
    {
        $user = $request->user();
        DB::transaction(function () use ($user, $request) {
            $user->forceFill(['password' => Hash::make($request->validated('password')), 'remember_token' => Str::random(60)])->save();
            if (config('session.driver') === 'database') DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        });
        $request->session()->regenerate();
        return response()->noContent();
    }

    public function verification(Request $request): mixed
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        $message = in_array(config('mail.default'), ['log', 'array'], true)
            ? 'Yêu cầu xác minh đã được ghi nhận. Môi trường hiện tại chưa bật gửi email; vui lòng liên hệ quản trị viên.'
            : 'Liên kết xác minh sẽ được gửi tới địa chỉ email của bạn.';

        return response()->json(['data' => ['message' => $message]]);
    }
}
