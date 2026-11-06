document.querySelectorAll('[data-delete-confirm]').forEach(container => {
    const open = container.querySelector('[data-open-delete]');
    const panel = container.querySelector('#delete-confirm-panel');
    open.addEventListener('click', () => {
        panel.hidden = false;
        open.setAttribute('aria-expanded', 'true');
        panel.querySelector('input[type=checkbox]').focus();
    });
    container.querySelector('[data-cancel-delete]')?.addEventListener('click', () => {
        container.querySelector('form').reset();
        panel.hidden = true;
        open.setAttribute('aria-expanded', 'false');
        open.focus();
    });
});
