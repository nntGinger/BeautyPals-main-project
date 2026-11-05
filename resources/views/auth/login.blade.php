@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
    <div class="card">
        <h1>Đăng nhập</h1>
        <p class="muted">Tài khoản nhập sai {{ config('auth.lockout.max_attempts', 5) }} lần sẽ tạm hoãn đăng nhập trong {{ (int) ceil(config('auth.lockout.decay_seconds', 900) / 60) }} phút. Các tài khoản khác vẫn đăng nhập bình thường.</p>

        @if ($lockoutSeconds > 0)
            <div class="flash-error" data-login-countdown="{{ $lockoutSeconds }}" data-lockout-email="{{ $lockoutEmail }}">
                <span data-lockout-message>Tài khoản này tạm hoãn đăng nhập. Thử lại sau </span>
                <strong class="lockout-clock" role="timer" aria-label="Thời gian chờ đăng nhập còn lại" data-lockout-clock>{{ sprintf('%02d:%02d', intdiv($lockoutSeconds, 60), $lockoutSeconds % 60) }}</strong>
                <p class="meta" data-lockout-note>Bạn vẫn có thể đăng nhập bằng tài khoản khác.</p>
                <noscript>Thời gian được cập nhật khi bạn tải lại trang.</noscript>
            </div>
        @endif

        @if ($errors->any() && ($lockoutSeconds <= 0 || strtolower(old('email', '')) !== strtolower($lockoutEmail)))
            <div class="flash-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="stack">
            @csrf
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $lockoutEmail) }}" required autofocus autocomplete="username">
            </div>
            <div>
                <label for="password">Mật khẩu</label>
                <x-password-input id="password" name="password" />
            </div>
            <label style="font-weight: 500;">
                <input type="checkbox" name="remember" value="1" style="width: auto;"> Ghi nhớ đăng nhập
            </label>
            <button type="submit">Đăng nhập</button>
        </form>
        <p class="meta">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký Customer</a></p>
    </div>
@endsection
