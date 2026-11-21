@extends('layouts.app')

@section('title', 'Khu vực khách hàng')

@section('content')
    <div class="card">
        <h1>Xin chào, {{ $user->name }}</h1>
        <p class="muted">Bạn đang ở khu vực Customer. Các URL /admin và /vendor sẽ bị từ chối với trang 403.</p>

        <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a class="btn" href="{{ route('customer.cart.index') }}">Giỏ hàng của tôi (S3-12)</a>
            <a class="btn btn-ghost" href="{{ route('customer.orders.index') }}">Đơn hàng của tôi (S3-13)</a>
            <a class="btn btn-ghost" href="{{ route('customer.payments.simulator') }}">Mô phỏng thanh toán &amp; Callback (S3-01)</a>
        </div>
    </div>
@endsection
