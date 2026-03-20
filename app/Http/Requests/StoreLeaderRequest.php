<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => 'required|exists:members,id|unique:leaders,member_id',
            'username' => 'required|string|max:255|unique:leaders,username',
            'password' => 'required|string|min:8',
        ];
    }
}
