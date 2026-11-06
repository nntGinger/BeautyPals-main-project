@extends('layouts.app')

@section('title', 'Khu vực khách hàng')

@section('content')
    <div class="card">
        <h1>Xin chào, {{ $user->name }}</h1>
        <p class="muted">Bạn đang ở khu vực Customer. Các URL /admin và /vendor sẽ bị từ chối với trang 403.</p>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
            <a href="{{ route('catalog.index') }}" class="btn">Khám phá sản phẩm</a>
            <a href="{{ route('customer.cart.index') }}" class="btn btn-ghost">Giỏ hàng của tôi</a>
            <a href="{{ route('customer.orders.index') }}" class="btn btn-ghost">Đơn hàng của tôi</a>
            <a href="{{ route('customer.addresses.index') }}" class="btn btn-ghost">Sổ địa chỉ giao hàng</a>
        </div>
    </div>
@endsection
