<?php

namespace App\Http\Requests\CRM;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:'.implode(',', array_keys(LeadStatus::list()))],
            'value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}