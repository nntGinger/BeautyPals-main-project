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
    </div>
@endsection
