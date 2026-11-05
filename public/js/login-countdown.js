(() => {
    document.querySelectorAll('[data-login-countdown]').forEach((container) => {
        const seconds = Number(container.dataset.loginCountdown);
        if (!Number.isFinite(seconds) || seconds <= 0) return;
        const started = performance.now();
        const clock = container.querySelector('[data-lockout-clock]');
        const email = document.querySelector('input[name="email"]');
        const syncAccount = () => {
            container.hidden = Boolean(container.dataset.lockoutEmail) && email.value.trim().toLowerCase() !== container.dataset.lockoutEmail.toLowerCase();
        };
        email?.addEventListener('input', syncAccount);
        if (email) syncAccount();
        let interval;
        const update = () => {
            const remaining = Math.max(0, seconds - Math.floor((performance.now() - started) / 1000));
            clock.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
            if (remaining === 0) {
                clearInterval(interval);
                container.querySelector('[data-lockout-message]').textContent = 'Đã hết thời gian chờ. Bạn có thể thử đăng nhập lại.';
                if (clock) clock.hidden = true;
                container.querySelector('[data-lockout-note]').hidden = true;
                container.setAttribute('role', 'status');
            }
        };
        interval = setInterval(update, 1000);
        update();
        document.addEventListener('visibilitychange', update);
    });
})();
