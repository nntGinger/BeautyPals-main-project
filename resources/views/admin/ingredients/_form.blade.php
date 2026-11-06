@php
    $aliases = old('aliases', isset($ingredient) ? $ingredient->aliases->pluck('alias')->all() : []);
@endphp

<label for="inci_name">Tên nguyên liệu *</label>
<input id="inci_name" name="inci_name" value="{{ old('inci_name', $ingredient->inci_name ?? '') }}" maxlength="150" required>
@error('inci_name') <p class="error">{{ $message }}</p> @enderror

<label for="display_name">Tên hiển thị *</label>
<input id="display_name" name="display_name" value="{{ old('display_name', $ingredient->display_name ?? '') }}" maxlength="150" required>
@error('display_name') <p class="error">{{ $message }}</p> @enderror

<label for="function">Công dụng</label>
<input id="function" name="function" value="{{ old('function', $ingredient->function ?? '') }}" maxlength="255">
@error('function') <p class="error">{{ $message }}</p> @enderror

<label>Alias (tối đa 20)</label>
<div id="alias-list">
    @foreach ($aliases as $index => $alias)
        <div class="alias-row" style="display:flex;gap:.5rem;margin:.4rem 0;align-items:center">
            <input name="aliases[{{ $index }}]" value="{{ $alias }}" maxlength="150" aria-label="Alias {{ $index + 1 }}">
            <button type="button" class="btn-ghost remove-alias">Bỏ</button>
            @error("aliases.$index") <p class="error">{{ $message }}</p> @enderror
        </div>
    @endforeach
</div>
@error('aliases') <p class="error">{{ $message }}</p> @enderror
<button type="button" class="btn-ghost" id="add-alias">Thêm alias</button>
<div style="margin-top:1.5rem"><button type="submit">Lưu thành phần</button></div>

@push('scripts')
<script>
(() => {
    const list = document.getElementById('alias-list');
    const add = document.getElementById('add-alias');
    const renumber = () => {
        [...list.querySelectorAll('.alias-row input')].forEach((input, index) => {
            input.name = `aliases[${index}]`;
            input.setAttribute('aria-label', `Alias ${index + 1}`);
        });
        add.disabled = list.querySelectorAll('.alias-row').length >= 20;
    };
    add.addEventListener('click', () => {
        if (list.querySelectorAll('.alias-row').length >= 20) return;
        const row = document.createElement('div');
        row.className = 'alias-row';
        row.style.cssText = 'display:flex;gap:.5rem;margin:.4rem 0;align-items:center';
        const input = document.createElement('input');
        input.maxLength = 150;
        input.required = true;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn-ghost remove-alias';
        remove.textContent = 'Bỏ';
        row.append(input, remove);
        list.append(row);
        renumber();
        input.focus();
    });
    list.addEventListener('click', event => {
        if (event.target.matches('.remove-alias')) {
            event.target.closest('.alias-row').remove();
            renumber();
        }
    });
    renumber();
})();
</script>
@endpush
