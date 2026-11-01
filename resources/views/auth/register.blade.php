@extends('layouts.app')

@section('title', 'Đăng ký Customer')

@section('content')
    <div class="card">
        <h1>Đăng ký tài khoản Customer</h1>
        <p class="muted">Tạo tài khoản để mua sắm tại BeautyPals. Mật khẩu cần ít nhất 8 ký tự.</p>

        <form method="POST" action="{{ route('register') }}" class="stack">
            @csrf
            <div>
                <label for="name">Họ tên</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone">Số điện thoại (không bắt buộc)</label>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                @error('phone') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password">Mật khẩu</label>
                <x-password-input id="password" name="password" autocomplete="new-password" minlength="8" />
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation">Xác nhận mật khẩu</label>
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" label="xác nhận mật khẩu" minlength="8" />
            </div>
            <button type="submit">Tạo tài khoản</button>
        </form>
        <p class="meta">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
    </div>
@endsection
