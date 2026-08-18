<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class RunPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }
}