<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRoster', $this->attributes->get('club'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $club = $this->attributes->get('club');

        return [
            'full_name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'required', 'string', 'max:120'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('player_profiles')->where('club_id', $club->id)->ignore($this->route('member'))],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ()-]{8,25}$/'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
            'jersey_number' => ['nullable', 'integer', 'between:0,999'],
            'position' => ['nullable', Rule::in(['GK', 'FIXO', 'ALA', 'PIVOT', 'CB', 'LB', 'RB', 'CDM', 'CM', 'CAM', 'LM', 'RM', 'LW', 'RW', 'ST', 'CF'])],
            'preferred_foot' => ['nullable', Rule::in(['left', 'right', 'both'])],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'injured'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
