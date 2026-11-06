(() => {
    const editor = document.getElementById('product-ingredients');
    if (!editor) return;
    const query = document.getElementById('ingredient-query');
    const results = document.getElementById('ingredient-results');
    const status = document.getElementById('ingredient-lookup-status');
    const selected = document.getElementById('selected-ingredients');
    const count = document.getElementById('ingredient-selection-count');
    const empty = document.getElementById('ingredient-empty');
    const retry = document.getElementById('ingredient-retry');
    let timer, request, generation = 0;

    const refresh = () => {
        const rows = [...selected.querySelectorAll('[data-ingredient-row]')];
        rows.forEach((row, index) => {
            const id = row.querySelector('[data-ingredient-id]');
            const position = row.querySelector('[data-ingredient-position]');
            id.name = `ingredients[${index}][ingredient_id]`;
            position.name = `ingredients[${index}][position]`;
            position.value = index + 1;
            row.querySelector('[data-ingredient-number]').textContent = index + 1;
            row.querySelector('[data-ingredient-up]').disabled = index === 0;
            row.querySelector('[data-ingredient-down]').disabled = index === rows.length - 1;
        });
        const ids = new Set(rows.map(row => row.querySelector('[data-ingredient-id]').value));
        results.querySelectorAll('button[data-result-id]').forEach(button => {
            button.disabled = ids.has(button.dataset.resultId) || rows.length >= 100;
        });
        count.textContent = `Đã chọn ${rows.length}/100 thành phần.`;
        empty.hidden = rows.length > 0;
    };
    const add = ingredient => {
        if (selected.children.length >= 100 || [...selected.querySelectorAll('[data-ingredient-id]')].some(input => input.value === String(ingredient.id))) return;
        const row = document.createElement('li');
        row.className = 'ingredient-row';
        row.dataset.ingredientRow = '';
        const number = document.createElement('span');
        number.className = 'ingredient-number';
        number.dataset.ingredientNumber = '';
        const label = document.createElement('span');
        label.className = 'ingredient-label';
        label.dataset.ingredientName = '';
        label.textContent = `${ingredient.display_name} (${ingredient.inci_name})`;
        const id = document.createElement('input');
        id.type = 'hidden';
        id.dataset.ingredientId = '';
        id.value = ingredient.id;
        const position = document.createElement('input');
        position.type = 'hidden';
        position.dataset.ingredientPosition = '';
        const actions = document.createElement('div');
        actions.className = 'ingredient-actions';
        [['ingredientUp', 'Lên', 'Đưa thành phần lên'], ['ingredientDown', 'Xuống', 'Đưa thành phần xuống'], ['ingredientRemove', 'Bỏ', 'Bỏ thành phần']].forEach(([key, text, aria]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn-ghost';
            button.dataset[key] = '';
            button.textContent = text;
            button.setAttribute('aria-label', aria);
            actions.append(button);
        });
        row.append(number, label, id, position, actions);
        selected.append(row);
        refresh();
        status.textContent = `Đã thêm ${ingredient.display_name}.`;
        editor.dispatchEvent(new CustomEvent('product:ingredients-changed', {bubbles: true}));
    };
    selected.addEventListener('click', event => {
        const button = event.target.closest('button');
        const row = button?.closest('[data-ingredient-row]');
        if (!row) return;
        if ('ingredientRemove' in button.dataset) row.remove();
        else if ('ingredientUp' in button.dataset && row.previousElementSibling) selected.insertBefore(row, row.previousElementSibling);
        else if ('ingredientDown' in button.dataset && row.nextElementSibling) selected.insertBefore(row.nextElementSibling, row);
        refresh();
        editor.dispatchEvent(new CustomEvent('product:ingredients-changed', {bubbles: true}));
    });
    query.addEventListener('input', () => {
        clearTimeout(timer);
        request?.abort();
        const current = ++generation;
        results.replaceChildren();
        results.hidden = true;
        status.textContent = '';
        retry.hidden = true;
        const q = query.value.trim();
        if (!q) return;
        timer = setTimeout(async () => {
            request = new AbortController();
            status.textContent = 'Đang tìm thành phần…';
            try {
                const url = new URL(editor.dataset.lookupUrl, window.location.origin);
                url.searchParams.set('q', q);
                const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', signal: request.signal});
                if (response.redirected || response.status === 401) throw new Error('Phiên đăng nhập đã hết. Hãy đăng nhập lại trước khi lưu.');
                if (!response.ok) throw new Error('Không thể tra cứu thành phần. Vui lòng thử lại hoặc kiểm tra quyền tài khoản.');
                const body = await response.json();
                if (current !== generation) return;
                if (!Array.isArray(body.data)) throw new Error('Kết quả tra cứu không hợp lệ.');
                body.data.forEach(ingredient => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn-ghost';
                    button.dataset.resultId = String(ingredient.id);
                    button.textContent = `${ingredient.display_name} — ${ingredient.inci_name}`;
                    button.addEventListener('click', () => add(ingredient));
                    results.append(button);
                });
                results.hidden = body.data.length === 0;
                status.textContent = body.data.length ? `${body.data.length} kết quả. Chọn thành phần để thêm.` : 'Không tìm thấy thành phần phù hợp.';
                refresh();
            } catch (error) {
                if (error.name !== 'AbortError' && current === generation) {
                    status.textContent = error.message;
                    retry.hidden = false;
                }
            }
        }, 250);
    });
    retry.addEventListener('click', () => query.dispatchEvent(new Event('input')));
    refresh();
})();
