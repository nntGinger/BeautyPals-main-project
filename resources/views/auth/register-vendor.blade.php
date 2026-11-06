@extends('layouts.app')

@section('title', 'Đăng ký mở gian hàng — BeautyPals')

@section('content')
    <div class="card" style="max-width: 680px; margin: 0 auto;">
        <h1>Đăng ký mở gian hàng đối tác</h1>
        <p class="muted">Hoàn tất hồ sơ doanh nghiệp và đính kèm giấy phép kinh doanh để đội ngũ Admin xét duyệt.</p>

        <form method="POST" action="{{ route('register.vendor.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <h2>1. Thông tin người đại diện</h2>
            <div class="form-grid">
                <div>
                    <label for="name">Họ và tên người đại diện</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone">Số điện thoại liên hệ</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required autocomplete="tel">
                    @error('phone') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="email">Email đăng nhập</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="form-grid">
                <div>
                    <label for="password">Mật khẩu (tối thiểu 8 ký tự)</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation">Xác nhận mật khẩu</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>

            <h2 style="margin-top: 1.5rem;">2. Hồ sơ cửa hàng & Giấy phép</h2>
            <div class="form-grid">
                <div>
                    <label for="store_name">Tên gian hàng</label>
                    <input id="store_name" type="text" name="store_name" value="{{ old('store_name') }}" required>
                    @error('store_name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tax_code">Mã số thuế</label>
                    <input id="tax_code" type="text" name="tax_code" value="{{ old('tax_code') }}" required>
                    @error('tax_code') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="address">Địa chỉ kho / cửa hàng</label>
                <input id="address" type="text" name="address" value="{{ old('address') }}">
                @error('address') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="license_file">Giấy phép kinh doanh (PDF, JPG, PNG, WEBP — tối đa 5MB)</label>
                <input id="license_file" type="file" name="license_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                @error('license_file') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; align-items: center;">
                <button type="submit">Gửi hồ sơ đăng ký gian hàng</button>
                <a href="{{ route('register') }}" class="btn btn-ghost">Đăng ký khách mua</a>
            </div>
        </form>
    </div>
@endsection

