@extends('layouts.app')

@section('title', 'Khám phá mỹ phẩm chính hãng — BeautyPals')

@section('content')
    <div class="card">
        <h1>Danh mục sản phẩm chính hãng</h1>
        <p class="muted">Tìm kiếm theo tên sản phẩm, danh mục, thương hiệu hoặc lọc chuyên sâu theo thành phần (INCI & tên gọi khác).</p>

        <form method="GET" action="{{ route('catalog.index') }}" style="margin-top: 1rem;">
            <div class="form-grid">
                <div>
                    <label for="q">Từ khóa tìm kiếm</label>
                    <input id="q" type="text" name="q" value="{{ $q }}" placeholder="Tên sản phẩm, công dụng...">
                </div>
                <div>
                    <label for="category_id">Danh mục</label>
                    <select id="category_id" name="category_id">
                        <option value="">-- Tất cả danh mục --</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected($categoryId === $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="brand_id">Thương hiệu</label>
                    <select id="brand_id" name="brand_id">
                        <option value="">-- Tất cả thương hiệu --</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected($brandId === $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sort">Sắp xếp</label>
                    <select id="sort" name="sort">
                        <option value="newest" @selected($sort === 'newest' || $sort === '')>Mới nhất</option>
                        <option value="price_asc" @selected($sort === 'price_asc')>Giá thấp đến cao</option>
                        <option value="price_desc" @selected($sort === 'price_desc')>Giá cao đến thấp</option>
                        <option value="name_asc" @selected($sort === 'name_asc')>Tên A → Z</option>
                    </select>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 0.5rem;">
                <div>
                    <label for="with_ingredient">Có chứa thành phần (INCI / Alias)</label>
                    <input id="with_ingredient" type="text" name="with_ingredient" value="{{ $withIngredient }}" placeholder="VD: Vitamin B3, Niacinamide...">
                </div>
                <div>
                    <label for="without_ingredient">Loại trừ thành phần</label>
                    <input id="without_ingredient" type="text" name="without_ingredient" value="{{ $withoutIngredient }}" placeholder="VD: Retinol, Salicylic Acid...">
                </div>
                <div>
                    <label for="min_price">Giá từ (VNĐ)</label>
                    <input id="min_price" type="number" name="min_price" min="0" value="{{ $minPrice }}">
                </div>
                <div>
                    <label for="max_price">Giá đến (VNĐ)</label>
                    <input id="max_price" type="number" name="max_price" min="0" value="{{ $maxPrice }}">
                </div>
            </div>

            <div style="margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <button type="submit">Lọc sản phẩm</button>
                <a href="{{ route('catalog.index') }}" class="btn btn-ghost">Xóa bộ lọc</a>
            </div>
        </form>
    </div>

    @if ($products->isEmpty())
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <h2>Không tìm thấy sản phẩm phù hợp</h2>
            <p class="muted">Hãy thử nới lỏng điều kiện tìm kiếm hoặc xóa bớt bộ lọc thành phần / khoảng giá.</p>
            <a href="{{ route('catalog.index') }}" class="btn" style="margin-top: 0.75rem;">Xem tất cả sản phẩm</a>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.15rem;">
            @foreach ($products as $product)
                @php
                    $minSkuPrice = $product->skus->min('price');
                @endphp
                <article class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <p class="muted" style="font-size: 0.82rem; margin: 0 0 0.35rem;">
                            {{ $product->brand?->name ?? 'BeautyPals' }} · {{ $product->category?->name }}
                        </p>
                        <h2 style="font-size: 1.05rem; margin-bottom: 0.45rem;">
                            <a href="{{ route('catalog.show', $product->slug) }}">{{ $product->name }}</a>
                        </h2>
                        <p class="muted" style="font-size: 0.85rem; margin: 0 0 0.75rem;">
                            Gian hàng: <strong>{{ $product->vendor?->store_name }}</strong>
                        </p>
                    </div>
                    <div>
                        <p style="font-size: 1.15rem; font-weight: 700; color: var(--accent-dark); margin: 0 0 0.75rem;">
                            {{ $minSkuPrice !== null ? number_format($minSkuPrice, 0, ',', '.') . ' ₫' : 'Liên hệ' }}
                        </p>
                        <a href="{{ route('catalog.show', $product->slug) }}" class="btn btn-sm" style="width: 100%; text-align: center;">
                            Xem chi tiết
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div style="margin-top: 1.5rem;">
            {{ $products->links() }}
        </div>
    @endif
@endsection

