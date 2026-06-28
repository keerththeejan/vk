/**
 * Product Create API layer — AJAX transport, no page reloads.
 */
(function (global) {
    'use strict';

    const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';

    const ProductCreateApi = {
        url: meta('pc-api-url') || window.location.href.split('?')[0],
        uploadUrl: meta('pc-upload-url') || '',

        async request(formData, intent) {
            formData.set('intent', intent);
            const res = await fetch(this.url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.error || `Request failed (${res.status})`);
            }
            return data;
        },

        async checkSku(sku) {
            const fd = new FormData();
            fd.set('intent', 'check_sku');
            fd.set('sku', sku);
            return this.request(fd, 'check_sku');
        },

        async detectDuplicate(name, sku) {
            const fd = new FormData();
            fd.set('intent', 'detect_duplicate');
            fd.set('name', name);
            fd.set('sku', sku);
            return this.request(fd, 'detect_duplicate');
        },

        async uploadMedia(file) {
            if (!this.uploadUrl) {
                return { success: true, local: true, preview: URL.createObjectURL(file) };
            }
            const fd = new FormData();
            fd.append('file', file);
            const res = await fetch(this.uploadUrl, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.error || 'Upload failed');
            }
            return data;
        },
    };

    global.ProductCreateApi = ProductCreateApi;
})(window);
