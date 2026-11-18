<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->route('club') || $this->user()->can('update', $this->attributes->get('club'));
    }

    public function rules(): array
    {
        return ['name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:5000'], 'city' => ['nullable', 'string', 'max:120'], 'home_ground' => ['nullable', 'string', 'max:160'], 'format' => ['sometimes', 'required', Rule::in(['5', '7', '11'])], 'formats' => ['sometimes', 'required', 'array', 'min:1', 'max:3'], 'formats.*' => ['required', 'distinct', Rule::in(['5', '7', '11'])], 'status' => ['sometimes', Rule::in(['active', 'archived'])], 'is_discoverable' => ['sometimes', 'boolean']];
    }
}
