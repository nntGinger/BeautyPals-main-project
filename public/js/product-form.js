(() => {
    const rows = document.getElementById('sku-rows');
    const template = document.getElementById('sku-row-template');
    const add = document.getElementById('add-sku');
    if (!rows || !template || !add) return;

    let nextIndex = Math.max(-1, ...Array.from(rows.querySelectorAll('input[name]'), input => {
        const match = input.name.match(/^skus\[(\d+)\]/);
        return match ? Number(match[1]) : -1;
    })) + 1;

    const refresh = () => {
        const current = rows.querySelectorAll('[data-sku-row]');
        current.forEach((row, index) => {
            row.querySelector('[data-row-number]').textContent = index + 1;
            row.querySelector('[data-remove-sku]').disabled = current.length <= 1;
        });
        add.disabled = current.length >= 20;
    };
    add.addEventListener('click', () => {
        if (rows.querySelectorAll('[data-sku-row]').length >= 20) return;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        refresh();
    });
    rows.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-sku]');
        if (!remove || rows.querySelectorAll('[data-sku-row]').length <= 1) return;
        remove.closest('[data-sku-row]').remove();
        refresh();
    });
    refresh();

    const images = document.getElementById('product-images');
    const preview = document.getElementById('new-product-image-preview');
    const imageFeedback = document.getElementById('product-image-feedback');
    let previewUrls = [];
    const clearPreview = () => {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls = [];
        if (preview) preview.replaceChildren();
    };
    if (images && preview) {
        const checkImages = () => {
            const oldImages = document.querySelectorAll('#existing-product-images input[type=checkbox]');
            const remaining = [...oldImages].filter(input => !input.checked).length;
            const files = [...images.files];
            const error = files.some(file => file.size > 5242880) ? 'Mỗi ảnh có dung lượng tối đa 5 MiB.'
                : remaining + files.length > 8 ? 'Tổng ảnh sau khi lưu không được vượt quá 8. Hãy bỏ bớt ảnh cũ hoặc chọn ít ảnh mới hơn.' : '';
            images.setCustomValidity(error);
            imageFeedback.textContent = error || `${remaining} ảnh hiện có được giữ, ${files.length} ảnh mới được chọn.`;
            imageFeedback.className = error ? 'error' : 'muted';
        };
        images.addEventListener('change', () => {
            clearPreview();
            Array.from(images.files).forEach(file => {
                const item = document.createElement('div');
                item.className = 'product-image-item';
                if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    const image = document.createElement('img');
                    image.src = URL.createObjectURL(file);
                    previewUrls.push(image.src);
                    image.alt = file.name;
                    item.append(image);
                }
                const name = document.createElement('p');
                name.textContent = file.name;
                name.style.overflowWrap = 'anywhere';
                item.append(name);
                preview.append(item);
            });
            checkImages();
        });
        document.getElementById('existing-product-images')?.addEventListener('change', checkImages);
        window.addEventListener('pagehide', clearPreview);
    }

    const form = document.querySelector('[data-product-form]');
    const saveStatus = document.querySelector('[data-save-status]');
    let dirty = Boolean(document.querySelector('[data-form-errors]')), saving = false;
    if (dirty && saveStatus) saveStatus.textContent = 'Dữ liệu chưa được lưu. Vui lòng kiểm tra các lỗi phía trên.';
    const markDirty = () => {
        if (saving) return;
        dirty = true;
        saveStatus.textContent = 'Có thay đổi chưa lưu.';
    };
    form?.addEventListener('input', event => { if (event.target.name) markDirty(); });
    form?.addEventListener('change', event => { if (event.target.name) markDirty(); });
    form?.addEventListener('product:ingredients-changed', markDirty);
    add.addEventListener('click', markDirty);
    rows.addEventListener('click', event => { if (event.target.closest('[data-remove-sku]')) markDirty(); });
    form?.addEventListener('submit', event => {
        if (saving) { event.preventDefault(); return; }
        saving = true;
        dirty = false;
        saveStatus.textContent = 'Đang lưu sản phẩm, vui lòng chờ…';
        form.querySelectorAll('button[type=submit]').forEach(button => {
            button.disabled = true;
            button.textContent = 'Đang lưu…';
        });
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty || saving) return;
        event.preventDefault();
        event.returnValue = '';
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        saving = false;
        form?.querySelectorAll('button[type=submit]').forEach(button => {
            button.disabled = false;
            button.textContent = form.querySelector('input[name="_method"]') ? 'Lưu thay đổi' : 'Lưu nháp';
        });
        if (saveStatus) saveStatus.textContent = dirty ? 'Có thay đổi chưa lưu.' : '';
    });
    document.querySelector('[data-form-errors]')?.focus();
})();
