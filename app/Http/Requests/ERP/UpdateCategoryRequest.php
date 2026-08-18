<?php

namespace App\Http\Requests\ERP;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ];

        if ($id) {
            $rules['parent_id'][] = 'not_in:'.$id;
        }

        return $rules;
    }
}