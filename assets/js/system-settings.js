(function () {
    'use strict';

    const BASE = window.VK_BASE_URL || '';
    const CSRF = window.VK_CSRF_TOKEN || '';

    const tabKeys = {
        general: ['company_name', 'site_title', 'site_name', 'company_tagline', 'business_slogan'],
        navigation: ['navbar_cta_text', 'navbar_cta_url', 'announcement_enabled', 'announcement_text', 'announcement_url'],
        contact: ['contact_phone', 'contact_phone_alt', 'support_email', 'sales_email', 'whatsapp_number', 'business_hours', 'company_address', 'google_maps_embed', 'branches_json', 'whatsapp_default_message'],
        social: ['facebook_url', 'instagram_url', 'linkedin_url', 'tiktok_url', 'youtube_url', 'twitter_url'],
        homepage: ['hero_title', 'hero_subtitle', 'hero_primary_cta_text', 'hero_primary_cta_url', 'hero_secondary_cta_text', 'hero_secondary_cta_url', 'home_stats_json', 'services_section_title', 'services_section_subtitle', 'testimonials_title'],
        seo: ['seo_site_title', 'seo_meta_description', 'seo_meta_keywords', 'seo_auto_enabled', 'seo_locations', 'seo_service_slugs', 'seo_canonical_url', 'robots_txt', 'seo_schema_markup'],
        theme: ['theme_primary', 'theme_secondary', 'theme_accent', 'theme_gradient_start', 'theme_gradient_end', 'theme_glow', 'button_style', 'card_style'],
        email: ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_secure', 'email_from', 'from_name', 'email_autoresponder_enabled', 'email_autoresponder_subject', 'email_autoresponder_body'],
        security: ['security_maintenance_mode', 'security_readonly_staff'],
        footer: ['footer_text', 'footer_bottom_text', 'analytics_domain', 'analytics_script_src'],
    };

    function toast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type || 'success');
        } else {
            alert(message);
        }
    }

    function fieldByKey(key) {
        return document.querySelector('[data-setting-key="' + CSS.escape(key) + '"]');
    }

    function readField(el) {
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return el.value || '';
    }

    function collectKeys(keys) {
        const settings = {};
        keys.forEach(function (key) {
            const el = fieldByKey(key);
            if (!el) return;
            if ((key === 'smtp_password' || key === 'imap_password') && readField(el) === '') return;
            settings[key] = readField(el);
        });
        return settings;
    }

    function collectTab(tab) {
        return collectKeys(tabKeys[tab] || []);
    }

    async function postJson(url, payload) {
        const res = await fetch(BASE + url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json().catch(function () {
            return { ok: false, error: 'Invalid response' };
        });
        if (!res.ok || !data.ok) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    async function saveTab(tab) {
        return postJson('/api/settings_save.php', { tab: tab, settings: collectTab(tab), csrf_token: CSRF });
    }

    async function saveAll() {
        const all = {};
        Object.keys(tabKeys).forEach(function (tab) {
            Object.assign(all, collectTab(tab));
        });
        return postJson('/api/settings_save.php', { tab: 'all', settings: all, csrf_token: CSRF });
    }

    document.querySelectorAll('.btn-save-tab').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const tab = btn.getAttribute('data-tab');
            if (!tab) return;
            btn.disabled = true;
            try {
                await saveTab(tab);
                toast('Settings saved successfully', 'success');
                if (tab === 'email') {
                    const pw = fieldByKey('smtp_password');
                    if (pw) pw.value = '';
                }
            } catch (e) {
                toast(e.message || 'Save failed', 'danger');
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.querySelector('[data-save-all]')?.addEventListener('click', async function (e) {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
            await saveAll();
            toast('All settings saved', 'success');
        } catch (err) {
            toast(err.message || 'Save failed', 'danger');
        } finally {
            btn.disabled = false;
        }
    });

    function digitsOnly(s) {
        return String(s || '').replace(/\D+/g, '');
    }

    document.getElementById('btnTestWhatsapp')?.addEventListener('click', function () {
        const num = readField(fieldByKey('whatsapp_number'));
        const msg = readField(fieldByKey('whatsapp_default_message')) || 'Hello';
        let n = digitsOnly(num);
        if (!n) {
            toast('Enter a WhatsApp number first', 'warning');
            return;
        }
        if (n.length === 10 && n.indexOf('07') === 0) n = '94' + n.slice(1);
        else if (n.length === 9 && n.indexOf('7') === 0) n = '94' + n;
        window.open('https://wa.me/' + n + '?text=' + encodeURIComponent(msg), '_blank', 'noopener,noreferrer');
    });

    const brandingForm = document.getElementById('brandingForm');
    if (brandingForm) {
        function setTilePreview(key, url) {
            if (!key || !url) return;
            document.querySelectorAll('[data-preview-for="' + CSS.escape(key) + '"]').forEach(function (img) {
                img.src = url;
                img.classList.add('is-loaded');
            });
            const input = document.getElementById(key);
            const tile = input?.closest('[data-upload-tile]');
            const preview = tile?.querySelector('.vk-upload-preview');
            if (preview && !preview.querySelector('img')) {
                const img = document.createElement('img');
                img.alt = 'Uploaded image preview';
                img.src = url;
                img.dataset.previewFor = key;
                img.className = 'is-loaded';
                preview.replaceChildren(img);
            }
            const hidden = fieldByKey(key);
            if (hidden && hidden.type === 'hidden') hidden.value = url;
            if (key === 'company_logo') {
                const live = document.querySelector('[data-live-logo]');
                if (live) live.src = url;
            }
        }

        function previewFile(input) {
            const file = input.files && input.files[0];
            if (!file) return;
            const tile = input.closest('[data-upload-tile]');
            const preview = tile?.querySelector('.vk-upload-preview');
            if (!preview) return;
            tile.classList.add('is-ready');
            const img = document.createElement('img');
            img.alt = 'Selected image preview';
            img.src = URL.createObjectURL(file);
            img.onload = function () {
                img.classList.add('is-loaded');
            };
            preview.replaceChildren(img);
            if (input.id === 'company_logo') {
                const live = document.querySelector('[data-live-logo]');
                if (live) live.src = img.src;
            }
        }

        brandingForm.querySelectorAll('input[type="file"]').forEach(function (input) {
            input.addEventListener('change', function () {
                previewFile(input);
            });
        });

        brandingForm.querySelectorAll('[data-upload-tile]').forEach(function (tile) {
            const input = tile.querySelector('input[type="file"]');
            if (!input) return;
            ['dragenter', 'dragover'].forEach(function (eventName) {
                tile.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    tile.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (eventName) {
                tile.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    tile.classList.remove('is-dragover');
                });
            });
            tile.addEventListener('drop', function (e) {
                if (!e.dataTransfer?.files?.length) return;
                input.files = e.dataTransfer.files;
                previewFile(input);
            });
        });

        brandingForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const alertBox = document.getElementById('brandingAlert');
            const btn = brandingForm.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
            if (alertBox) {
                alertBox.className = 'alert d-none mt-3';
                alertBox.textContent = '';
            }
            const formData = new FormData(brandingForm);
            formData.append('csrf_token', CSRF);
            try {
                brandingForm.classList.add('is-uploading');
                const res = await fetch(BASE + '/api/branding_save.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF },
                    body: formData,
                });
                const data = await res.json().catch(function () {
                    return { ok: false, error: 'Invalid response' };
                });
                if (!res.ok || !data.ok) throw new Error(data.errors?.join(', ') || data.error || 'Save failed');
                if (alertBox) {
                    alertBox.className = 'alert alert-success mt-3';
                    alertBox.textContent = 'Branding saved successfully.';
                }
                Object.entries(data.assets || {}).forEach(function ([key, asset]) {
                    setTilePreview(key, asset.url);
                });
                toast('Branding saved', 'success');
            } catch (err) {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger mt-3';
                    alertBox.textContent = err.message || 'Save failed';
                }
            } finally {
                brandingForm.classList.remove('is-uploading');
                if (btn) btn.disabled = false;
            }
        });
    }

    document.getElementById('settingsSearch')?.addEventListener('input', function (e) {
        const q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('.vk-setting-field').forEach(function (field) {
            const hay = field.getAttribute('data-setting-search') || '';
            field.classList.toggle('d-none', q !== '' && !hay.includes(q));
        });
    });

    function updateLivePreview() {
        const pairs = [
            ['company_name', '[data-live-company]'],
            ['company_tagline', '[data-live-tagline]'],
            ['hero_title', '[data-live-hero-title]'],
            ['hero_subtitle', '[data-live-hero-subtitle]'],
            ['hero_primary_cta_text', '[data-live-cta]'],
            ['footer_text', '[data-live-footer]'],
        ];
        pairs.forEach(function ([key, selector]) {
            const target = document.querySelector(selector);
            const el = fieldByKey(key);
            if (target && el) target.textContent = readField(el);
        });
        const contact = document.querySelector('[data-live-contact]');
        if (contact) {
            contact.textContent = [readField(fieldByKey('contact_phone')), readField(fieldByKey('support_email'))].filter(Boolean).join(' · ');
        }
        const root = document.documentElement;
        const primary = readField(fieldByKey('theme_primary')) || '#3b82f6';
        const secondary = readField(fieldByKey('theme_secondary')) || '#14b8a6';
        root.style.setProperty('--vk-preview-primary', primary);
        root.style.setProperty('--vk-preview-secondary', secondary);
    }

    document.querySelectorAll('[data-setting-key]').forEach(function (el) {
        el.addEventListener('input', updateLivePreview);
        el.addEventListener('change', updateLivePreview);
    });
    updateLivePreview();

    document.getElementById('settingsImportForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = e.currentTarget;
        const box = document.getElementById('backupAlert');
        const data = new FormData(form);
        data.append('csrf_token', CSRF);
        try {
            const res = await fetch(BASE + '/api/settings_import.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
                body: data,
            });
            const json = await res.json().catch(function () { return { ok: false, error: 'Invalid response' }; });
            if (!res.ok || !json.ok) throw new Error(json.error || 'Import failed');
            if (box) {
                box.className = 'alert alert-success mt-3';
                box.textContent = 'Imported ' + json.imported + ' settings. Reloading...';
            }
            setTimeout(function () { location.reload(); }, 900);
        } catch (err) {
            if (box) {
                box.className = 'alert alert-danger mt-3';
                box.textContent = err.message || 'Import failed';
            }
        }
    });

    document.querySelector('[data-restore-defaults]')?.addEventListener('click', async function () {
        if (!confirm('Restore default settings? Existing customized values for known defaults will be overwritten.')) return;
        try {
            await postJson('/api/settings_restore_defaults.php', { csrf_token: CSRF });
            toast('Defaults restored', 'success');
            setTimeout(function () { location.reload(); }, 700);
        } catch (err) {
            toast(err.message || 'Restore failed', 'danger');
        }
    });

    if (window.location.hash) {
        const trigger = document.querySelector('[data-bs-target="' + CSS.escape(window.location.hash) + '"]');
        if (trigger && window.bootstrap && window.bootstrap.Tab) {
            new window.bootstrap.Tab(trigger).show();
        }
    }
})();
