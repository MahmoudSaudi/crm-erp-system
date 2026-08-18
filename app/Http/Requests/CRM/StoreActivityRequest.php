<?php

namespace App\Http\Requests\CRM;

use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', array_keys(ActivityType::list()))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'related_type' => ['nullable', 'string'],
            'related_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->input('related_type'))) {
            $this->merge([
                'related_type' => null,
                'related_id' => null,
            ]);
        }
    }
}