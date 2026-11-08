@push('styles')
    <style>
        main { max-width: 1120px; }
        main > .card.product-editor { max-width: none; }
        .product-editor select, .product-editor textarea { width: 100%; padding: .7rem .8rem; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink); font: inherit; }
        .product-editor textarea { min-height: 140px; resize: vertical; }
        .product-fields, .sku-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1rem; }
        .sku-row { min-width: 0; margin: 1rem 0; padding: 1rem; border: 1px solid var(--line); border-radius: 12px; }
        .sku-row legend { padding: 0 .4rem; font-weight: 600; }
        .sku-actions, .product-form-actions { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
        .sku-active { display: inline-flex; gap: .5rem; align-items: center; margin: 0; }
        .sku-active input[type=checkbox] { width: auto; }
        .product-editor button:disabled { opacity: .5; cursor: not-allowed; }
        .product-errors { padding: 1rem; margin-top: 1rem; background: #fde7e5; border-radius: 10px; color: var(--danger); }
        .product-image-list { display: flex; flex-wrap: wrap; gap: .8rem; margin: 1rem 0; }
        .product-image-item { width: 150px; padding: .5rem; border: 1px solid var(--line); border-radius: 10px; }
        .product-image-item img { width: 100%; height: 120px; object-fit: contain; background: #f7f5f4; border-radius: 6px; }
        .product-image-item label { display: flex; align-items: center; gap: .4rem; font-size: .85rem; }
        .product-image-item input[type=checkbox] { width: auto; }
        .product-image-empty { display: grid; place-items: center; height: 120px; background: #f7f5f4; font-size: .8rem; color: var(--muted); }
        @media (max-width: 650px) { .product-fields, .sku-fields { grid-template-columns: 1fr; } .product-editor.card { padding: 1rem; } }
    </style>
@endpush
@php
    $product = $product ?? null;
    $rows = old('skus', $product ? $product->skus->toArray() : [['is_active' => 1]]);
    $rows = is_array($rows) && count($rows) ? $rows : [['is_active' => 1]];
    $rows = array_filter($rows, fn ($row, $key) => is_array($row) && is_int($key) && $key >= 0, ARRAY_FILTER_USE_BOTH);
    $rows = $rows ?: [['is_active' => 1]];
    $field = function ($key, $default = null) {
        $value = old($key, $default);
        return is_scalar($value) ? $value : null;
    };
@endphp
@if ($errors->any())
    <div class="product-errors" role="alert" tabindex="-1" data-form-errors>
        <strong>Chưa lưu được sản phẩm. Vui lòng kiểm tra:</strong>
        <ul>@foreach (array_unique($errors->all()) as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<p class="muted">Các trường có dấu * là bắt buộc. Giá bán nhập bằng số nguyên VND, không dùng dấu phân cách hàng nghìn.</p>
<h2>Thông tin sản phẩm</h2>
<label for="product-name">Tên sản phẩm *</label>
<input id="product-name" name="name" value="{{ $field('name', $product?->name) }}" maxlength="200" required>
@error('name')<p class="error">{{ $message }}</p>@enderror
<div class="product-fields">
    <div>
        <label for="product-category">Danh mục *</label>
        <select id="product-category" name="category_id" required>
            <option value="">Chọn danh mục</option>
            @foreach ($categories as $category)<option value="{{ $category->id }}" @selected($field('category_id', $product?->category_id) == $category->id)>{{ $category->name }}</option>@endforeach
        </select>
        @error('category_id')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="product-brand">Thương hiệu</label>
        <select id="product-brand" name="brand_id">
            <option value="">Chưa chọn thương hiệu</option>
            @foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected($field('brand_id', $product?->brand_id) == $brand->id)>{{ $brand->name }}</option>@endforeach
        </select>
        @error('brand_id')<p class="error">{{ $message }}</p>@enderror
    </div>
</div>
<label for="product-description">Mô tả</label>
<textarea id="product-description" name="description" maxlength="10000">{{ $field('description', $product?->description) }}</textarea>
@error('description')<p class="error">{{ $message }}</p>@enderror
<h2>Ảnh sản phẩm</h2>
<p class="muted">Chọn ảnh JPEG, PNG hoặc WebP, tối đa 5 MiB mỗi ảnh và 8 ảnh mỗi sản phẩm. Ảnh đầu tiên là ảnh chính.</p>
@if ($product && $product->images->isNotEmpty())
    @php
        $deletedImages = old('delete_image_ids', []);
        $deletedImages = is_array($deletedImages) ? $deletedImages : [];
    @endphp
    <div class="product-image-list" id="existing-product-images">
        @foreach ($product->images as $image)
            <div class="product-image-item">
                @if (\Illuminate\Support\Facades\Storage::disk('public')->exists($image->path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="Ảnh {{ $loop->iteration }} của sản phẩm">
                @else
                    <div class="product-image-empty">Chưa có tệp ảnh</div>
                @endif
                <label><input type="checkbox" name="delete_image_ids[]" value="{{ $image->id }}" @checked(in_array($image->id, $deletedImages))> Xóa ảnh này</label>
            </div>
        @endforeach
    </div>
@endif
<label for="product-images">{{ $product ? 'Thêm ảnh mới' : 'Đăng ảnh sản phẩm' }}</label>
<input id="product-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="product-image-note">
<p class="muted" id="product-image-note">Có thể chọn nhiều ảnh cùng lúc. Ảnh mới được thêm sau các ảnh hiện có.</p>
<p id="product-image-feedback" class="muted" role="status" aria-live="polite"></p>
@error('images')<p class="error">{{ $message }}</p>@enderror
@foreach ($errors->get('images.*') as $messages)
    @foreach ($messages as $message)<p class="error">{{ $message }}</p>@endforeach
@endforeach
@error('delete_image_ids')<p class="error">{{ $message }}</p>@enderror
<div id="new-product-image-preview" class="product-image-list" aria-live="polite"></div>
@if ($errors->any())<p class="muted">Nếu đã chọn ảnh mới, bạn cần chọn lại tệp trước khi lưu.</p>@endif
<h2>Thuộc tính</h2>
<p class="muted">Thêm từ 1 đến 20 thuộc tính. Mỗi thuộc tính có mã duy nhất và giá bán riêng.</p>
@error('skus')<p class="error">{{ $message }}</p>@enderror
<div id="sku-rows">
    @foreach ($rows as $index => $row)
        @include('vendor.products._sku-row', ['index' => $index, 'row' => $row])
    @endforeach
</div>
<button type="button" class="btn-ghost" id="add-sku">+ Thêm thuộc tính</button>
<p class="muted">Thuộc tính đã được sử dụng cần được giữ lại; bạn có thể bỏ chọn “Đang hoạt động”.</p>
@include('vendor.products._ingredients')
<div class="product-form-actions">
    <button type="submit">{{ $product ? 'Lưu thay đổi' : 'Lưu nháp' }}</button>
    <a href="{{ $product ? route('vendor.products.show', $product) : route('vendor.products.index') }}">Hủy</a>
</div>
<p class="muted" data-save-status role="status" aria-live="polite"></p>
<template id="sku-row-template">@include('vendor.products._sku-row', ['index' => '__INDEX__', 'row' => ['is_active' => 1]])</template>
@push('scripts')
    <script src="{{ asset('js/product-form.js') }}" defer></script>
@endpush
