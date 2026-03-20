<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => 'required|exists:groups,id',
            'date' => 'required|date',
            'attendances' => 'required|array|min:1',
            'attendances.*.member_id' => 'required|exists:members,id',
            'attendances.*.attended' => 'required|boolean',
            'attendances.*.is_first_timer' => 'boolean',
            'attendances.*.is_visitor' => 'boolean',
        ];
    }
}
