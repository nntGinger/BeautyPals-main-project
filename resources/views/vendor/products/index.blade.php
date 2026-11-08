@extends('layouts.app')

@section('title', 'Sản phẩm của tôi')

@push('styles')
    <style>
        main { max-width: 1600px; }
        main > .card.product-catalog { max-width: none; padding: clamp(1rem, 2.5vw, 2rem); }
        .product-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1.1rem; margin-top: 1.5rem; }
        .product-tile { min-width: 0; display: flex; flex-direction: column; overflow: hidden; border: 1px solid var(--line); border-radius: 14px; background: #fff; transition: box-shadow .18s, border-color .18s; }
        .product-tile:hover { border-color: #d9b8bf; box-shadow: 0 8px 24px #2b211c0d; }
        .product-photo { display: grid; place-items: center; aspect-ratio: 1; background: #f7f5f4; border-bottom: 1px solid var(--line); color: var(--muted); }
        .product-photo img { width: 100%; height: 100%; aspect-ratio: 1; object-fit: contain; background: #fff; }
        .product-photo-placeholder { display: grid; justify-items: center; gap: .6rem; font-size: .8rem; }
        .product-photo-placeholder svg { width: 36px; height: 36px; color: #b7aaa4; }
        .product-info { display: flex; flex-direction: column; flex: 1; gap: .7rem; padding: 1rem; }
        .product-brand { margin: 0; color: var(--muted); font-size: .8rem; overflow-wrap: anywhere; }
        .product-name { margin: 0; font-size: .98rem; line-height: 1.5; overflow-wrap: anywhere; }
        .product-name a { color: var(--ink); }
        .product-name a:hover { color: var(--accent-dark); }
        .product-attributes { margin: 0; color: var(--muted); font-size: .85rem; }
        .product-price-block { margin-top: auto; padding-top: .65rem; border-top: 1px solid #f0e9e5; }
        .product-price-label { display: block; margin-bottom: .3rem; font-size: .75rem; color: var(--muted); }
        .product-price { margin: 0; color: var(--accent-dark); font-size: 1rem; font-weight: 700; overflow-wrap: anywhere; }
        .product-pagination { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: center; margin-top: 1.5rem; }
        @media (max-width: 1199px) { .product-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (max-width: 959px) { .product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 699px) { .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; } .product-info { padding: .8rem; } }
        @media (max-width: 419px) { .product-grid { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <section class="card product-catalog">
        <h1>Sản phẩm của tôi</h1>
        <p class="muted">{{ $products->total() }} sản phẩm thuộc cửa hàng của bạn.</p>
        <a class="btn" href="{{ route('vendor.products.create') }}">Thêm sản phẩm</a>
        <div class="product-grid">
            @forelse ($products as $product)
                @php
                    $image = $product->images->first(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->exists($image->path));
                @endphp
                <article class="product-tile">
                    <a class="product-photo" href="{{ route('vendor.products.show', $product) }}" aria-label="Xem {{ $product->name }}">
                        @if ($image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $product->name }}" loading="lazy" width="400" height="400">
                        @else
                            <span class="product-photo-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/></svg>
                                <span>Chưa có ảnh</span>
                            </span>
                        @endif
                    </a>
                    <div class="product-info">
                        <x-product-status :status="$product->status" />
                        <h2 class="product-name"><a href="{{ route('vendor.products.show', $product) }}">{{ $product->name }}</a></h2>
                        <p class="product-brand">Thương hiệu: {{ $product->brand?->name ?? 'Chưa có thương hiệu' }}</p>
                        <p class="product-attributes">{{ $product->skus_count }} Thuộc tính</p>
                        <div class="product-price-block">
                            <span class="product-price-label">Giá bán</span>
                            <p class="product-price">
                                @if ($product->skus_min_price === null)
                                    Chưa có giá
                                @else
                                    {{ number_format($product->skus_min_price, 0, ',', '.') }} ₫@if ($product->skus_min_price != $product->skus_max_price) – {{ number_format($product->skus_max_price, 0, ',', '.') }} ₫@endif
                                @endif
                            </p>
                        </div>
                    </div>
                </article>
            @empty
                <p style="grid-column: 1 / -1">Cửa hàng chưa có sản phẩm.</p>
            @endforelse
        </div>
        @if ($products->hasPages())
            <nav aria-label="Phân trang sản phẩm" class="product-pagination">
                @if ($products->previousPageUrl())
                    <a class="btn btn-ghost" href="{{ $products->previousPageUrl() }}" rel="prev">Trang trước</a>
                @endif
                <span>Trang {{ $products->currentPage() }} / {{ $products->lastPage() }}</span>
                @if ($products->nextPageUrl())
                    <a class="btn btn-ghost" href="{{ $products->nextPageUrl() }}" rel="next">Trang sau</a>
                @endif
            </nav>
        @endif
    </section>
@endsection
