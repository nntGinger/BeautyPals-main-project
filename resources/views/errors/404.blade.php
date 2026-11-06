@extends('layouts.app')

@section('title', '404 — Không tìm thấy trang')

@section('content')
    <div class="card" style="text-align: center; max-width: 600px; margin: 2.5rem auto; padding: 2.5rem 1.5rem;">
        <span class="badge badge-warn">HTTP 404 · KHÔNG TÌM THẤY</span>
        <h1 style="margin-top: 0.75rem;">Trang bạn tìm kiếm không tồn tại</h1>
        <p class="muted">Liên kết có thể đã thay đổi hoặc sản phẩm không còn hiển thị trên hệ thống.</p>
        <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.75rem;">
            <a href="{{ route('catalog.index') }}" class="btn">Khám phá sản phẩm</a>
            <a href="{{ route('home') }}" class="btn btn-ghost">Về trang chủ</a>
        </div>
    </div>
@endsection

