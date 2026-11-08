@extends('layouts.app')

@section('title', 'Đăng ký cửa hàng – BeautyPals')

@section('content')
<div class="card" style="max-width:560px;margin:0 auto;">
    <h1>Đăng ký cửa hàng</h1>
    <p class="muted">Điền thông tin để mở cửa hàng trên BeautyPals. Hồ sơ sẽ được Admin xét duyệt.</p>

    @include('partials.flash')

    <form method="POST" action="{{ route('vendor.register.store') }}" enctype="multipart/form-data">
        @csrf

        <fieldset style="border:0;padding:0;margin-bottom:1.2rem;">
            <legend style="font-weight:700;font-size:1rem;margin-bottom:.5rem;">Thông tin tài khoản</legend>

            <label for="name">Họ và tên *</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="email">Email *</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            @error('email') <p class="error">{{ $message }}</p> @enderror

            <label for="phone">Số điện thoại</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone') }}">
            @error('phone') <p class="error">{{ $message }}</p> @enderror

            <label for="password">Mật khẩu *</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password') <p class="error">{{ $message }}</p> @enderror

            <label for="password_confirmation">Nhập lại mật khẩu *</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>
        </fieldset>

        <fieldset style="border:0;padding:0;">
            <legend style="font-weight:700;font-size:1rem;margin-bottom:.5rem;">Thông tin cửa hàng</legend>

            <label for="store_name">Tên cửa hàng *</label>
            <input id="store_name" type="text" name="store_name" value="{{ old('store_name') }}" required>
            @error('store_name') <p class="error">{{ $message }}</p> @enderror

            <label for="tax_code">Mã số thuế *</label>
            <input id="tax_code" type="text" name="tax_code" value="{{ old('tax_code') }}" required>
            @error('tax_code') <p class="error">{{ $message }}</p> @enderror

            <label for="address">Địa chỉ kinh doanh</label>
            <input id="address" type="text" name="address" value="{{ old('address') }}">
            @error('address') <p class="error">{{ $message }}</p> @enderror

            <label for="license_file">Giấy phép kinh doanh * <span class="muted" style="font-weight:400;">(PDF / JPG / PNG, tối đa 5 MB)</span></label>
            <input id="license_file" type="file" name="license_file" accept=".pdf,.jpg,.jpeg,.png" required style="padding:.4rem .2rem;">
            @error('license_file') <p class="error">{{ $message }}</p> @enderror
        </fieldset>

        <button type="submit" style="margin-top:1.2rem;width:100%;">Gửi hồ sơ đăng ký</button>
    </form>

    <p style="margin-top:1rem;text-align:center;" class="muted">
        Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a>
    </p>
</div>
@endsection

