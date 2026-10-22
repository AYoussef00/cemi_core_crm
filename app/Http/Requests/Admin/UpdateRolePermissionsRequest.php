<?php

namespace App\Http\Requests\Admin;

use App\Support\Authorization\RecordScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $this->user()?->can('update', $role) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scopeValues = array_column(RecordScope::cases(), 'value');

        return [
            'grants' => ['nullable', 'array'],
            'grants.*.enabled' => ['sometimes', 'boolean'],
            'grants.*.scope' => ['nullable', 'string', Rule::in($scopeValues)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grants.*.scope.in' => 'نطاق السجل غير صالح.',
        ];
    }
}
