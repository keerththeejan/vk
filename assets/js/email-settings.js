(function () {
    'use strict';

    const root = document.getElementById('emailSettingsApp');
    if (!root) {
        return;
    }

    const apiUrl = root.dataset.apiUrl || '';
    const csrf = root.dataset.csrf || window.VK_CSRF_TOKEN || '';
    const canEdit = root.dataset.canEdit === '1';
    const toast = (msg, type) => {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type || 'success');
        }
    };

    const loadingEl = document.getElementById('esLoadingToast');
    const loadingText = document.getElementById('esLoadingText');

    function setLoading(on, text) {
        if (!loadingEl) {
            return;
        }
        loadingEl.classList.toggle('d-none', !on);
        if (loadingText && text) {
            loadingText.textContent = text;
        }
    }

    async function apiPost(action, payload) {
        const body = Object.assign({ action, csrf_token: csrf }, payload || {});
        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        let data = {};
        try {
            data = await res.json();
        } catch (e) {
            data = { ok: false, error: 'Invalid server response.' };
        }
        if (!res.ok && !data.error) {
            data.error = data.message || 'Request failed.';
        }
        return data;
    }

    async function apiGet(params) {
        const qs = new URLSearchParams(params);
        const res = await fetch(`${apiUrl}?${qs}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        return res.json();
    }

    function smtpPayload() {
        const form = document.getElementById('esSmtpForm');
        if (!form) {
            return {};
        }
        const fd = new FormData(form);
        const data = Object.fromEntries(fd.entries());
        data.smtp_secure = document.getElementById('smtp_secure')?.value || 'tls';
        return data;
    }

    function renderSteps(container, steps) {
        if (!container || !Array.isArray(steps)) {
            return;
        }
        container.innerHTML = steps
            .map((s) => {
                const cls =
                    s.status === 'success'
                        ? 'es-step-ok'
                        : s.status === 'failed'
                          ? 'es-step-fail'
                          : 'es-step-run';
                const icon =
                    s.status === 'success'
                        ? 'check-circle-fill'
                        : s.status === 'failed'
                          ? 'x-circle-fill'
                          : 'arrow-repeat';
                const detail = s.detail ? `<span class="text-muted ms-1">${escapeHtml(s.detail)}</span>` : '';
                return `<li class="${cls}"><i class="bi bi-${icon} me-1"></i>${escapeHtml(s.label)}${detail}</li>`;
            })
            .join('');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderConnectionResult(steps, ok, error) {
        const el = document.getElementById('esConnectionResult');
        if (!el) {
            return;
        }
        if (!steps || !steps.length) {
            el.innerHTML = escapeHtml(error || 'No result.');
            return;
        }
        el.innerHTML =
            (ok ? '<div class="text-success mb-2"><i class="bi bi-check-circle me-1"></i>Connection successful</div>' : '') +
            '<ul class="list-unstyled mb-0">' +
            steps
                .map((s) => {
                    const cls =
                        s.status === 'success' ? 'text-success' : s.status === 'failed' ? 'text-danger' : 'text-primary';
                    return `<li class="small ${cls}">${escapeHtml(s.label)}${s.detail ? ' — ' + escapeHtml(s.detail) : ''}</li>`;
                })
                .join('') +
            '</ul>' +
            (error && !ok ? `<div class="text-danger small mt-2">${escapeHtml(error)}</div>` : '');
    }

    async function runConnectionTest(btn) {
        if (btn) {
            btn.disabled = true;
        }
        setLoading(true, 'Testing SMTP connection…');
        const payload = canEdit ? smtpPayload() : {};
        const data = await apiPost('test_connection', payload);
        setLoading(false);
        if (btn) {
            btn.disabled = false;
        }
        renderConnectionResult(data.steps, data.ok, data.error);
        const stepsEl = document.getElementById('esTestSteps');
        if (stepsEl && data.steps) {
            renderSteps(stepsEl, data.steps);
        }
        toast(data.ok ? 'Connection successful.' : data.error || 'Connection failed.', data.ok ? 'success' : 'danger');
    }

    const saveBtn = document.getElementById('esSaveSmtpBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', async () => {
            saveBtn.disabled = true;
            setLoading(true, 'Saving SMTP settings…');
            const data = await apiPost('save', smtpPayload());
            setLoading(false);
            saveBtn.disabled = false;
            if (data.ok) {
                toast(data.message || 'SMTP settings saved.', 'success');
                const pw = document.getElementById('smtp_password');
                if (pw) {
                    pw.value = '';
                    pw.placeholder = '•••••••• (unchanged)';
                }
            } else {
                const msg = data.errors ? Object.values(data.errors).join(' ') : data.error;
                toast(msg || 'Save failed.', 'danger');
            }
        });
    }

    document.querySelectorAll('#esTestConnectionBtn, #esTestConnectionBtn2').forEach((btn) => {
        btn.addEventListener('click', () => runConnectionTest(btn));
    });

    const sendTestBtn = document.getElementById('esSendTestBtn');
    if (sendTestBtn) {
        sendTestBtn.addEventListener('click', async () => {
            const to = document.getElementById('esTestRecipient')?.value?.trim() || '';
            if (!to) {
                toast('Enter a recipient email.', 'warning');
                return;
            }
            const stepsEl = document.getElementById('esTestSteps');
            renderSteps(stepsEl, [
                { label: 'Connecting', status: 'running' },
                { label: 'Authenticating', status: 'running' },
                { label: 'Sending', status: 'running' },
            ]);
            sendTestBtn.disabled = true;
            setLoading(true, 'Sending test email…');
            const data = await apiPost('send_test', { to });
            setLoading(false);
            sendTestBtn.disabled = false;
            if (data.steps) {
                renderSteps(stepsEl, data.steps);
            }
            toast(data.ok ? data.message || 'Test email sent.' : data.error || 'Send failed.', data.ok ? 'success' : 'danger');
        });
    }

    const togglePw = document.getElementById('esTogglePassword');
    if (togglePw) {
        togglePw.addEventListener('click', () => {
            const input = document.getElementById('smtp_password');
            if (!input) {
                return;
            }
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            togglePw.querySelector('i')?.classList.toggle('bi-eye', !show);
            togglePw.querySelector('i')?.classList.toggle('bi-eye-slash', show);
        });
    }

    const previewFrame = document.getElementById('esTemplatePreview');
    let activeTemplate = document.querySelector('.es-template-item.active')?.dataset.key || 'registration';

    async function loadTemplatePreview(key) {
        if (!previewFrame) {
            return;
        }
        try {
            const data = await apiGet({ action: 'templates', key });
            if (data.ok && data.html) {
                previewFrame.srcdoc = data.html;
            }
        } catch (e) {
            /* ignore */
        }
    }

    document.querySelectorAll('.es-template-item').forEach((item) => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.es-template-item').forEach((el) => el.classList.remove('active'));
            item.classList.add('active');
            activeTemplate = item.dataset.key || 'registration';
            loadTemplatePreview(activeTemplate);
        });
    });

    document.querySelectorAll('[data-preview]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-preview]').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            const mode = btn.dataset.preview || 'desktop';
            if (previewFrame) {
                previewFrame.className = `es-preview-frame es-preview-${mode}`;
            }
        });
    });

    loadTemplatePreview(activeTemplate);

    const exportBtn = document.getElementById('esExportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', async () => {
            const data = await apiGet({ action: 'export' });
            if (!data.ok) {
                toast(data.error || 'Export failed.', 'danger');
                return;
            }
            const blob = new Blob([JSON.stringify(data.data || data, null, 2)], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `vk-email-config-${new Date().toISOString().slice(0, 10)}.json`;
            a.click();
            URL.revokeObjectURL(a.href);
            toast('Configuration exported.', 'success');
        });
    }

    const restoreBtn = document.getElementById('esRestoreBtn');
    if (restoreBtn) {
        restoreBtn.addEventListener('click', async () => {
            if (!window.confirm('Restore default SMTP settings? This will overwrite current values.')) {
                return;
            }
            setLoading(true, 'Restoring defaults…');
            const data = await apiPost('restore_defaults', {});
            setLoading(false);
            if (data.ok) {
                toast(data.message || 'Defaults restored.', 'success');
                window.location.reload();
            } else {
                toast(data.error || 'Restore failed.', 'danger');
            }
        });
    }

    const importBtn = document.getElementById('esImportBtn');
    const importFile = document.getElementById('esImportFile');
    if (importBtn && importFile) {
        importBtn.addEventListener('click', () => importFile.click());
        importFile.addEventListener('change', async () => {
            const file = importFile.files?.[0];
            if (!file) {
                return;
            }
            try {
                const text = await file.text();
                const config = JSON.parse(text);
                const payload = config.data || config;
                if (!window.confirm('Import configuration? Current SMTP settings will be overwritten (password is not imported).')) {
                    importFile.value = '';
                    return;
                }
                setLoading(true, 'Importing configuration…');
                const data = await apiPost('import', { config: payload });
                setLoading(false);
                importFile.value = '';
                if (data.ok) {
                    toast(data.message || 'Configuration imported.', 'success');
                    window.location.reload();
                } else {
                    toast(data.error || 'Import failed.', 'danger');
                }
            } catch (e) {
                importFile.value = '';
                toast('Invalid configuration file.', 'danger');
            }
        });
    }

    const saveInboxBtn = document.getElementById('esSaveInboxBtn');
    if (saveInboxBtn) {
        saveInboxBtn.addEventListener('click', async () => {
            saveInboxBtn.disabled = true;
            setLoading(true, 'Saving inbox settings…');
            const data = await apiPost('save_inbox', {
                imap_host: document.getElementById('imap_host')?.value || '',
                imap_port: document.getElementById('imap_port')?.value || 993,
                imap_username: document.getElementById('imap_username')?.value || '',
                imap_password: document.getElementById('imap_password')?.value || '',
                imap_poll_enabled: document.getElementById('imap_poll_enabled')?.checked ? '1' : '0',
                email_autoresponder_enabled: document.getElementById('email_autoresponder_enabled')?.checked ? '1' : '0',
                email_autoresponder_subject: document.getElementById('email_autoresponder_subject')?.value || '',
                email_autoresponder_body: document.getElementById('email_autoresponder_body')?.value || '',
            });
            setLoading(false);
            saveInboxBtn.disabled = false;
            toast(data.ok ? data.message || 'Inbox settings saved.' : data.error || 'Save failed.', data.ok ? 'success' : 'danger');
        });
    }
})();
