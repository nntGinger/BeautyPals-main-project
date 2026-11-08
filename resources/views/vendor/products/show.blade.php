@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <section class="card" style="overflow-wrap: anywhere">
        <p><a href="{{ route('vendor.products.index') }}">← Sản phẩm của tôi</a></p>
        <h1>{{ $product->name }}</h1>
        @if ($errors->any())
            <div class="flash-error" role="alert">
                <strong>Chưa thực hiện được thao tác:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <p><a class="btn" href="{{ route('vendor.products.edit', $product) }}">Sửa sản phẩm</a></p>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin: 1rem 0">
            @if (in_array($product->status, ['draft', 'rejected', 'hidden'], true))
                <form method="POST" action="{{ route('vendor.products.submit', $product) }}">
                    @csrf
                    <button type="submit">{{ $product->status === 'hidden' ? 'Gửi duyệt lại' : 'Gửi duyệt' }}</button>
                </form>
            @endif
            @if ($product->status === 'draft')
                <div data-delete-confirm style="max-width:100%">
                    <button type="button" class="btn-ghost" data-open-delete aria-expanded="false" aria-controls="delete-confirm-panel">Xóa sản phẩm nháp</button>
                    <div id="delete-confirm-panel" hidden>
                    <form method="POST" action="{{ route('vendor.products.destroy', $product) }}" style="padding:1rem; margin-top:.75rem; border:1px solid var(--line); border-radius:10px">
                        @csrf
                        @method('DELETE')
                        <p>Xóa sản phẩm nháp và ảnh của sản phẩm. Thao tác này không thể hoàn tác.</p>
                        <label style="display:flex; gap:.5rem; align-items:start"><input type="checkbox" required style="width:auto; margin-top:.3rem"> Tôi xác nhận muốn xóa sản phẩm này.</label>
                        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-top:.75rem">
                            <button type="submit">Xác nhận xóa</button>
                            <button type="button" class="btn-ghost" data-cancel-delete>Hủy xóa</button>
                        </div>
                    </form>
                    </div>
                    <noscript><p>Bật JavaScript để mở xác nhận xóa sản phẩm.</p></noscript>
                </div>
            @endif
        </div>
        <p>Trạng thái: <x-product-status :status="$product->status" /></p>
        @if (in_array($product->status, ['draft', 'rejected', 'hidden'], true))
            <p class="muted">Trước khi gửi duyệt, cần ít nhất một thuộc tính đang hoạt động, một ảnh hợp lệ và một thành phần. Chỉ thông tin đã lưu được gửi duyệt.</p>
        @elseif ($product->status === 'pending')
            <p class="muted">Sản phẩm đang chờ Admin xét duyệt.</p>
        @endif
        <p>Danh mục: {{ $product->category?->name }}</p>
        <p>Thương hiệu: {{ $product->brand?->name ?? 'Chưa chọn' }}</p>
        @if ($product->reject_reason)
            <p class="error">{{ $product->status === 'hidden' ? 'Lý do ẩn' : 'Lý do từ chối' }}: {{ $product->reject_reason }}</p>
        @endif
        <p style="white-space: pre-wrap">{{ $product->description }}</p>
        <div style="display: flex; flex-wrap: wrap; gap: .75rem">
            @foreach ($product->images as $image)
                @if (\Illuminate\Support\Facades\Storage::disk('public')->exists($image->path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $product->name }}" width="160" height="160" style="object-fit: contain; max-width: 100%; border-radius: 10px">
                @endif
            @endforeach
        </div>
        <h2>Thuộc tính</h2>
        @forelse ($product->skus as $sku)
            <article style="border-top: 1px solid var(--line); padding: .75rem 0">
                <strong>{{ $sku->variant_name }}</strong>
                <p>Mã: {{ $sku->sku_code }} · {{ number_format($sku->price, 0, ',', '.') }} VND</p>
                <p>{{ $sku->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}@if ($sku->size) · {{ $sku->size }}@endif</p>
            </article>
        @empty
            <p>Chưa có thuộc tính.</p>
        @endforelse
        <h2>Thành phần</h2>
        <ol>
            @forelse ($product->ingredients as $ingredient)
                <li>{{ $ingredient->display_name }} ({{ $ingredient->inci_name }})</li>
            @empty
                <li>Chưa có thành phần.</li>
            @endforelse
        </ol>
    </section>
@endsection
@push('scripts')
    <script src="{{ asset('js/product-actions.js') }}" defer></script>
@endpush
