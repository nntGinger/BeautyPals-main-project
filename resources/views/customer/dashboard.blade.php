@extends('layouts.app')

@section('title', 'Khu vực khách hàng')

@section('content')
    <div class="card">
        <h1>Xin chào, {{ $user->name }}</h1>
        <p class="muted">Bạn đang ở khu vực Customer. Các URL /admin và /vendor sẽ bị từ chối với trang 403.</p>

        <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a class="btn" href="{{ route('customer.payments.simulator') }}">Mô phỏng thanh toán &amp; Callback (S3-01)</a>
        </div>
    </div>
@endsection
