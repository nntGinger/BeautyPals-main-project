@push('styles')
<style>
    .ingredient-results { display: grid; gap: .4rem; padding: .6rem; margin: .5rem 0; border: 1px solid var(--line); border-radius: 10px; max-height: 280px; overflow-y: auto; }
    .ingredient-results[hidden] { display: none; }
    .ingredient-results button { border-radius: 8px; text-align: left; overflow-wrap: anywhere; padding: .6rem .8rem; }
    .ingredient-selection { padding: 0; list-style: none; display: grid; gap: .6rem; }
    .ingredient-row { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem; border: 1px solid var(--line); border-radius: 10px; padding: .75rem; min-width: 0; }
    .ingredient-row .ingredient-label { flex: 1 1 180px; min-width: 0; overflow-wrap: anywhere; }
    .ingredient-row .ingredient-actions { display: flex; gap: .4rem; flex-wrap: wrap; }
    .ingredient-row button { padding: .4rem .7rem; font-size: .85rem; }
    .ingredient-number { font-weight: 700; color: var(--accent-dark); }
</style>
@endpush
<section id="product-ingredients" data-lookup-url="{{ route('ingredients.lookup') }}">
    <h2>Thành phần</h2>
    <p class="muted">Tìm theo tên nguyên liệu, tên hiển thị hoặc tên gọi khác. Chọn tối đa 100 thành phần và xếp theo thứ tự trên nhãn sản phẩm.</p>
    <label for="ingredient-query">Tìm thành phần theo tên hoặc alias</label>
    <input type="search" id="ingredient-query" maxlength="150" autocomplete="off" aria-controls="ingredient-results" placeholder="Ví dụ: Vitamin B3 hoặc Nicotinamide">
    <p id="ingredient-lookup-status" class="muted" role="status" aria-live="polite"></p>
    <button type="button" class="btn-ghost" id="ingredient-retry" hidden>Thử tìm lại thành phần</button>
    <div id="ingredient-results" class="ingredient-results" role="region" aria-label="Kết quả tìm thành phần" hidden></div>
    @error('ingredients')<p class="error">{{ $message }}</p>@enderror
    <p id="ingredient-selection-count" class="muted" aria-live="polite"></p>
    <ol id="selected-ingredients" class="ingredient-selection">
        @foreach ($selectedIngredients ?? [] as $index => $row)
            @php
                $id = is_scalar($row['ingredient_id'] ?? null) ? $row['ingredient_id'] : '';
                $position = is_scalar($row['position'] ?? null) ? $row['position'] : '';
                $ingredient = ($ingredientNames ?? collect())->get($id);
            @endphp
            <li class="ingredient-row" data-ingredient-row>
                <span class="ingredient-number" data-ingredient-number>{{ $position }}</span>
                <span class="ingredient-label" data-ingredient-name>{{ $ingredient ? $ingredient->display_name.' ('.$ingredient->inci_name.')' : 'Thành phần không còn tồn tại — vui lòng bỏ hoặc chọn lại' }}</span>
                <input type="hidden" data-ingredient-id name="ingredients[{{ $index }}][ingredient_id]" value="{{ $id }}">
                <input type="hidden" data-ingredient-position name="ingredients[{{ $index }}][position]" value="{{ $position }}">
                <div class="ingredient-actions"><button type="button" class="btn-ghost" data-ingredient-up aria-label="Đưa thành phần lên">Lên</button><button type="button" class="btn-ghost" data-ingredient-down aria-label="Đưa thành phần xuống">Xuống</button><button type="button" class="btn-ghost" data-ingredient-remove aria-label="Bỏ thành phần">Bỏ</button></div>
                @error("ingredients.$index.ingredient_id")<p class="error">{{ $message }}</p>@enderror
                @error("ingredients.$index.position")<p class="error">{{ $message }}</p>@enderror
            </li>
        @endforeach
    </ol>
    <p id="ingredient-empty" class="muted">Chưa chọn thành phần. Có thể lưu nháp, nhưng cần thêm ít nhất một thành phần trước khi gửi duyệt.</p>
    <noscript><p class="error">Bật JavaScript để tìm và thay đổi danh sách thành phần. Danh sách đã có vẫn được gửi khi lưu.</p></noscript>
</section>
@push('scripts')
    <script src="{{ asset('js/product-ingredients.js') }}" defer></script>
@endpush
