<?php

namespace App\Http\Requests\ERP;

use App\Models\ERP\Expense;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:'.implode(',', Expense::categories())],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'is_reimbursable' => ['nullable', 'boolean'],
        ];
    }
}