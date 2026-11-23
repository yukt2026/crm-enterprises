<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'department' => 'sometimes|nullable|string|max:100',
            'avatar' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
