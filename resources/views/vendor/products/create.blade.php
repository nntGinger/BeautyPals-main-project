@extends('layouts.app')
@section('title', 'Thêm sản phẩm')
@section('content')
    <section class="card product-editor">
        <p><a href="{{ route('vendor.products.index') }}">← Sản phẩm của tôi</a></p>
        <h1>Thêm sản phẩm</h1>
        <p class="muted">Lưu thông tin sản phẩm và các thuộc tính để tạo bản nháp.</p>
        <form method="POST" action="{{ route('vendor.products.store') }}" enctype="multipart/form-data" data-product-form>
            @csrf
            @include('vendor.products._form')
        </form>
    </section>
@endsection
