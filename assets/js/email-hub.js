(function () {
    'use strict';

    const BASE = window.VK_BASE_URL || '';
    const CSRF = window.VK_CSRF_TOKEN || '';

    function collectHub() {
        return {
            imap_poll_enabled: document.getElementById('imap_poll_enabled')?.checked ? '1' : '0',
            imap_host: document.getElementById('imap_host')?.value ?? '',
            imap_port: String(document.getElementById('imap_port')?.value ?? '993'),
            imap_username: document.getElementById('imap_username')?.value ?? '',
            email_autoresponder_enabled: document.getElementById('email_autoresponder_enabled')?.checked ? '1' : '0',
            email_autoresponder_subject: document.getElementById('email_autoresponder_subject')?.value ?? '',
            email_autoresponder_body: document.getElementById('email_autoresponder_body')?.value ?? '',
        };
    }

    document.getElementById('btnSaveEmailHub')?.addEventListener('click', async function () {
        const btn = document.getElementById('btnSaveEmailHub');
        const settings = collectHub();
        const pw = document.getElementById('imap_password')?.value ?? '';
        if (pw !== '') {
            settings.imap_password = pw;
        }
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(BASE + '/api/settings_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ tab: 'email_hub', settings: settings, csrf_token: CSRF }),
            });
            const data = await res.json().catch(function () {
                return { ok: false, error: 'Invalid response' };
            });
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Save failed');
            }
            const pwi = document.getElementById('imap_password');
            if (pwi) pwi.value = '';
            if (typeof window.showToast === 'function') {
                window.showToast('Email & inbox settings saved', 'success');
            }
        } catch (e) {
            const msg = e && e.message ? e.message : 'Save failed';
            if (typeof window.showToast === 'function') {
                window.showToast(msg, 'danger');
            } else {
                alert(msg);
            }
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    document.getElementById('btnArResetDefault')?.addEventListener('click', function () {
        const sub = document.getElementById('email_autoresponder_subject');
        const body = document.getElementById('email_autoresponder_body');
        if (sub) sub.value = 'Thank you for contacting VK IT';
        if (body) {
            body.value =
                'Hello,\n\nThank you for contacting us. We have received your email and will respond shortly.\n\nRegards,\nVK IT Team';
        }
    });

    document.getElementById('btnImapPresetVkitnet')?.addEventListener('click', function () {
        const h = document.getElementById('imap_host');
        const p = document.getElementById('imap_port');
        if (h) h.value = 'vkitnet.info';
        if (p) p.value = '993';
    });
})();
