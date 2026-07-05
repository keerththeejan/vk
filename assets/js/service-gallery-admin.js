(function () {
    'use strict';

    const BASE = window.VK_BASE_URL || '';
    const MAX_BYTES = 3 * 1024 * 1024;
    const ACCEPT = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showToast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type || 'success');
        }
    }

    const app = document.getElementById('vkSgApp');
    if (!app) {
        return;
    }

    const apiUrl = app.getAttribute('data-api-url') || BASE + '/api/service_gallery.php';
    const csrf = app.getAttribute('data-csrf') || window.VK_CSRF_TOKEN || '';
    let perms = {};
    try {
        perms = JSON.parse(app.getAttribute('data-permissions') || '{}');
    } catch (e) {
        perms = {};
    }

    const storedPerPage = localStorage.getItem('vkSgPerPage') || app.getAttribute('data-per-page') || '20';

    const state = {
        serviceId: parseInt(app.getAttribute('data-initial-service') || '0', 10) || 0,
        page: 1,
        total: 0,
        totalPages: 1,
        perPage: storedPerPage,
        sort: 'newest',
        q: '',
        dateFrom: '',
        dateTo: '',
        category: '',
        status: '',
        featured: '',
        items: [],
        bulkMode: false,
        lbIndex: 0,
        lbZoom: 1,
        searchTimer: null,
    };

    const el = {
        filterService: document.getElementById('vkSgFilterService'),
        filterCategory: document.getElementById('vkSgFilterCategory'),
        filterStatus: document.getElementById('vkSgFilterStatus'),
        filterFeatured: document.getElementById('vkSgFilterFeatured'),
        sort: document.getElementById('vkSgSort'),
        perPage: document.getElementById('vkSgPerPage'),
        search: document.getElementById('vkSgSearch'),
        dateFrom: document.getElementById('vkSgDateFrom'),
        dateTo: document.getElementById('vkSgDateTo'),
        apply: document.getElementById('vkSgApplyFilters'),
        reset: document.getElementById('vkSgResetFilters'),
        bulkToggle: document.getElementById('vkSgBulkToggle'),
        bulkBar: document.getElementById('vkSgBulkBar'),
        bulkAction: document.getElementById('vkSgBulkAction'),
        bulkMoveTarget: document.getElementById('vkSgBulkMoveTarget'),
        bulkRun: document.getElementById('vkSgBulkRun'),
        grid: document.getElementById('gridcss'),
        empty: document.getElementById('vkSgEmpty'),
        meta: document.getElementById('vkSgResultMeta'),
        pager: document.getElementById('vkSgPager'),
        pagePrev: document.getElementById('vkSgPagePrev'),
        pageNext: document.getElementById('vkSgPageNext'),
        pageLabel: document.getElementById('vkSgPageLabel'),
        dropzone: document.getElementById('vkSgDropzone'),
        fileInput: document.getElementById('vkSgFileInput'),
        previewStrip: document.getElementById('vkSgPreviewStrip'),
        queue: document.getElementById('vkSgUploadQueue'),
        uploadHint: document.getElementById('vkSgUploadHint'),
        exportCsv: document.getElementById('vkSgExportCsv'),
        exportJson: document.getElementById('vkSgExportJson'),
        lbModal: document.getElementById('vkSgLightbox'),
        lbImg: document.getElementById('vkSgLightboxImg'),
        lbTitle: document.getElementById('vkSgLightboxTitle'),
        lbMeta: document.getElementById('vkSgLightboxMeta'),
        lbInfo: document.getElementById('vkSgLightboxInfo'),
        lbDownload: document.getElementById('vkSgLbDownload'),
        lbPrev: document.getElementById('vkSgLbPrev'),
        lbNext: document.getElementById('vkSgLbNext'),
        lbFullscreen: document.getElementById('vkSgLbLbFullscreen'),
        lbTouch: document.getElementById('vkSgLbTouchArea'),
        lbZoomIn: document.getElementById('vkSgLbZoomIn'),
        lbZoomOut: document.getElementById('vkSgLbZoomOut'),
        lbZoomReset: document.getElementById('vkSgLbZoomReset'),
        delModal: document.getElementById('vkSgDeleteModal'),
        delTarget: document.getElementById('vkSgDeleteTargetId'),
        delBtn: document.getElementById('vkSgDeleteConfirmBtn'),
        editModal: document.getElementById('vkSgEditModal'),
        editId: document.getElementById('vkSgEditId'),
        editTitle: document.getElementById('vkSgEditTitle'),
        editDescription: document.getElementById('vkSgEditDescription'),
        editAlt: document.getElementById('vkSgEditAlt'),
        editSeo: document.getElementById('vkSgEditSeo'),
        editCategory: document.getElementById('vkSgEditCategory'),
        editOrder: document.getElementById('vkSgEditOrder'),
        editStatus: document.getElementById('vkSgEditStatus'),
        editFeatured: document.getElementById('vkSgEditFeatured'),
        editSave: document.getElementById('vkSgEditSave'),
    };

    let lbModalInst = null;
    let delModalInst = null;
    let editModalInst = null;
    let touchStartX = 0;

    function listParams() {
        return {
            service_id: String(state.serviceId),
            page: String(state.page),
            per_page: state.perPage,
            sort: state.sort,
            q: state.q,
            date_from: state.dateFrom,
            date_to: state.dateTo,
            category: state.category,
            status: state.status,
            featured: state.featured,
        };
    }

    function syncUrl() {
        const u = new URL(window.location.href);
        if (state.serviceId > 0) {
            u.searchParams.set('service_id', String(state.serviceId));
        } else {
            u.searchParams.delete('service_id');
        }
        window.history.replaceState({}, '', u.pathname + u.search);
    }

    function updateUploadUi() {
        const canUpload = state.serviceId > 0 && perms.can_upload;
        if (el.dropzone) {
            el.dropzone.classList.toggle('opacity-50', !canUpload);
            el.dropzone.style.pointerEvents = canUpload ? '' : 'none';
        }
        if (el.uploadHint) {
            el.uploadHint.textContent = canUpload
                ? 'Images are optimized into full, medium, and thumbnail sizes.'
                : 'Select a specific album (not “All albums”) to enable uploads.';
        }
        if (el.fileInput) {
            el.fileInput.disabled = !canUpload;
        }
        const pub = document.getElementById('vkSgPublicServiceLink');
        if (pub) {
            if (state.serviceId > 0) {
                pub.href = BASE + '/service-details.php?id=' + state.serviceId;
                pub.classList.remove('disabled', 'pe-none');
            } else {
                pub.href = '#';
                pub.classList.add('disabled', 'pe-none');
            }
        }
    }

    function setBulkUi() {
        document.body.classList.toggle('vk-sg-bulk-on', state.bulkMode);
        if (el.bulkBar) {
            el.bulkBar.classList.toggle('d-none', !state.bulkMode);
        }
    }

    function selectedIds() {
        const cbs = el.grid.querySelectorAll('.vk-sg-bulk-cb input:checked:not(:disabled)');
        return Array.prototype.map.call(cbs, function (c) {
            return parseInt(c.value, 10);
        });
    }

    function statusBadge(status) {
        const map = { published: 'success', hidden: 'secondary', draft: 'warning' };
        const cls = map[status] || 'secondary';
        return '<span class="badge text-bg-' + cls + '">' + esc(status || 'published') + '</span>';
    }

    async function fetchList() {
        const params = new URLSearchParams(Object.assign({ action: 'list' }, listParams()));
        const res = await fetch(apiUrl + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await res.json().catch(function () {
            return { ok: false };
        });
        if (!res.ok || !data.ok) {
            throw new Error((data && data.error) || 'Could not load gallery.');
        }
        state.items = data.items || [];
        state.total = data.total || 0;
        const pp = state.perPage === 'all' ? state.total || 1 : parseInt(state.perPage, 10) || 20;
        state.totalPages = state.perPage === 'all' ? 1 : Math.max(1, Math.ceil(state.total / pp));
        el.grid.innerHTML = '';
        state.items.forEach(function (it) {
            el.grid.appendChild(renderCard(it));
        });
        el.empty.classList.toggle('d-none', state.items.length > 0);
        if (el.meta) {
            el.meta.textContent =
                state.total > 0
                    ? 'Showing ' + state.items.length + ' of ' + state.total + ' · Page ' + state.page + '/' + state.totalPages
                    : '';
        }
        if (el.pager) {
            const showPager = state.perPage !== 'all' && state.totalPages > 1;
            el.pager.classList.toggle('d-none', !showPager);
            if (el.pageLabel) {
                el.pageLabel.textContent = 'Page ' + state.page;
            }
            if (el.pagePrev) {
                el.pagePrev.disabled = state.page <= 1;
            }
            if (el.pageNext) {
                el.pageNext.disabled = state.page >= state.totalPages;
            }
        }
    }

    function renderCard(it) {
        const article = document.createElement('article');
        article.className = 'card vk-sg-admin-card h-100';
        article.dataset.itemId = String(it.id);

        const thumbUrl = it.thumb_url || it.image_url || '';
        const title = it.title || '—';
        const svc = it.service_name || '';
        const isSample = !!it.is_sample;
        const when = (it.created_at || '').replace('T', ' ').slice(0, 16);
        const fileHint = it.original_filename || '';
        const featuredBadge = it.is_featured ? '<span class="badge text-bg-warning position-absolute top-0 end-0 m-2"><i class="bi bi-star-fill me-1"></i>Featured</span>' : '';
        const sampleBadge = isSample ? '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle position-absolute top-0 start-0 m-2 ms-5">Sample</span>' : '';

        const checkboxHtml = isSample
            ? '<input type="checkbox" class="form-check-input m-0" disabled>'
            : '<input type="checkbox" class="form-check-input m-0" value="' + it.id + '" aria-label="Select">';

        let actions = '<button type="button" class="btn btn-outline-primary vk-sg-view"><i class="bi bi-eye"></i></button>';
        if (!isSample && perms.can_edit) {
            actions += '<button type="button" class="btn btn-outline-secondary vk-sg-edit" data-id="' + it.id + '"><i class="bi bi-pencil"></i></button>';
        }
        if (!isSample && perms.can_delete) {
            actions += '<button type="button" class="btn btn-outline-danger vk-sg-del" data-id="' + it.id + '"><i class="bi bi-trash"></i></button>';
        }

        article.innerHTML =
            '<div class="position-relative vk-sg-thumb-wrap">' +
            '<label class="vk-sg-bulk-cb position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75">' +
            checkboxHtml +
            '</label>' +
            featuredBadge +
            sampleBadge +
            (thumbUrl
                ? '<img class="vk-sg-thumb vk-sg-lazy" src="' + esc(thumbUrl) + '" alt="' + esc(it.alt_text || title) + '" loading="lazy" width="400" height="225">'
                : '<div class="vk-sg-skeleton w-100 h-100"></div>') +
            '</div>' +
            '<div class="card-body py-2 px-3">' +
            '<div class="d-flex justify-content-between align-items-start gap-1 mb-1">' +
            '<div class="fw-semibold small text-truncate flex-grow-1" title="' + esc(title) + '">' + esc(title) + '</div>' +
            (isSample ? '' : statusBadge(it.status)) +
            '</div>' +
            '<div class="text-muted small text-truncate"><i class="bi bi-folder2-open me-1"></i>' + esc(svc) + '</div>' +
            '<div class="text-muted small">' + esc(it.category || 'general') + ' · ' + esc(it.file_size_label || '') + ' · ' + esc(it.resolution || '') + '</div>' +
            '<div class="text-muted small">' + esc(when) + (it.uploader_name ? ' · ' + esc(it.uploader_name) : '') + '</div>' +
            (fileHint ? '<div class="text-muted small text-truncate" title="' + esc(fileHint) + '">' + esc(fileHint) + '</div>' : '') +
            '</div>' +
            '<div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">' +
            '<div class="btn-group btn-group-sm w-100">' + actions + '</div></div>';

        return article;
    }

    function refreshGrid() {
        return fetchList();
    }

    function openLightbox(index) {
        if (index < 0 || index >= state.items.length) {
            return;
        }
        state.lbIndex = index;
        state.lbZoom = 1;
        applyLbZoom();
        const it = state.items[index];
        el.lbImg.src = it.image_url || '';
        el.lbImg.alt = it.alt_text || it.title || '';
        el.lbTitle.textContent = it.title || 'Gallery';
        el.lbMeta.textContent = (it.service_name || '') + (it.created_at ? ' · ' + String(it.created_at).slice(0, 10) : '');
        if (el.lbDownload) {
            el.lbDownload.href = it.image_url || '#';
            el.lbDownload.download = it.original_filename || 'image';
        }
        if (el.lbInfo) {
            el.lbInfo.innerHTML =
                esc(it.file_size_label || '') +
                ' · ' +
                esc(it.resolution || '') +
                ' · ' +
                esc(it.category || '') +
                ' · ' +
                esc(it.uploader_name || '');
        }
        if (!lbModalInst) {
            lbModalInst = new bootstrap.Modal(el.lbModal);
        }
        lbModalInst.show();
    }

    function applyLbZoom() {
        el.lbImg.style.transform = 'scale(' + state.lbZoom + ')';
        el.lbImg.classList.toggle('vk-sg-zoomed', state.lbZoom > 1.01);
    }

    function lbStep(delta) {
        const n = state.items.length;
        if (n < 1) {
            return;
        }
        openLightbox((state.lbIndex + delta + n) % n);
    }

    async function apiPost(action, payload) {
        const body = Object.assign({ action: action, csrf_token: csrf }, payload || {});
        const res = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(function () {
            return {};
        });
        if (!res.ok || data.ok === false) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    function validateFile(f) {
        if (f.size > MAX_BYTES) {
            return 'Max 3MB: ' + f.name;
        }
        if (ACCEPT.indexOf(f.type) === -1 && !/\.(jpe?g|png|webp|gif|svg)$/i.test(f.name)) {
            return 'Unsupported type: ' + f.name;
        }
        return null;
    }

    function showPreviews(files) {
        if (!el.previewStrip) {
            return;
        }
        el.previewStrip.innerHTML = '';
        el.previewStrip.classList.toggle('d-none', !files.length);
        Array.prototype.slice.call(files, 0, 8).forEach(function (f) {
            const wrap = document.createElement('div');
            wrap.className = 'vk-sg-preview-item';
            if (f.type.startsWith('image/') && f.type !== 'image/svg+xml') {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.onload = function () {
                    URL.revokeObjectURL(img.src);
                };
                wrap.appendChild(img);
            } else {
                wrap.textContent = f.name;
            }
            el.previewStrip.appendChild(wrap);
        });
    }

    function uploadOne(file, onProgress) {
        return new Promise(function (resolve, reject) {
            const fd = new FormData();
            fd.append('action', 'upload');
            fd.append('csrf_token', csrf);
            fd.append('service_id', String(state.serviceId));
            fd.append('file', file);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', apiUrl);
            xhr.setRequestHeader('X-CSRF-Token', csrf);
            xhr.onload = function () {
                let data = {};
                try {
                    data = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    data = {};
                }
                if (xhr.status >= 200 && xhr.status < 300 && data.ok) {
                    resolve(data.item);
                } else {
                    reject(new Error(data.error || 'Upload failed'));
                }
            };
            xhr.onerror = function () {
                reject(new Error('Network error'));
            };
            if (xhr.upload && onProgress) {
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) {
                        onProgress(e.loaded / e.total);
                    }
                };
            }
            xhr.send(fd);
        });
    }

    async function handleFiles(fileList) {
        if (state.serviceId <= 0 || !perms.can_upload) {
            showToast('Select an album before uploading.', 'warning');
            return;
        }
        const files = Array.prototype.slice.call(fileList || [], 0).slice(0, 24);
        if (!files.length) {
            return;
        }
        showPreviews(files);
        el.queue.hidden = false;
        el.queue.innerHTML = '';
        let okCount = 0;
        for (let i = 0; i < files.length; i++) {
            const f = files[i];
            const err = validateFile(f);
            const row = document.createElement('div');
            row.className = 'vk-sg-upload-row border-bottom py-2';
            const bar = document.createElement('div');
            bar.className = 'progress mt-1';
            bar.style.height = '4px';
            bar.innerHTML = '<div class="progress-bar" style="width:0%"></div>';
            row.innerHTML =
                '<div class="d-flex justify-content-between"><span class="text-truncate me-2">' +
                esc(f.name) +
                '</span><span class="vk-sg-up-status text-muted small">…</span></div>';
            row.appendChild(bar);
            el.queue.appendChild(row);
            const statusEl = row.querySelector('.vk-sg-up-status');
            const barInner = bar.querySelector('.progress-bar');
            if (err) {
                statusEl.textContent = err;
                statusEl.classList.add('text-danger');
                continue;
            }
            try {
                statusEl.textContent = 'Uploading…';
                await uploadOne(f, function (p) {
                    barInner.style.width = Math.round(p * 100) + '%';
                });
                barInner.classList.add('bg-success');
                statusEl.textContent = 'Done';
                okCount++;
            } catch (e) {
                barInner.classList.add('bg-danger');
                statusEl.textContent = e.message || 'Failed';
                statusEl.classList.add('text-danger');
            }
        }
        showToast(okCount ? okCount + ' image(s) uploaded.' : 'Upload finished with errors.', okCount ? 'success' : 'warning');
        if (el.previewStrip) {
            el.previewStrip.classList.add('d-none');
        }
        await refreshGrid();
    }

    function applyFiltersFromUi() {
        state.serviceId = parseInt(el.filterService.value, 10) || 0;
        state.sort = el.sort.value === 'oldest' ? 'oldest' : 'newest';
        state.q = el.search.value.trim();
        state.dateFrom = el.dateFrom.value;
        state.dateTo = el.dateTo.value;
        state.category = el.filterCategory ? el.filterCategory.value : '';
        state.status = el.filterStatus ? el.filterStatus.value : '';
        state.featured = el.filterFeatured ? el.filterFeatured.value : '';
        state.perPage = el.perPage ? el.perPage.value : '20';
        localStorage.setItem('vkSgPerPage', state.perPage);
        state.page = 1;
        syncUrl();
        updateUploadUi();
    }

    function bindGridClicks() {
        el.grid.addEventListener('click', function (ev) {
            const t = ev.target;
            if (t.closest('.vk-sg-view')) {
                const card = t.closest('[data-item-id]');
                const id = parseInt(card && card.getAttribute('data-item-id'), 10);
                const idx = state.items.findIndex(function (x) {
                    return x.id === id;
                });
                openLightbox(idx);
            } else if (t.closest('.vk-sg-edit')) {
                const id = parseInt(t.closest('.vk-sg-edit').getAttribute('data-id') || '0', 10);
                const row = state.items.find(function (x) {
                    return x.id === id;
                });
                if (!row) {
                    return;
                }
                el.editId.value = String(id);
                el.editTitle.value = row.title || '';
                el.editDescription.value = row.description || '';
                el.editAlt.value = row.alt_text || '';
                el.editSeo.value = row.seo_keywords || '';
                el.editCategory.value = row.category || 'general';
                el.editOrder.value = row.display_order || 0;
                el.editStatus.value = row.status || 'published';
                el.editFeatured.checked = !!row.is_featured;
                if (!editModalInst) {
                    editModalInst = new bootstrap.Modal(el.editModal);
                }
                editModalInst.show();
            } else if (t.closest('.vk-sg-del')) {
                const id = parseInt(t.closest('.vk-sg-del').getAttribute('data-id') || '0', 10);
                if (id > 0) {
                    el.delTarget.value = String(id);
                    if (!delModalInst) {
                        delModalInst = new bootstrap.Modal(el.delModal);
                    }
                    delModalInst.show();
                }
            }
        });
    }

    if (el.apply) {
        el.apply.addEventListener('click', function () {
            applyFiltersFromUi();
            refreshGrid().catch(function (e) {
                showToast(e.message, 'danger');
            });
        });
    }

    if (el.reset) {
        el.reset.addEventListener('click', function () {
            el.filterService.value = '0';
            if (el.filterCategory) {
                el.filterCategory.value = '';
            }
            if (el.filterStatus) {
                el.filterStatus.value = '';
            }
            if (el.filterFeatured) {
                el.filterFeatured.value = '';
            }
            el.sort.value = 'newest';
            el.search.value = '';
            el.dateFrom.value = '';
            el.dateTo.value = '';
            applyFiltersFromUi();
            refreshGrid().catch(function (e) {
                showToast(e.message, 'danger');
            });
        });
    }

    if (el.search) {
        el.search.addEventListener('input', function () {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(function () {
                state.q = el.search.value.trim();
                state.page = 1;
                refreshGrid().catch(function () {});
            }, 350);
        });
    }

    if (el.filterService) {
        el.filterService.addEventListener('change', function () {
            state.serviceId = parseInt(el.filterService.value, 10) || 0;
            updateUploadUi();
        });
    }

    if (el.pagePrev) {
        el.pagePrev.addEventListener('click', function () {
            if (state.page > 1) {
                state.page--;
                refreshGrid().catch(function (e) {
                    showToast(e.message, 'danger');
                });
            }
        });
    }

    if (el.pageNext) {
        el.pageNext.addEventListener('click', function () {
            if (state.page < state.totalPages) {
                state.page++;
                refreshGrid().catch(function (e) {
                    showToast(e.message, 'danger');
                });
            }
        });
    }

    if (el.bulkToggle) {
        el.bulkToggle.addEventListener('click', function () {
            state.bulkMode = !state.bulkMode;
            setBulkUi();
        });
    }

    if (el.bulkAction) {
        el.bulkAction.addEventListener('change', function () {
            if (el.bulkMoveTarget) {
                el.bulkMoveTarget.classList.toggle('d-none', el.bulkAction.value !== 'move');
            }
        });
    }

    if (el.bulkRun) {
        el.bulkRun.addEventListener('click', async function () {
            const ids = selectedIds();
            if (!ids.length) {
                showToast('Select at least one image.', 'warning');
                return;
            }
            const action = el.bulkAction.value;
            if (!action) {
                showToast('Choose a bulk action.', 'warning');
                return;
            }
            if (action === 'zip') {
                window.location.href = apiUrl + '?action=zip&ids=' + ids.join(',');
                return;
            }
            if (action === 'move') {
                const target = parseInt(el.bulkMoveTarget.value, 10) || 0;
                if (!target) {
                    showToast('Select target album.', 'warning');
                    return;
                }
                try {
                    await apiPost('bulk', { bulk_action: 'move', ids: ids, target_service_id: target });
                    showToast('Images moved.', 'success');
                    await refreshGrid();
                } catch (e) {
                    showToast(e.message, 'danger');
                }
                return;
            }
            if (action === 'delete' && !window.confirm('Delete ' + ids.length + ' image(s)?')) {
                return;
            }
            try {
                await apiPost('bulk', { bulk_action: action, ids: ids });
                showToast('Bulk action completed.', 'success');
                await refreshGrid();
            } catch (e) {
                showToast(e.message, 'danger');
            }
        });
    }

    if (el.delBtn) {
        el.delBtn.addEventListener('click', async function () {
            const id = parseInt(el.delTarget.value || '0', 10);
            if (id <= 0) {
                return;
            }
            try {
                await apiPost('delete', { id: id });
                delModalInst.hide();
                showToast('Image deleted.', 'success');
                await refreshGrid();
            } catch (e) {
                showToast(e.message, 'danger');
            }
        });
    }

    if (el.editSave) {
        el.editSave.addEventListener('click', async function () {
            const id = parseInt(el.editId.value || '0', 10);
            const title = el.editTitle.value.trim();
            if (id <= 0 || !title) {
                showToast('Title is required.', 'warning');
                return;
            }
            try {
                await apiPost('update', {
                    id: id,
                    title: title,
                    description: el.editDescription.value,
                    alt_text: el.editAlt.value,
                    seo_keywords: el.editSeo.value,
                    category: el.editCategory.value,
                    display_order: parseInt(el.editOrder.value, 10) || 0,
                    status: el.editStatus.value,
                    is_featured: el.editFeatured.checked,
                });
                editModalInst.hide();
                showToast('Image updated.', 'success');
                await refreshGrid();
            } catch (e) {
                showToast(e.message, 'danger');
            }
        });
    }

    if (el.exportCsv) {
        el.exportCsv.addEventListener('click', function () {
            const p = new URLSearchParams(Object.assign({ action: 'export', format: 'csv' }, listParams()));
            window.location.href = apiUrl + '?' + p.toString();
        });
    }

    if (el.exportJson) {
        el.exportJson.addEventListener('click', async function () {
            const p = new URLSearchParams(Object.assign({ action: 'export', format: 'json' }, listParams()));
            const res = await fetch(apiUrl + '?' + p.toString());
            const data = await res.json();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'gallery-' + new Date().toISOString().slice(0, 10) + '.json';
            a.click();
            URL.revokeObjectURL(a.href);
        });
    }

    ['lbPrev', 'lbNext', 'lbZoomIn', 'lbZoomOut', 'lbZoomReset'].forEach(function (key) {
        if (!el[key]) {
            return;
        }
        el[key].addEventListener('click', function () {
            if (key === 'lbPrev') {
                lbStep(-1);
            } else if (key === 'lbNext') {
                lbStep(1);
            } else if (key === 'lbZoomIn') {
                state.lbZoom = Math.min(3, Math.round((state.lbZoom + 0.25) * 100) / 100);
                applyLbZoom();
            } else if (key === 'lbZoomOut') {
                state.lbZoom = Math.max(0.5, Math.round((state.lbZoom - 0.25) * 100) / 100);
                applyLbZoom();
            } else {
                state.lbZoom = 1;
                applyLbZoom();
            }
        });
    });

    if (el.lbFullscreen) {
        el.lbFullscreen.addEventListener('click', function () {
            const wrap = el.lbModal.querySelector('.modal-content');
            if (wrap && wrap.requestFullscreen) {
                wrap.requestFullscreen();
            }
        });
    }

    if (el.lbTouch) {
        el.lbTouch.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        });
        el.lbTouch.addEventListener('touchend', function (e) {
            const dx = e.changedTouches[0].screenX - touchStartX;
            if (Math.abs(dx) > 50) {
                lbStep(dx > 0 ? -1 : 1);
            }
        });
    }

    if (el.dropzone) {
        el.dropzone.addEventListener('click', function () {
            if (state.serviceId > 0 && perms.can_upload) {
                el.fileInput.click();
            }
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
            el.dropzone.addEventListener(ev, function (e) {
                e.preventDefault();
                el.dropzone.classList.add('vk-sg-dropzone--active');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            el.dropzone.addEventListener(ev, function (e) {
                e.preventDefault();
                if (ev === 'dragleave') {
                    el.dropzone.classList.remove('vk-sg-dropzone--active');
                }
            });
        });
        el.dropzone.addEventListener('drop', function (e) {
            el.dropzone.classList.remove('vk-sg-dropzone--active');
            handleFiles(e.dataTransfer.files);
        });
    }

    if (el.fileInput) {
        el.fileInput.addEventListener('change', function () {
            handleFiles(el.fileInput.files);
            el.fileInput.value = '';
        });
    }

    document.addEventListener('keydown', function (e) {
        if (!el.lbModal.classList.contains('show')) {
            return;
        }
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            lbStep(-1);
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            lbStep(1);
        } else if (e.key === 'Escape') {
            lbModalInst && lbModalInst.hide();
        }
    });

    if (el.perPage) {
        el.perPage.value = storedPerPage;
        state.perPage = storedPerPage;
    }
    if (el.filterService) {
        el.filterService.value = String(state.serviceId);
    }
    updateUploadUi();
    setBulkUi();
    bindGridClicks();

    refreshGrid().catch(function (e) {
        showToast(e.message || 'Load failed', 'danger');
    });
})();
