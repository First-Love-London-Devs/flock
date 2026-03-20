<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'group_type_id' => 'required|exists:group_types,id',
            'parent_id' => 'nullable|exists:groups,id',
            'leader_id' => 'nullable|exists:leaders,id',
            'description' => 'nullable|string',
            'meeting_day' => 'nullable|integer|min:0|max:6',
            'meeting_time' => 'nullable|date_format:H:i',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
