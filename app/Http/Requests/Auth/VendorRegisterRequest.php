<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class VendorRegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email', ''))),
            'store_name' => trim((string) $this->input('store_name', '')),
            'tax_code' => trim((string) $this->input('tax_code', '')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'store_name' => ['required', 'string', 'max:150', 'unique:vendors,store_name'],
            'tax_code' => ['required', 'string', 'max:20', 'unique:vendors,tax_code'],
            'address' => ['nullable', 'string', 'max:255'],
            'license_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên người đại diện.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.required' => 'Vui lòng nhập số điện thoại liên hệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'store_name.required' => 'Vui lòng nhập tên cửa hàng.',
            'store_name.unique' => 'Tên cửa hàng đã được đăng ký.',
            'tax_code.required' => 'Vui lòng nhập mã số thuế.',
            'tax_code.unique' => 'Mã số thuế này đã được đăng ký.',
            'license_file.required' => 'Vui lòng tải lên giấy phép kinh doanh.',
            'license_file.mimes' => 'Giấy phép phải có định dạng PDF, JPG, PNG hoặc WEBP.',
            'license_file.max' => 'Dung lượng tệp giấy phép tối đa 5MB.',
        ];
    }
}
