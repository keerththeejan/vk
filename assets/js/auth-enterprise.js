(() => {
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    qsa('[data-vk-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = qs(button.getAttribute('data-vk-copy'));
            if (!target) return;
            const text = target.value || target.textContent || '';
            await navigator.clipboard.writeText(text.trim());
            const original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
            setTimeout(() => { button.innerHTML = original; }, 1400);
        });
    });

    qsa('[data-vk-filter]').forEach((input) => {
        const table = qs(input.getAttribute('data-vk-filter'));
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            qsa('tbody tr', table).forEach((row) => {
                row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
            });
        });
    });

    qsa('form.needs-validation').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add('was-validated');
                form.querySelector(':invalid')?.focus();
                return;
            }
            const submit = form.querySelector('[type="submit"][data-loading-text]');
            if (submit) {
                submit.disabled = true;
                submit.dataset.originalText = submit.innerHTML;
                submit.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${submit.dataset.loadingText}`;
            }
        });
    });

    const signup = qs('[data-signup-wizard]');
    if (signup) {
        let step = 1;
        const panes = qsa('[data-step-pane]', signup);
        const steps = qsa('[data-step-indicator]', signup);
        const showStep = (next) => {
            step = Math.max(1, Math.min(3, next));
            panes.forEach((pane) => pane.classList.toggle('d-none', pane.dataset.stepPane !== String(step)));
            steps.forEach((item) => {
                const index = Number(item.dataset.stepIndicator);
                item.classList.toggle('is-active', index === step);
                item.classList.toggle('is-done', index < step);
            });
        };
        qsa('[data-step-next]', signup).forEach((button) => {
            button.addEventListener('click', () => {
                const currentPane = qs(`[data-step-pane="${step}"]`, signup);
                const invalid = currentPane?.querySelector(':invalid');
                if (invalid) {
                    signup.classList.add('was-validated');
                    invalid.focus();
                    return;
                }
                showStep(step + 1);
            });
        });
        qsa('[data-step-prev]', signup).forEach((button) => button.addEventListener('click', () => showStep(step - 1)));

        const fullName = qs('#fullname');
        const preview = qs('#usernamePreview');
        fullName?.addEventListener('input', () => {
            const base = fullName.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '').slice(0, 28);
            preview.textContent = base ? `${base}.${new Date().getFullYear().toString().slice(2)}##` : 'Generated after submission';
        });
    }

    qsa('[data-download-credentials]').forEach((button) => {
        button.addEventListener('click', () => {
            window.print();
        });
    });
})();
