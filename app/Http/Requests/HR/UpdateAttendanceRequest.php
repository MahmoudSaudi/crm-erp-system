<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attendance = $this->route('attendance');

        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date', 'unique:attendance,date,'.$attendance->id.',id,employee_id,'.$this->integer('employee_id')],
            'status' => ['required', 'in:present,absent,late,leave'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
        ];
    }
}