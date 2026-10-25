<?php

namespace App\Http\Requests\Employees;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route middleware already checks the employees.manage permission.
        return true;
    }

    public function rules(): array
    {
        return [
            // Empty means "use the suggested number".
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:employees,employee_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'hire_date' => ['required', 'date'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            // One login account belongs to at most one employee.
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:employees,user_id'],
            'employment_status' => ['sometimes', Rule::in(Employee::STATUSES)],
        ];
    }
}