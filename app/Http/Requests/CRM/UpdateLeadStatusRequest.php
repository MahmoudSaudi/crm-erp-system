<?php

namespace App\Http\Requests\CRM;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:'.implode(',', array_keys(LeadStatus::list()))],
        ];
    }
}