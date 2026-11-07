<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class RegisterVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guest — no auth needed
    }

    public function rules(): array
    {
        return [
            // User fields
            'name'                  => ['required', 'string', 'max:120'],
            'email'                 => ['required', 'email', 'max:190', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'phone'                 => ['nullable', 'string', 'max:20'],
            // Vendor fields
            'store_name'            => ['required', 'string', 'max:150', 'unique:vendors,store_name'],
            'tax_code'              => ['required', 'string', 'max:20', 'unique:vendors,tax_code'],
            'address'               => ['nullable', 'string', 'max:255'],
            'license_file'          => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',   // 5 MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'license_file.mimes' => 'Giấy phép chỉ chấp nhận định dạng PDF, JPG hoặc PNG.',
            'license_file.max'   => 'Giấy phép không được vượt quá 5 MB.',
            'email.unique'       => 'Email này đã được sử dụng.',
            'tax_code.unique'    => 'Mã số thuế này đã được đăng ký.',
            'store_name.unique'  => 'Tên cửa hàng này đã được sử dụng.',
        ];
    }
}

