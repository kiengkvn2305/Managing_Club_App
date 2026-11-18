<?php
namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private function shape(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || strlen($value) > 72 || str_contains($value, "\0")) $fail('Mật khẩu quá dài hoặc chứa ký tự không hợp lệ.');
        };
    }

    public function requestLink(Request $request): mixed
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        try { Password::broker()->sendResetLink(['email' => mb_strtolower(trim($data['email'])), 'is_active' => true]); }
        catch (\Throwable $error) { report($error); }
        return response()->json(['message' => 'Nếu tài khoản hợp lệ và dịch vụ email đã được cấu hình, hướng dẫn sẽ được gửi đến địa chỉ này.']);
    }

    public function reset(Request $request): mixed
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255'], 'token' => ['required', 'string', 'max:255'], 'password' => ['bail', 'required', 'string', $this->shape(), 'confirmed', PasswordRule::min(8)]]);
        $data['email'] = mb_strtolower(trim($data['email'])); $data['is_active'] = true;
        DB::transaction(function () use ($data) {
            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))->where('email', $data['email'])->lockForUpdate()->first();
            $result = Password::broker()->reset($data, function ($user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                if (config('session.driver') === 'database') DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                event(new PasswordReset($user));
            });
            if ($result !== Password::PASSWORD_RESET) throw ValidationException::withMessages(['token' => ['Liên kết không hợp lệ hoặc đã hết hạn. Hãy yêu cầu liên kết mới.']]);
        });
        return response()->noContent();
    }
}
