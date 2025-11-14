<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telephone' => 'required|string',
            'code_pin' => 'required|string|min:4|max:6',
            'code_otp' => 'required|string|size:6',
        ];
    }
}