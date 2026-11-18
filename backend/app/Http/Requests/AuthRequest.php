<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        $passwordShape = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || strlen($value) > 72 || str_contains($value, "\0")) {
                $fail('Mật khẩu quá dài hoặc chứa ký tự không hợp lệ. Vui lòng dùng mật khẩu ngắn hơn.');
            }
        };
        if ($action === 'login') {
            return ['email' => ['required', 'email', 'max:255'], 'password' => ['bail', 'required', 'string', $passwordShape]];
        }
        $rules = ['name' => [$action === 'register' ? 'required' : 'sometimes', 'required', 'string', 'max:120'], 'email' => [$action === 'register' ? 'required' : 'sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()?->id)]];
        if ($action === 'register') {
            $rules['password'] = ['bail', 'required', 'string', $passwordShape, 'confirmed', Password::min(8)];
        }
        if ($action === 'profile' && $this->has('email') && $this->input('email') !== $this->user()->email) {
            $rules['current_password'] = ['bail', 'required', 'string', $passwordShape, 'current_password:web'];
        }

        return $rules;
    }
}
