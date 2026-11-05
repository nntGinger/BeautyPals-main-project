@extends('layouts.app')

@section('title', '403 — Không có quyền truy cập')

@section('content')
    <div class="card error-card" style="text-align: center; max-width: 640px; margin: 2.5rem auto; padding: 3rem 2rem;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 84px; height: 84px; border-radius: 50%; background: #fde7e5; color: var(--accent-dark); margin-bottom: 1.5rem;">
            <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
            </svg>
        </div>

        <div>
            <span style="display: inline-block; padding: 0.25rem 0.85rem; background: #fbeae8; color: var(--danger); border-radius: 999px; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.8rem;">
                HTTP 403 · Quyền truy cập bị từ chối
            </span>
        </div>

        <h1 style="color: var(--ink); font-size: 1.75rem; margin-bottom: 0.8rem;">
            Bạn không được phép vào trang này
        </h1>

        <p style="color: var(--muted); font-size: 1rem; line-height: 1.6; max-width: 500px; margin: 0 auto 1.5rem;">
            {{ $exception?->getMessage() ?: 'Khu vực này yêu cầu quyền hạn đặc biệt hoặc cửa hàng của bạn chưa được duyệt.' }}
        </p>

        @auth
            <div style="background: #faf6f3; border: 1px solid var(--line); border-radius: 12px; padding: 0.85rem 1.2rem; margin: 0 auto 1.8rem; max-width: 460px; font-size: 0.92rem; color: var(--muted); text-align: left;">
                <span>Đang đăng nhập: <strong>{{ auth()->user()->name }}</strong> (Vai trò: <span style="text-transform: capitalize; color: var(--accent-dark); font-weight: 600;">{{ auth()->user()->role }}</span>)</span>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                <a href="{{ auth()->user()->homePath() }}" class="btn">
                    Về khu vực của tôi &rarr;
                </a>
                <a href="{{ route('home') }}" class="btn btn-ghost">
                    Về trang chủ
                </a>
            </div>
        @else
            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                <a href="{{ route('login') }}" class="btn">
                    Đăng nhập tài khoản &rarr;
                </a>
                <a href="{{ route('home') }}" class="btn btn-ghost">
                    Về trang chủ
                </a>
            </div>
        @endauth

        <p class="meta" style="margin-top: 2rem; font-size: 0.82rem; color: var(--muted);">
            Cần hỗ trợ về tài khoản? Vui lòng liên hệ <a href="mailto:support@beautypals.test">support@beautypals.test</a>
        </p>
    </div>
@endsection
