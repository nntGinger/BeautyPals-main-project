@extends('layouts.app')
@section('title', 'Xét duyệt sản phẩm')
@section('content')
@php
    $statuses = ['draft' => 'Nháp', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối', 'hidden' => 'Đã ẩn'];
    $actions = ['product.approve' => 'Duyệt', 'product.reject' => 'Từ chối', 'product.hide' => 'Ẩn', 'product.restore' => 'Hiện lại', 'product.submit' => 'Gửi duyệt'];
@endphp
<section class="card" style="overflow-wrap:anywhere">
    <p><a href="{{ route('admin.products.index') }}">← Danh sách sản phẩm</a></p>
    <h1>{{ $product->name }}</h1>
    <p>Cửa hàng: {{ $product->vendor->store_name }} · Trạng thái: <x-product-status :status="$product->status" /></p>
    @if ($errors->any())<div class="flash-error" role="alert"><strong>Chưa thực hiện được thao tác:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if ($product->reject_reason)<p class="error">Lý do: {{ $product->reject_reason }}</p>@endif
    <p>Danh mục: {{ $product->category?->name }} · Thương hiệu: {{ $product->brand?->name ?? 'Chưa chọn' }}</p>
    <p style="white-space:pre-wrap">{{ $product->description }}</p>
    <div style="display:flex; gap:.75rem; flex-wrap:wrap">
        @foreach ($product->images as $image)
            @if (\Illuminate\Support\Facades\Storage::disk('public')->exists($image->path))<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $product->name }}" width="140" height="140" style="object-fit:contain; max-width:100%">@endif
        @endforeach
    </div>
    <h2>Thuộc tính</h2>
    @forelse ($product->skus as $sku)<p>{{ $sku->variant_name }} · {{ $sku->sku_code }} · {{ number_format($sku->price, 0, ',', '.') }} ₫ · {{ $sku->is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}</p>@empty<p>Chưa có thuộc tính.</p>@endforelse
    <h2>Thành phần</h2>
    <ol>@forelse ($product->ingredients as $ingredient)<li>{{ $ingredient->display_name }} ({{ $ingredient->inci_name }})</li>@empty<li>Chưa có thành phần.</li>@endforelse</ol>
    @if ($product->status === 'pending')
        <form method="POST" action="{{ route('admin.products.approve', $product) }}">@csrf<button type="submit">Duyệt sản phẩm</button></form>
    @endif
    @if (in_array($product->status, ['pending', 'approved'], true))
        <form method="POST" action="{{ route($product->status === 'pending' ? 'admin.products.reject' : 'admin.products.hide', $product) }}" style="margin-top:1rem">
            @csrf
            <label for="reason">{{ $product->status === 'pending' ? 'Lý do từ chối' : 'Lý do ẩn' }} *</label>
            <textarea id="reason" name="reason" maxlength="255" required style="width:100%; min-height:90px; padding:.75rem; font:inherit; border:1px solid var(--line); border-radius:10px">{{ is_string(old('reason')) ? old('reason') : '' }}</textarea>
            <p><button class="btn-ghost" type="submit">{{ $product->status === 'pending' ? 'Từ chối sản phẩm' : 'Ẩn sản phẩm' }}</button></p>
        </form>
    @endif
    @if ($product->status === 'hidden')
        <form method="POST" action="{{ route('admin.products.restore', $product) }}">@csrf<button type="submit">Hiện lại sản phẩm</button></form>
    @endif
    @if (in_array($product->status, ['draft', 'rejected'], true))<p class="muted">Chờ Vendor gửi duyệt trước khi xét duyệt sản phẩm.</p>@endif
    <h2>Lịch sử xét duyệt</h2>
    @forelse ($history as $entry)
        <p>{{ $actions[$entry->action] ?? $entry->action }} · {{ $entry->actor?->name ?? 'Tài khoản đã xóa' }} · {{ $entry->created_at }}<br>{{ $statuses[$entry->old_values['status'] ?? ''] ?? '?' }} → {{ $statuses[$entry->new_values['status'] ?? ''] ?? '?' }}@if ($entry->new_values['reject_reason'] ?? null)<br>Lý do: {{ $entry->new_values['reject_reason'] }}@endif</p>
    @empty<p class="muted">Chưa có lịch sử xét duyệt.</p>@endforelse
    @if ($history->hasPages())<nav aria-label="Phân trang lịch sử">@if ($history->previousPageUrl())<a href="{{ $history->previousPageUrl() }}">Trang trước</a>@endif <span>Trang {{ $history->currentPage() }} / {{ $history->lastPage() }}</span> @if ($history->nextPageUrl())<a href="{{ $history->nextPageUrl() }}">Trang sau</a>@endif</nav>@endif
</section>
@endsection
