<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ];
    }
}