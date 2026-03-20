<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:members,email,' . $this->route('member'),
            'phone_number' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'marital_status' => 'nullable|string|max:50',
            'occupation' => 'nullable|string|max:255',
            'member_since' => 'nullable|date',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
