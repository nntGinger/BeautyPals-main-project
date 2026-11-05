@extends('layouts.app')

@section('title', 'Khu vực cửa hàng')

@section('content')
    <div class="card">
        <h1>{{ $vendor->store_name }}</h1>
        <p class="muted">Cửa hàng đã được duyệt. Đây là khu vực Vendor.</p>
        <p>Chủ sở hữu: {{ $user->name }} ({{ $user->email }})</p>
    </div>
@endsection
