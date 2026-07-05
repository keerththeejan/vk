(function () {
    'use strict';

    const baseUrl = window.VK_BASE_URL || window.BASE_URL || '';
    const API = baseUrl + '/api/invoice_print_settings.php';
    const previewId = window.IPS_PREVIEW_ID || 0;
    const stampPresets = window.IPS_STAMP_PRESETS || {};
    const csrf = window.VK_CSRF_TOKEN || '';

    const app = document.getElementById('invoicePrintSettingsApp');
    if (!app) return;

    const frame = document.getElementById('ipsPreviewFrame');
    let debounceTimer = null;
    let settings = {};

    function collectSettings() {
        const out = {};
        app.querySelectorAll('[data-setting-key]').forEach(function (el) {
            const key = el.getAttribute('data-setting-key');
            if (!key) return;
            if (el.type === 'checkbox') {
                out[key] = el.checked ? 1 : 0;
            } else {
                out[key] = el.value;
            }
        });
        return out;
    }

    function applySettingsToForm(data) {
        app.querySelectorAll('[data-setting-key]').forEach(function (el) {
            const key = el.getAttribute('data-setting-key');
            if (!key || !(key in data)) return;
            if (el.type === 'checkbox') {
                el.checked = !!Number(data[key]);
            } else {
                el.value = data[key];
            }
            if (el.type === 'range') {
                const hint = app.querySelector('.ips-range-val[data-for="' + el.id + '"]');
                if (hint) hint.textContent = el.value;
            }
        });
        app.querySelectorAll('.ips-preview-img').forEach(function (img) {
            const pathKey = img.getAttribute('data-path-key');
            if (pathKey && data[pathKey]) {
                const base = baseUrl + '/';
                img.src = base + String(data[pathKey]).replace(/^\//, '') + '?t=' + Date.now();
            }
        });
    }

    function toast(msg, type) {
        if (window.showToast) {
            window.showToast(msg, type || 'success');
        } else {
            alert(msg);
        }
    }

    function api(action, opts) {
        opts = opts || {};
        const method = opts.method || 'GET';
        const headers = { 'X-CSRF-TOKEN': csrf };
        const init = { method: method, headers: headers, credentials: 'same-origin' };
        if (opts.body) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        return fetch(API + '?action=' + encodeURIComponent(action), init).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok || !j.ok) throw new Error(j.error || 'Request failed');
                return j;
            });
        });
    }

    function refreshPreview() {
        if (!frame || !previewId) return;
        settings = collectSettings();
        api('preview', { method: 'POST', body: settings }).then(function () {
            frame.src = baseUrl + '/modules/invoices/print.php?id=' + previewId + '&settings_preview=1&t=' + Date.now();
        }).catch(function () { /* silent */ });
    }

    function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshPreview, 350);
    }

    app.addEventListener('input', function (e) {
        const t = e.target;
        if (!t.matches('[data-setting-key]')) return;
        if (t.type === 'range') {
            const hint = app.querySelector('.ips-range-val[data-for="' + t.id + '"]');
            if (hint) hint.textContent = t.value;
        }
        if (t.getAttribute('data-setting-key') === 'stamp_preset' && stampPresets[t.value]) {
            const p = stampPresets[t.value];
            const w = app.querySelector('[data-setting-key="stamp_width_mm"]');
            const h = app.querySelector('[data-setting-key="stamp_height_mm"]');
            if (w) w.value = p.width ?? p.width_mm;
            if (h) h.value = p.height ?? p.height_mm;
        }
        schedulePreview();
    });

    app.addEventListener('change', function (e) {
        if (e.target.matches('.ips-upload')) {
            const field = e.target.getAttribute('data-field');
            const file = e.target.files && e.target.files[0];
            if (!field || !file) return;
            const fd = new FormData();
            fd.append('field', field);
            fd.append('file', file);
            fd.append('csrf_token', csrf);
            fetch(API + '?action=upload', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (!j.ok) throw new Error(j.error || 'Upload failed');
                applySettingsToForm(j.settings);
                toast('Uploaded successfully');
                schedulePreview();
            }).catch(function (err) { toast(err.message, 'danger'); });
            e.target.value = '';
        }
    });

    app.querySelectorAll('.ips-delete-asset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const field = btn.getAttribute('data-field');
            if (!field || !confirm('Reset this asset to default?')) return;
            const fd = new FormData();
            fd.append('field', field);
            fd.append('csrf_token', csrf);
            fetch(API + '?action=delete_asset', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (!j.ok) throw new Error(j.error || 'Delete failed');
                applySettingsToForm(j.settings);
                toast('Asset reset');
                schedulePreview();
            }).catch(function (err) { toast(err.message, 'danger'); });
        });
    });

    document.getElementById('ipsSaveBtn')?.addEventListener('click', function () {
        api('save', { method: 'POST', body: collectSettings() }).then(function (j) {
            applySettingsToForm(j.settings);
            toast('Settings saved');
        }).catch(function (err) { toast(err.message, 'danger'); });
    });

    document.getElementById('ipsResetBtn')?.addEventListener('click', function () {
        if (!confirm('Reset all print settings to defaults?')) return;
        api('reset', { method: 'POST' }).then(function (j) {
            applySettingsToForm(j.settings);
            toast('Reset to defaults');
            schedulePreview();
        }).catch(function (err) { toast(err.message, 'danger'); });
    });

    document.getElementById('ipsBackupBtn')?.addEventListener('click', function () {
        api('backup', { method: 'POST' }).then(function () {
            toast('Backup created');
        }).catch(function (err) { toast(err.message, 'danger'); });
    });

    document.getElementById('ipsRestoreBtn')?.addEventListener('click', function () {
        if (!confirm('Restore settings from last backup?')) return;
        api('restore', { method: 'POST' }).then(function (j) {
            applySettingsToForm(j.settings);
            toast('Restored from backup');
            schedulePreview();
        }).catch(function (err) { toast(err.message, 'danger'); });
    });

    api('get').then(function (j) {
        applySettingsToForm(j.settings);
    }).catch(function () { /* form has server defaults */ });
})();
