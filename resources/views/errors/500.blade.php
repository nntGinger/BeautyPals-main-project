@extends('layouts.app')

@section('title', '500 — Lỗi hệ thống')

@section('content')
    <div class="card" style="text-align: center; max-width: 600px; margin: 2.5rem auto; padding: 2.5rem 1.5rem;">
        <span class="badge badge-danger">HTTP 500 · LỖI HỆ THỐNG</span>
        <h1 style="margin-top: 0.75rem;">Đã xảy ra sự cố ngoài ý muốn</h1>
        <p class="muted">Hệ thống đang xử lý sự cố. Vui lòng thử lại sau ít phút hoặc quay về trang chủ.</p>
        <div style="margin-top: 1.5rem;">
            <a href="{{ route('home') }}" class="btn">Về trang chủ</a>
        </div>
    </div>
@endsection

