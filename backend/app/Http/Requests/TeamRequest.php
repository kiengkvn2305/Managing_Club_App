<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRoster', $this->attributes->get('club'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120', Rule::unique('teams')->where('club_id', $this->attributes->get('club')->id)], 'format' => ['required', Rule::in(['5', '7', '11'])]];
    }
}
