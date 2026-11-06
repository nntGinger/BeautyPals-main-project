@extends('layouts.app')
@section('title', 'Sửa sản phẩm')
@section('content')
    <section class="card product-editor">
        <p><a href="{{ route('vendor.products.show', $product) }}">← Chi tiết sản phẩm</a></p>
        <h1>Sửa sản phẩm</h1>
        <p>Trạng thái hiện tại: <x-product-status :status="$product->status" /></p>
        <p class="muted">Lưu thay đổi sẽ giữ nguyên trạng thái hiện tại. Hãy lưu xong trước khi mở chi tiết để gửi duyệt.</p>
        @if (in_array($product->status, ['rejected', 'hidden'], true))
            <p class="error">{{ $product->status === 'hidden' ? 'Lý do ẩn' : 'Lý do từ chối' }}: {{ $product->reject_reason }}</p>
        @endif
        <form method="POST" action="{{ route('vendor.products.update', $product) }}" enctype="multipart/form-data" data-product-form>
            @csrf
            @method('PUT')
            @include('vendor.products._form')
        </form>
        @if (in_array($product->status, ['draft', 'rejected', 'hidden'], true))
            <p><a href="{{ route('vendor.products.show', $product) }}">Mở chi tiết để gửi duyệt</a> sau khi đã lưu thay đổi.</p>
        @endif
    </section>
@endsection
