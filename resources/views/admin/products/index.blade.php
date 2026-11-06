@extends('layouts.app')
@section('title', 'Duyệt sản phẩm')
@push('styles')
<style>
    main { max-width: 1400px; } main > .card.moderation { max-width: none; }
    .moderation-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; margin: 1rem 0; }
    .moderation-filters input, .moderation-filters select { padding: .7rem; border: 1px solid var(--line); border-radius: 8px; font: inherit; }
    .moderation-filters input { width: min(320px, 100%); } .moderation-table { width: 100%; border-collapse: collapse; }
    .moderation-table th, .moderation-table td { text-align: left; padding: .9rem; border-bottom: 1px solid var(--line); }
</style>
@endpush
@section('content')
@php
    $statuses = ['draft' => 'Nháp', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối', 'hidden' => 'Đã ẩn'];
@endphp
<section class="card moderation">
    <h1>Duyệt sản phẩm</h1>
    @if ($errors->any())<div class="flash-error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="GET" class="moderation-filters">
        <div><label for="q">Tên sản phẩm</label><input id="q" name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" maxlength="200"></div>
        <div><label for="status">Trạng thái</label><select id="status" name="status"><option value="">Tất cả</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <button type="submit">Lọc sản phẩm</button>
    </form>
    <p class="muted">{{ $products->total() }} sản phẩm</p>
    <div style="overflow-x: auto">
        <table class="moderation-table"><thead><tr><th>Sản phẩm</th><th>Cửa hàng</th><th>Thương hiệu</th><th>Trạng thái</th><th>Thuộc tính</th></tr></thead><tbody>
        @forelse ($products as $product)
            <tr><td><a href="{{ route('admin.products.show', $product) }}">{{ $product->name }}</a></td><td>{{ $product->vendor->store_name }}</td><td>{{ $product->brand?->name ?? 'Chưa chọn' }}</td><td><x-product-status :status="$product->status" /></td><td>{{ $product->skus_count }}</td></tr>
        @empty
            <tr><td colspan="5">Không có sản phẩm phù hợp.</td></tr>
        @endforelse
        </tbody></table>
    </div>
    @if ($products->hasPages())
        <nav aria-label="Phân trang sản phẩm" style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem">
            @if ($products->previousPageUrl())<a href="{{ $products->previousPageUrl() }}">Trang trước</a>@endif
            <span>Trang {{ $products->currentPage() }} / {{ $products->lastPage() }}</span>
            @if ($products->nextPageUrl())<a href="{{ $products->nextPageUrl() }}">Trang sau</a>@endif
        </nav>
    @endif
</section>
@endsection
