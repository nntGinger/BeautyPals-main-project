@php
    $row = array_map(fn ($value) => is_scalar($value) ? $value : null, $row);
@endphp
<fieldset class="sku-row" data-sku-row>
    <legend>Thuộc tính <span data-row-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></legend>
    @if (! empty($row['id']))
        <input type="hidden" name="skus[{{ $index }}][id]" value="{{ $row['id'] }}">
    @endif
    @error("skus.$index.id")<p class="error">{{ $message }}</p>@enderror
    <div class="sku-fields">
        <div>
            <label for="sku-{{ $index }}-code">Mã thuộc tính *</label>
            <input id="sku-{{ $index }}-code" name="skus[{{ $index }}][sku_code]" value="{{ $row['sku_code'] ?? '' }}" maxlength="60" required>
            @error("skus.$index.sku_code")<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="sku-{{ $index }}-name">Tên thuộc tính *</label>
            <input id="sku-{{ $index }}-name" name="skus[{{ $index }}][variant_name]" value="{{ $row['variant_name'] ?? '' }}" maxlength="100" placeholder="Ví dụ: Chai 100 ml" required>
            @error("skus.$index.variant_name")<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="sku-{{ $index }}-price">Giá bán (VND) *</label>
            <input id="sku-{{ $index }}-price" type="number" inputmode="numeric" min="0" max="1000000000" step="1" name="skus[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" required>
            @error("skus.$index.price")<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="sku-{{ $index }}-size">Dung tích / kích thước</label>
            <input id="sku-{{ $index }}-size" name="skus[{{ $index }}][size]" value="{{ $row['size'] ?? '' }}" maxlength="50">
            @error("skus.$index.size")<p class="error">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="sku-actions">
        <label class="sku-active"><input type="hidden" name="skus[{{ $index }}][is_active]" value="0"><input type="checkbox" name="skus[{{ $index }}][is_active]" value="1" @checked(($row['is_active'] ?? 1) == 1)> Đang hoạt động</label>
        <button class="btn-ghost" type="button" data-remove-sku>Bỏ thuộc tính</button>
    </div>
    @error("skus.$index.is_active")<p class="error">{{ $message }}</p>@enderror
</fieldset>
