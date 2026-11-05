@extends('layouts.app')

@section('title', 'Chờ duyệt cửa hàng')

@section('content')
    <div class="card">
        <h1>Cửa hàng chưa được duyệt</h1>
        <p class="muted">
            Tài khoản vendor của bạn đăng nhập được, nhưng chưa vào được khu vực /vendor
            cho đến khi quản trị viên duyệt cửa hàng.
        </p>
        @if ($vendor)
            <p>Cửa hàng: <strong>{{ $vendor->store_name }}</strong> — trạng thái: {{ $vendor->status }}</p>
        @endif
    </div>
@endsection
