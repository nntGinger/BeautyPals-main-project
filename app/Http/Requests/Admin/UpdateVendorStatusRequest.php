<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,reject,suspend,unsuspend'],
            'reason' => ['required_if:action,reject', 'required_if:action,suspend', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.in'            => 'Hành động không hợp lệ.',
            'reason.required_if'   => 'Vui lòng nhập lý do khi từ chối hoặc khóa cửa hàng.',
        ];
    }

    /**
     * Map the action string to a target vendor status.
     */
    public function targetStatus(): string
    {
        return match ($this->input('action')) {
            'approve', 'unsuspend' => 'approved',
            'reject'               => 'rejected',
            'suspend'              => 'suspended',
        };
    }
}

