(() => {
    const conceal = (button, input) => {
        input.type = 'password';
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', `Hiện ${button.dataset.label}`);
        button.querySelector('.password-eye-slash').setAttribute('hidden', '');
    };

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;
        button.hidden = false;
        button.addEventListener('click', () => {
            if (input.type === 'text') {
                conceal(button, input);
            } else {
                input.type = 'text';
                button.setAttribute('aria-pressed', 'true');
                button.setAttribute('aria-label', `Ẩn ${button.dataset.label}`);
                button.querySelector('.password-eye-slash').removeAttribute('hidden');
            }
        });
        input.form?.addEventListener('submit', () => conceal(button, input));
        window.addEventListener('pagehide', () => conceal(button, input));
        window.addEventListener('pageshow', () => conceal(button, input));
    });
})();
