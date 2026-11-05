@extends('layouts.app')

@section('title', 'Khu vực khách hàng')

@section('content')
    <div class="card">
        <h1>Xin chào, {{ $user->name }}</h1>
        <p class="muted">Bạn đang ở khu vực Customer. Các URL /admin và /vendor sẽ bị từ chối với trang 403.</p>
    </div>
@endsection
