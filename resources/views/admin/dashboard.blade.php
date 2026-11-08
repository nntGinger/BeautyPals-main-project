@extends('layouts.app')

@section('title', 'Khu vực quản trị')

@section('content')
    <div class="card">
        <h1>Bảng điều khiển Admin</h1>
        <p class="muted">Chỉ vai trò admin mới vào được /admin.</p>
        <ul>
            <li>Người dùng: {{ $userCount }}</li>
            <li>Cửa hàng: {{ $vendorCount }}</li>
            <li>Vendor chờ duyệt: {{ $pendingVendors }}</li>
        </ul>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--line);">
            <a href="{{ route('admin.vendors.index') }}" class="btn" style="display:inline-block;background:var(--accent);color:#fff;padding:.6rem 1.2rem;border-radius:8px;font-weight:600;text-decoration:none;">
                Quản lý & Duyệt cửa hàng Vendor →
            </a>
        </div>
    </div>
@endsection
