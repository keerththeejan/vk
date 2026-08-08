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
        data.smtp_auth = document.getElementById('smtp_auth')?.checked ? '1' : '0';
        data.smtp_debug = document.getElementById('smtp_debug')?.checked ? '1' : '0';
        return data;
    }

    function showReasons(reasons, errorText) {
        const box = document.getElementById('esReasonsBox');
        if (!box) return;
        if ((!reasons || !reasons.length) && !errorText) {
            box.classList.add('d-none');
            box.innerHTML = '';
            return;
        }
        const list = (reasons && reasons.length)
            ? '<strong>Possible reasons:</strong><ul class="mb-0 mt-1">' + reasons.map((r) => `<li>${escapeHtml(r)}</li>`).join('') + '</ul>'
            : '';
        const err = errorText ? `<div class="mb-2" style="white-space:pre-wrap">${escapeHtml(errorText)}</div>` : '';
        box.innerHTML = err + list;
        box.classList.remove('d-none');
    }

    function showDebug(text) {
        const el = document.getElementById('esDebugTranscript');
        if (!el) return;
        if (!text) {
            el.classList.add('d-none');
            el.textContent = '';
            return;
        }
        el.textContent = text;
        el.classList.remove('d-none');
    }

    function renderConnectionResult(steps, ok, error, reasons, report) {
        const el = document.getElementById('esConnectionResult');
        if (!el) {
            return;
        }
        let reportHtml = '';
        if (report && typeof report === 'object') {
            reportHtml =
                '<div class="es-report mt-3 small border-top pt-2">' +
                (report.root_cause
                    ? `<div><strong>Root Cause:</strong> ${escapeHtml(String(report.root_cause))}</div>`
                    : '<div><strong>Root Cause:</strong> None — authentication succeeded</div>') +
                (report.recommended_fix
                    ? `<div><strong>Recommended Fix:</strong> ${escapeHtml(String(report.recommended_fix))}</div>`
                    : '') +
                (report.server_response
                    ? `<div><strong>Server Response:</strong> <code>${escapeHtml(String(report.server_response))}</code></div>`
                    : '') +
                `<div><strong>SMTP Host:</strong> ${escapeHtml(String(report.smtp_host || ''))} · <strong>Port:</strong> ${escapeHtml(String(report.port || ''))} · <strong>Encryption:</strong> ${escapeHtml(String(report.encryption || ''))}</div>` +
                `<div><strong>Authentication Result:</strong> ${escapeHtml(String(report.authentication_result || ''))}</div>` +
                '</div>';
        }
        if (!steps || !steps.length) {
            el.innerHTML = escapeHtml(error || 'No result.') + reportHtml;
            showReasons(reasons, error);
            return;
        }
        el.innerHTML =
            (ok ? '<div class="text-success mb-2"><i class="bi bi-check-circle me-1"></i>Connection successful</div>' : '<div class="text-danger mb-2"><i class="bi bi-x-circle me-1"></i>Connection failed</div>') +
            '<ul class="list-unstyled mb-0">' +
            steps
                .map((s) => {
                    const cls =
                        s.status === 'success' ? 'text-success' : s.status === 'failed' ? 'text-danger' : 'text-primary';
                    return `<li class="small ${cls}"><i class="bi bi-${s.status === 'success' ? 'check-circle' : s.status === 'failed' ? 'x-circle' : 'arrow-repeat'} me-1"></i>${escapeHtml(s.label)}${s.detail ? ' — ' + escapeHtml(String(s.detail)) : ''}</li>`;
                })
                .join('') +
            '</ul>' +
            reportHtml;
        showReasons(reasons, ok ? '' : error);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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
                const detail = s.detail ? `<span class="text-muted ms-1">${escapeHtml(String(s.detail))}</span>` : '';
                return `<li class="${cls}"><i class="bi bi-${icon} me-1"></i>${escapeHtml(s.label)}${detail}</li>`;
            })
            .join('');
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
        renderConnectionResult(data.steps, data.ok, data.error, data.reasons, data.report);
        showDebug(data.transcript || data.debug || '');
        const stepsEl = document.getElementById('esTestSteps');
        if (stepsEl && data.steps) {
            renderSteps(stepsEl, data.steps);
        }
        const toastMsg = data.ok
            ? 'Connection successful.'
            : (data.report && data.report.root_cause
                ? data.report.root_cause
                : (data.reasons && data.reasons[0]
                    ? 'Authentication / connection failed — see diagnostics.'
                    : (data.error || 'Connection failed.')));
        toast(toastMsg, data.ok ? 'success' : 'danger');
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
                { label: 'DNS Lookup', status: 'running' },
                { label: 'SMTP Connection', status: 'running' },
                { label: 'Authentication', status: 'running' },
                { label: 'Send Test Mail', status: 'running' },
            ]);
            sendTestBtn.disabled = true;
            setLoading(true, 'Sending test email…');

            const payload = Object.assign(smtpPayload(), {
                to,
                subject: document.getElementById('esTestSubject')?.value || 'VK Network — SMTP Test',
                message: document.getElementById('esTestMessage')?.value || '',
                debug: document.getElementById('smtp_debug')?.checked ? '1' : '0',
            });

            const fileInput = document.getElementById('esTestAttachment');
            let data;
            if (fileInput && fileInput.files && fileInput.files[0]) {
                const fd = new FormData();
                Object.keys(payload).forEach((k) => fd.append(k, payload[k]));
                fd.append('action', 'send_test');
                fd.append('csrf_token', csrf);
                fd.append('attachment', fileInput.files[0]);
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
                    credentials: 'same-origin',
                    body: fd,
                });
                data = await res.json();
            } else {
                data = await apiPost('send_test', payload);
            }

            setLoading(false);
            sendTestBtn.disabled = false;
            if (data.steps) {
                renderSteps(stepsEl, data.steps);
                renderConnectionResult(data.steps, data.ok, data.error, data.reasons, data.report);
            }
            showDebug(data.debug || '');
            showReasons(data.reasons, data.ok ? '' : data.error);
            toast(data.ok ? data.message || 'Test email sent.' : 'Send failed — see diagnostics.', data.ok ? 'success' : 'danger');
        });
    }

    document.querySelectorAll('.es-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
            const host = btn.dataset.host || '';
            const port = btn.dataset.port || '587';
            const secure = btn.dataset.secure || 'tls';
            const auth = btn.dataset.auth !== '0';
            const hint = btn.dataset.hint || '';
            const hostEl = document.getElementById('smtp_host');
            const portEl = document.getElementById('smtp_port');
            const secEl = document.getElementById('smtp_secure');
            const authEl = document.getElementById('smtp_auth');
            if (hostEl && host) hostEl.value = host;
            if (portEl) portEl.value = port;
            if (secEl) secEl.value = secure;
            if (authEl) authEl.checked = auth;
            const hintEl = document.getElementById('esPresetHint');
            if (hintEl) hintEl.textContent = hint || 'Preset applied.';
            toast('Preset applied: ' + (btn.textContent || '').trim(), 'success');
        });
    });

    const processQueueBtn = document.getElementById('esProcessQueueBtn');
    if (processQueueBtn) {
        processQueueBtn.addEventListener('click', async () => {
            processQueueBtn.disabled = true;
            const data = await apiPost('queue_process', {});
            processQueueBtn.disabled = false;
            toast(data.ok ? (data.message || 'Queue processed.') : data.error || 'Failed', data.ok ? 'success' : 'danger');
            if (data.ok) setTimeout(() => location.reload(), 700);
        });
    }

    document.querySelectorAll('.es-queue-retry').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const data = await apiPost('queue_retry', { id: btn.dataset.id });
            toast(data.ok ? data.message : data.error || 'Failed', data.ok ? 'success' : 'danger');
        });
    });

    document.querySelectorAll('.es-log-resend').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!window.confirm('Resend this email?')) return;
            const data = await apiPost('log_resend', { id: btn.dataset.id });
            toast(data.ok ? data.message : data.error || 'Failed', data.ok ? 'success' : 'danger');
            if (!data.ok) showReasons(data.reasons, data.error);
        });
    });

    document.querySelectorAll('.es-log-delete').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!window.confirm('Delete this log entry?')) return;
            const data = await apiPost('log_delete', { id: btn.dataset.id });
            toast(data.ok ? data.message : data.error || 'Failed', data.ok ? 'success' : 'danger');
            if (data.ok) btn.closest('tr')?.remove();
        });
    });

    let activeLogId = 0;
    const logModalEl = document.getElementById('esLogModal');
    const logModal = logModalEl && window.bootstrap ? new bootstrap.Modal(logModalEl) : null;

    function fillLogFromRow(tr) {
        if (!tr) return null;
        return {
            id: tr.dataset.logId || '',
            to: tr.dataset.to || '',
            subject: tr.dataset.subject || '',
            body: tr.dataset.body || '',
            error: tr.dataset.error || '',
            status: tr.dataset.status || '',
        };
    }

    document.querySelectorAll('.es-log-view').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tr = btn.closest('tr');
            const row = fillLogFromRow(tr);
            if (!row || !logModal) return;
            activeLogId = Number(row.id) || 0;
            document.getElementById('esLogModalTitle').textContent = 'View email';
            document.getElementById('esLogViewPane')?.classList.remove('d-none');
            document.getElementById('esLogEditPane')?.classList.add('d-none');
            document.getElementById('esLogRetryBtn')?.classList.add('d-none');
            document.getElementById('esLogViewStatus').textContent = row.status || '—';
            document.getElementById('esLogViewTo').textContent = row.to || '—';
            document.getElementById('esLogViewSubject').textContent = row.subject || '—';
            document.getElementById('esLogViewBody').textContent = row.body || '—';
            document.getElementById('esLogViewError').textContent = row.error || '—';
            logModal.show();
        });
    });

    document.querySelectorAll('.es-log-edit').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tr = btn.closest('tr');
            const row = fillLogFromRow(tr);
            if (!row || !logModal) return;
            activeLogId = Number(row.id) || 0;
            document.getElementById('esLogModalTitle').textContent = 'Edit & retry failed email';
            document.getElementById('esLogViewPane')?.classList.add('d-none');
            document.getElementById('esLogEditPane')?.classList.remove('d-none');
            const retryBtn = document.getElementById('esLogRetryBtn');
            retryBtn?.classList.remove('d-none');
            document.getElementById('esEditTo').value = row.to || '';
            document.getElementById('esEditSubject').value = row.subject || '';
            document.getElementById('esEditMessage').value = row.body || '';
            const errBox = document.getElementById('esEditErrorBox');
            if (errBox) {
                if (row.error) {
                    errBox.textContent = 'Last error: ' + row.error;
                    errBox.classList.remove('d-none');
                } else {
                    errBox.classList.add('d-none');
                }
            }
            logModal.show();
        });
    });

    const retryBtn = document.getElementById('esLogRetryBtn');
    if (retryBtn) {
        retryBtn.addEventListener('click', async () => {
            if (!activeLogId) return;
            retryBtn.disabled = true;
            const data = await apiPost('log_resend', {
                id: activeLogId,
                to_email: document.getElementById('esEditTo')?.value || '',
                subject: document.getElementById('esEditSubject')?.value || '',
                message: document.getElementById('esEditMessage')?.value || '',
            });
            retryBtn.disabled = false;
            toast(data.ok ? data.message : data.error || 'Retry failed', data.ok ? 'success' : 'danger');
            if (!data.ok) {
                const errBox = document.getElementById('esEditErrorBox');
                if (errBox) {
                    errBox.textContent = data.error || 'Retry failed';
                    errBox.classList.remove('d-none');
                }
                showReasons(data.reasons, data.error);
            } else {
                logModal?.hide();
                setTimeout(() => location.reload(), 700);
            }
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
