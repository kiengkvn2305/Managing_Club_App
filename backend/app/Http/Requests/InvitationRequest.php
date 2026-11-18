<?php

namespace App\Http\Requests;

use App\Enums\ClubRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitationRequest extends FormRequest
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
        return ['email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(array_values(array_diff(array_column(ClubRole::cases(), 'value'), ['owner'])))]];
    }
}
