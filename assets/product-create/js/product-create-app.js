/**
 * Product Create App — wizard, validation, preview, autosave.
 */
(function () {
    'use strict';

    const form = document.getElementById('productCreateForm');
    const root = document.getElementById('productCreateRoot');
    if (!form || !root) return;

    const api = window.ProductCreateApi;
    const intentInput = document.getElementById('pcIntent');
    const loadingBar = document.getElementById('pcLoadingBar');
    const toastHost = document.getElementById('pcToastHost');
    const undoStack = [];
    let autosaveTimer = null;
    let lastSnapshot = null;

    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    function showLoading(on) {
        if (loadingBar) loadingBar.hidden = !on;
    }

    function toast(message, type = 'info') {
        const id = 'pcToast' + Date.now();
        const bg = { success: 'text-bg-success', error: 'text-bg-danger', info: 'text-bg-primary' }[type] || 'text-bg-secondary';
        const el = document.createElement('div');
        el.id = id;
        el.className = `toast align-items-center ${bg} border-0`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        toastHost?.appendChild(el);
        const t = new bootstrap.Toast(el, { delay: 3500 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function slugify(v) {
        return v.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'product';
    }

    function num(id) {
        const el = document.getElementById(id);
        return el ? parseFloat(el.value) || 0 : 0;
    }

    function val(id) {
        const el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function pushActivity(text) {
        const list = $('#pcActivityTimeline');
        if (!list) return;
        const li = document.createElement('li');
        li.className = 'pc-timeline-item';
        li.innerHTML = `<span class="pc-timeline-dot"></span>${escapeHtml(text)}`;
        list.prepend(li);
        while (list.children.length > 8) list.lastElementChild?.remove();
    }

    function snapshotForm() {
        const data = new FormData(form);
        const obj = {};
        data.forEach((v, k) => {
            if (obj[k] !== undefined) {
                if (!Array.isArray(obj[k])) obj[k] = [obj[k]];
                obj[k].push(v);
            } else {
                obj[k] = v;
            }
        });
        return obj;
    }

    function restoreSnapshot(snap) {
        if (!snap) return;
        $$('input, select, textarea', form).forEach((el) => {
            const name = el.name;
            if (!name || !(name in snap)) return;
            const v = snap[name];
            if (el.type === 'checkbox') {
                el.checked = !!v;
            } else if (el.type === 'file') {
                /* skip */
            } else {
                el.value = Array.isArray(v) ? v[0] : v;
            }
        });
        refreshAll();
    }

    function saveUndo() {
        undoStack.push(snapshotForm());
        if (undoStack.length > 20) undoStack.shift();
    }

    function updatePreview() {
        const name = val('name') || 'Untitled Product';
        const sku = val('sku') || '—';
        const price = num('selling_price');
        const cur = val('currency') || 'USD';
        $('#pcPreviewName').textContent = name;
        $('#pcPreviewSku').textContent = 'SKU: ' + sku;
        $('#pcPreviewPrice').textContent = new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(price);

        const serpTitle = val('meta_title') || name;
        const serpUrl = (val('seo_url') || slugify(name)) + ' — example.com/product';
        const serpDesc = val('meta_description') || val('short_description') || 'Meta description preview.';
        const st = $('#pcSerpTitle'); if (st) st.textContent = serpTitle;
        const su = $('#pcSerpUrl'); if (su) su.textContent = serpUrl;
        const sd = $('#pcSerpDesc'); if (sd) sd.textContent = serpDesc;

        const mt = $('#pcMetaTitleCount'); if (mt) mt.textContent = val('meta_title').length;
        const md = $('#pcMetaDescCount'); if (md) md.textContent = val('meta_description').length;
    }

    function updatePricing() {
        const cost = num('cost_price');
        const sell = num('selling_price');
        const profit = sell - cost;
        const margin = sell > 0 ? (profit / sell) * 100 : 0;
        const markup = cost > 0 ? (profit / cost) * 100 : 0;
        const fmt = (n) => (Number.isFinite(n) ? n.toFixed(2) : '—');
        const gp = $('#pcGrossProfit'); if (gp) gp.textContent = fmt(profit);
        const mp = $('#pcMarginPct'); if (mp) mp.textContent = fmt(margin) + '%';
        const mk = $('#pcMarkupPct'); if (mk) mk.textContent = fmt(markup) + '%';
        const pm = $('#profit_margin'); if (pm) pm.value = fmt(margin);
        const anM = $('#pcAnMargin'); if (anM) anM.textContent = fmt(margin) + '%';
        const stock = num('opening_stock');
        const rev = sell * stock;
        const ar = $('#pcAnRevenue'); if (ar) ar.textContent = new Intl.NumberFormat().format(rev);
    }

    function updateStockHealth() {
        const current = num('current_stock') || num('opening_stock');
        const min = num('minimum_stock');
        const reorder = num('reorder_level');
        let pct = 100;
        let label = 'Healthy';
        if (current <= 0) { pct = 5; label = 'Out of stock'; }
        else if (current <= min) { pct = 25; label = 'Critical'; }
        else if (current <= reorder) { pct = 55; label = 'Low — reorder soon'; }
        else { pct = Math.min(100, 50 + current); label = 'Healthy'; }
        const bar = $('#pcStockHealthBar .progress-bar');
        if (bar) {
            bar.style.width = pct + '%';
            bar.className = 'progress-bar ' + (pct < 30 ? 'bg-danger' : pct < 60 ? 'bg-warning' : 'bg-success');
        }
        const lbl = $('#pcStockHealthLabel'); if (lbl) lbl.textContent = label;
        const inv = $('#pcAnInventory'); if (inv) inv.textContent = label;
    }

    function completenessScore() {
        const checks = [
            !!val('name'),
            !!val('sku'),
            !!val('category_id'),
            !!val('brand_id'),
            num('selling_price') > 0,
            num('opening_stock') >= 0,
            !!val('short_description'),
            !!val('description'),
            !!val('meta_title'),
            !!val('meta_description'),
        ];
        const done = checks.filter(Boolean).length;
        return Math.round((done / checks.length) * 100);
    }

    function seoScore() {
        let s = 0;
        if (val('meta_title').length >= 30 && val('meta_title').length <= 70) s += 30;
        else if (val('meta_title')) s += 15;
        if (val('meta_description').length >= 80) s += 30;
        else if (val('meta_description')) s += 15;
        if (val('seo_url') || val('focus_keyword')) s += 20;
        if (val('meta_keywords')) s += 10;
        if (val('canonical_url')) s += 10;
        return Math.min(100, s);
    }

    function updateCompleteness() {
        const pct = completenessScore();
        const badge = $('#pcCompletionBadge');
        const bar = $('#pcCompletionBar');
        if (badge) badge.textContent = pct + '%';
        if (bar) {
            bar.setAttribute('aria-valuenow', String(pct));
            const inner = bar.querySelector('.progress-bar');
            if (inner) inner.style.width = pct + '%';
        }
        const seo = seoScore();
        const anSeo = $('#pcAnSeo'); if (anSeo) anSeo.textContent = seo + '/100';

        const map = {
            name: !!val('name'),
            sku: !!val('sku'),
            category: !!val('category_id'),
            price: num('selling_price') > 0,
            stock: num('opening_stock') >= 0,
            seo: !!val('meta_title') && !!val('meta_description'),
        };
        $$('#pcChecklist li').forEach((li) => {
            const key = li.dataset.check;
            li.classList.toggle('is-done', !!map[key]);
        });
    }

    function validateForm() {
        let ok = true;
        $$('[required]', form).forEach((el) => {
            el.classList.remove('is-invalid');
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                ok = false;
            }
        });
        if (!val('name')) {
            $('#name')?.classList.add('is-invalid');
            ok = false;
        }
        const summary = $('#pcValidationSummary');
        if (summary) {
            summary.className = ok ? 'alert alert-success small' : 'alert alert-danger small';
            summary.textContent = ok ? 'All required fields passed validation.' : 'Please complete required fields (product name).';
        }
        return ok;
    }

    async function submitIntent(intent) {
        if (intent !== 'draft' && intent !== 'autosave' && !validateForm()) {
            toast('Fix validation errors before publishing.', 'error');
            return;
        }
        saveUndo();
        if (intentInput) intentInput.value = intent;
        const fd = new FormData(form);
        showLoading(true);
        try {
            const data = await api.request(fd, intent);
            if (data.redirect) {
                toast(intent === 'publish_new' ? 'Product published. Starting new…' : 'Product published.', 'success');
                pushActivity('Product published');
                window.location.href = data.redirect;
                return;
            }
            if (data.completeness !== undefined) updateCompleteness();
            toast(intent === 'autosave' ? 'Auto-saved' : 'Draft saved', 'success');
            pushActivity(intent === 'autosave' ? 'Auto-saved draft' : 'Draft saved');
        } catch (err) {
            toast(err.message || 'Save failed', 'error');
        } finally {
            showLoading(false);
        }
    }

    function scheduleAutosave() {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(() => submitIntent('autosave'), 10000);
    }

    function genSku() {
        const prefix = (val('name').slice(0, 3).toUpperCase().replace(/[^A-Z]/g, '') || 'PRD');
        const sku = prefix + '-' + Date.now().toString(36).toUpperCase().slice(-6);
        const el = $('#sku');
        if (el) { el.value = sku; el.dispatchEvent(new Event('change', { bubbles: true })); }
        pushActivity('SKU generated');
    }

    async function checkSkuDebounced() {
        const sku = val('sku');
        const status = $('#pcSkuStatus');
        if (!sku) { if (status) status.textContent = ''; return; }
        try {
            const r = await api.checkSku(sku);
            if (status) {
                status.textContent = r.exists ? 'SKU already in use' : 'SKU available';
                status.className = 'form-text ' + (r.exists ? 'text-danger' : 'text-success');
            }
        } catch { /* ignore */ }
    }

    let skuTimer;
    function onSkuInput() {
        clearTimeout(skuTimer);
        skuTimer = setTimeout(checkSkuDebounced, 400);
    }

    function filterSubcategories() {
        const cat = val('category_id');
        $$('#subcategory_id option[data-parent]').forEach((opt) => {
            opt.hidden = cat !== '' && opt.dataset.parent !== cat;
        });
    }

    function bindTaxClass() {
        const sel = $('#tax_class_id');
        if (!sel) return;
        sel.addEventListener('change', () => {
            const opt = sel.selectedOptions[0];
            const rate = opt?.dataset.rate;
            const tr = $('#tax_rate');
            if (tr && rate) tr.value = rate;
            updatePricing();
        });
    }

    /* Media */
    const gallery = $('#pcGallery');
    const fileInput = $('#images');
    const dropzone = $('#pcDropzone');
    let primaryIndex = 0;
    const mediaFiles = [];

    function renderGallery() {
        if (!gallery) return;
        gallery.innerHTML = '';
        mediaFiles.forEach((item, i) => {
            const col = document.createElement('div');
            col.className = 'col-4 col-sm-3';
            col.innerHTML = `
                <div class="pc-gallery-item ${i === primaryIndex ? 'is-primary' : ''}" data-index="${i}">
                    <img src="${item.preview}" alt="" loading="lazy">
                    <div class="pc-gallery-actions">
                        <button type="button" class="btn btn-sm btn-light py-0 pc-set-primary" data-i="${i}">Primary</button>
                        <button type="button" class="btn btn-sm btn-danger py-0 pc-remove-media" data-i="${i}">×</button>
                    </div>
                </div>`;
            gallery.appendChild(col);
        });
        syncFileInput();
        const first = mediaFiles[primaryIndex];
        const prev = $('#pcPreviewMedia');
        if (prev && first) {
            prev.innerHTML = `<img src="${first.preview}" alt="">`;
        }
    }

    function syncFileInput() {
        if (!fileInput) return;
        const dt = new DataTransfer();
        mediaFiles.forEach((m) => dt.items.add(m.file));
        fileInput.files = dt.files;
    }

    function addFiles(files) {
        Array.from(files).forEach((file) => {
            if (!file.type.startsWith('image/') && file.type !== 'video/mp4' && file.type !== 'application/pdf') return;
            mediaFiles.push({ file, preview: URL.createObjectURL(file) });
        });
        renderGallery();
        pushActivity('Media added');
    }

    function initMedia() {
        $('#pcBrowseMedia')?.addEventListener('click', () => fileInput?.click());
        fileInput?.addEventListener('change', (e) => addFiles(e.target.files));
        dropzone?.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('is-dragover'); });
        dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('is-dragover'));
        dropzone?.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
            addFiles(e.dataTransfer.files);
        });
        gallery?.addEventListener('click', (e) => {
            const p = e.target.closest('.pc-set-primary');
            const r = e.target.closest('.pc-remove-media');
            if (p) { primaryIndex = parseInt(p.dataset.i, 10); renderGallery(); }
            if (r) { mediaFiles.splice(parseInt(r.dataset.i, 10), 1); renderGallery(); }
        });
        $('#pcCompressImages')?.addEventListener('click', () => toast('Images queued for compression (client-side).', 'info'));
        $('#pcAiOptimize')?.addEventListener('click', () => toast('AI optimization queued.', 'info'));
    }

    /* Variants */
    function cartesian(arrays) {
        return arrays.reduce((a, b) => a.flatMap((x) => b.map((y) => x.concat([y]))), [[]]);
    }

    function generateVariants() {
        const dims = ['variant_colors', 'variant_sizes', 'variant_materials', 'variant_storage', 'variant_models']
            .map((id) => val(id).split(',').map((s) => s.trim()).filter(Boolean))
            .filter((a) => a.length);
        const body = $('#pcVariantBody');
        if (!body) return;
        if (!dims.length) {
            body.innerHTML = '<tr class="text-secondary"><td colspan="4">Enter at least one variant dimension.</td></tr>';
            return;
        }
        const combos = cartesian(dims);
        const baseSku = val('sku') || 'VAR';
        body.innerHTML = combos.map((c, i) => {
            const label = c.join(' / ');
            const vsku = baseSku + '-' + (i + 1);
            return `<tr><td>${escapeHtml(label)}</td><td>${escapeHtml(vsku)}</td><td><input type="number" class="form-control form-control-sm" value="${num('selling_price')}"></td><td><input type="number" class="form-control form-control-sm" value="0"></td></tr>`;
        }).join('');
        pushActivity('Variants generated');
    }

    /* AI helpers (client-side heuristics) */
    async function runAi(action) {
        const name = val('name');
        if (!name && action !== 'duplicate') {
            toast('Enter a product name first.', 'error');
            return;
        }
        switch (action) {
            case 'description': {
                const desc = `${name} — premium quality product designed for everyday use. Features reliable performance, modern design, and excellent value. Ideal for retail and wholesale channels.`;
                const d = $('#description'); if (d) d.value = desc;
                const sd = $('#short_description'); if (sd && !sd.value) sd.value = desc.slice(0, 160);
                break;
            }
            case 'category':
                toast('Category suggestion: check top matching categories in your catalog.', 'info');
                break;
            case 'tags': {
                const tags = name.toLowerCase().split(/\s+/).slice(0, 5).join(', ');
                const t = $('#product_tags'); if (t) t.value = tags;
                break;
            }
            case 'seo':
                autoSeo();
                break;
            case 'duplicate': {
                try {
                    const r = await api.detectDuplicate(name, val('sku'));
                    if (r.matches?.length) {
                        toast('Found ' + r.matches.length + ' similar product(s).', 'info');
                    } else {
                        toast('No duplicates detected.', 'success');
                    }
                } catch (e) { toast(e.message, 'error'); }
                break;
            }
            case 'price': {
                const cost = num('cost_price');
                if (cost > 0) {
                    const sell = $('#selling_price');
                    if (sell) sell.value = (cost * 1.35).toFixed(2);
                    updatePricing();
                }
                toast('Suggested price applied (35% markup).', 'success');
                break;
            }
        }
        refreshAll();
        pushActivity('AI: ' + action);
    }

    function autoSeo() {
        const name = val('name');
        if (!name) return;
        const mt = $('#meta_title'); if (mt && !mt.value) mt.value = name.slice(0, 70);
        const md = $('#meta_description'); if (md && !md.value) md.value = (val('short_description') || name).slice(0, 160);
        const su = $('#seo_url'); if (su && !su.value) su.value = slugify(name);
        const fk = $('#focus_keyword'); if (fk && !fk.value) fk.value = name.split(/\s+/)[0].toLowerCase();
        refreshAll();
    }

    function refreshAll() {
        updatePreview();
        updatePricing();
        updateStockHealth();
        updateCompleteness();
        scheduleAutosave();
    }

    function bindToolbar() {
        $$('[data-intent]').forEach((btn) => {
            btn.addEventListener('click', () => submitIntent(btn.dataset.intent));
        });
        $('#pcBtnPreview')?.addEventListener('click', () => {
            updatePreview();
            toast('Preview updated in sidebar.', 'info');
        });
        $('#pcBtnReset')?.addEventListener('click', () => {
            if (!confirm('Reset all fields?')) return;
            saveUndo();
            form.reset();
            mediaFiles.length = 0;
            renderGallery();
            refreshAll();
            pushActivity('Form reset');
        });
        $('#pcReviewPublish')?.addEventListener('click', () => submitIntent('publish'));
        $('#pcReviewValidate')?.addEventListener('click', validateForm);
    }

    function bindShortcuts() {
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                submitIntent('draft');
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                submitIntent('publish');
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                const snap = undoStack.pop();
                if (snap) { restoreSnapshot(snap); toast('Undone', 'info'); }
            }
        });
    }

    function initSearchableSelects() {
        $$('.pc-searchable').forEach((sel) => {
            sel.addEventListener('focus', () => sel.dataset.prevFilter = '');
        });
    }

    form.addEventListener('input', (e) => {
        if (e.target.id === 'sku') onSkuInput();
        if (e.target.id === 'name' && !val('seo_url')) {
            /* defer slug */
        }
        refreshAll();
    });
    form.addEventListener('change', refreshAll);

    $('#pcGenSku')?.addEventListener('click', genSku);
    $('#pcGenSlug')?.addEventListener('click', () => {
        const su = $('#seo_url');
        if (su) su.value = slugify(val('name'));
        refreshAll();
    });
    $('#pcAutoSeo')?.addEventListener('click', autoSeo);
    $('#pcGenVariants')?.addEventListener('click', generateVariants);
    $('#category_id')?.addEventListener('change', filterSubcategories);

    $$('.pc-ai-btn').forEach((btn) => btn.addEventListener('click', () => runAi(btn.dataset.ai)));
    $('#pcQuickDuplicate')?.addEventListener('click', () => runAi('duplicate'));
    $('#pcQuickClone')?.addEventListener('click', () => toast('Clone copies field values to clipboard pattern.', 'info'));
    $('#pcQuickExport')?.addEventListener('click', () => window.print());
    $('#pcQuickTemplate')?.addEventListener('click', () => {
        localStorage.setItem('pc_template', JSON.stringify(snapshotForm()));
        toast('Template saved locally.', 'success');
    });
    $('#pcQuickDeleteDraft')?.addEventListener('click', () => {
        if (confirm('Clear local draft template?')) {
            localStorage.removeItem('pc_template');
            toast('Draft template cleared.', 'info');
        }
    });

    bindToolbar();
    bindShortcuts();
    bindTaxClass();
    initMedia();
    initSearchableSelects();
    filterSubcategories();
    lastSnapshot = snapshotForm();
    refreshAll();
    pushActivity('Wizard ready');
})();
