(function () {
    'use strict';

    const BASE = window.VK_BASE_URL || '';
    const MAX_BYTES = 3 * 1024 * 1024;
    const ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showToast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        } else {
            alert(msg);
        }
    }

    const app = document.getElementById('vkSgApp');
    if (!app) {
        return;
    }

    let services = [];
    try {
        services = JSON.parse(app.getAttribute('data-services') || '[]');
    } catch (e) {
        services = [];
    }

    const PER_PAGE = Math.min(48, Math.max(1, parseInt(app.getAttribute('data-per-page') || '12', 10) || 12));

    const state = {
        serviceId: parseInt(app.getAttribute('data-initial-service') || '0', 10) || 0,
        page: 1,
        total: 0,
        hasMore: false,
        sort: 'newest',
        q: '',
        dateFrom: '',
        dateTo: '',
        items: [],
        bulkMode: false,
        lbIndex: 0,
        lbZoom: 1,
        deleteIds: [],
    };

    const el = {
        filterService: document.getElementById('vkSgFilterService'),
        sort: document.getElementById('vkSgSort'),
        search: document.getElementById('vkSgSearch'),
        dateFrom: document.getElementById('vkSgDateFrom'),
        dateTo: document.getElementById('vkSgDateTo'),
        apply: document.getElementById('vkSgApplyFilters'),
        reset: document.getElementById('vkSgResetFilters'),
        bulkToggle: document.getElementById('vkSgBulkToggle'),
        bulkDelete: document.getElementById('vkSgBulkDelete'),
        grid: document.getElementById('gridcss'),
        empty: document.getElementById('vkSgEmpty'),
        loadMoreWrap: document.getElementById('vkSgLoadMoreWrap'),
        loadMore: document.getElementById('vkSgLoadMore'),
        meta: document.getElementById('vkSgResultMeta'),
        dropzone: document.getElementById('vkSgDropzone'),
        fileInput: document.getElementById('vkSgFileInput'),
        queue: document.getElementById('vkSgUploadQueue'),
        uploadHint: document.getElementById('vkSgUploadHint'),
        lbModal: document.getElementById('vkSgLightbox'),
        lbImg: document.getElementById('vkSgLightboxImg'),
        lbTitle: document.getElementById('vkSgLightboxTitle'),
        lbMeta: document.getElementById('vkSgLightboxMeta'),
        lbPrev: document.getElementById('vkSgLbPrev'),
        lbNext: document.getElementById('vkSgLbNext'),
        lbZoomIn: document.getElementById('vkSgLbZoomIn'),
        lbZoomOut: document.getElementById('vkSgLbZoomOut'),
        lbZoomReset: document.getElementById('vkSgLbZoomReset'),
        delModal: document.getElementById('vkSgDeleteModal'),
        delTarget: document.getElementById('vkSgDeleteTargetId'),
        delBtn: document.getElementById('vkSgDeleteConfirmBtn'),
        editModal: document.getElementById('vkSgEditModal'),
        editId: document.getElementById('vkSgEditId'),
        editTitle: document.getElementById('vkSgEditTitle'),
        editSave: document.getElementById('vkSgEditSave'),
    };

    let lbModalInst = null;
    let delModalInst = null;
    let editModalInst = null;

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
        const canUpload = state.serviceId > 0;
        if (el.dropzone) {
            el.dropzone.classList.toggle('opacity-50', !canUpload);
            el.dropzone.style.pointerEvents = canUpload ? '' : 'none';
        }
        if (el.uploadHint) {
            el.uploadHint.textContent = canUpload
                ? 'Images are resized and saved as WebP or JPEG under uploads/services/gallery/.'
                : 'Select a specific service in the filter (not “All services”) to enable uploads.';
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
        if (el.bulkDelete) {
            el.bulkDelete.classList.toggle('d-none', !state.bulkMode);
        }
    }

    function selectedIds() {
        const cbs = el.grid.querySelectorAll('.vk-sg-bulk-cb input:checked');
        return Array.prototype.map.call(cbs, function (c) {
            return parseInt(c.value, 10);
        });
    }

    async function fetchList(append) {
        const params = new URLSearchParams({
            service_id: String(state.serviceId),
            page: String(state.page),
            per_page: String(PER_PAGE),
            sort: state.sort,
            q: state.q,
            date_from: state.dateFrom,
            date_to: state.dateTo,
        });
        const res = await fetch(BASE + '/api/service_gallery_list.php?' + params.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await res.json().catch(function () {
            return { ok: false };
        });
        if (!res.ok || !data.ok) {
            throw new Error((data && data.error) || 'Could not load gallery.');
        }
        if (!append) {
            state.items = data.items || [];
            el.grid.innerHTML = '';
        } else {
            state.items = state.items.concat(data.items || []);
        }
        state.total = data.total || 0;
        state.hasMore = !!data.has_more;
        (data.items || []).forEach(function (it) {
            el.grid.appendChild(renderCard(it));
        });
        el.empty.classList.toggle('d-none', state.items.length > 0);
        el.loadMoreWrap.classList.toggle('d-none', !state.hasMore);
        const shown = state.items.length;
        if (el.meta) {
            el.meta.textContent =
                state.total > 0 ? 'Showing ' + shown + ' of ' + state.total + ' images' : '';
        }
    }

    function renderCard(it) {
        const article = document.createElement('article');
        article.className = 'card vk-sg-admin-card h-100';
        article.dataset.itemId = String(it.id);

        const thumbUrl = it.image_url || '';
        const title = it.title || '—';
        const svc = it.service_name || '';
        const isSample = !!it.is_sample;
        const when = (it.created_at || '').replace('T', ' ').slice(0, 16);
        const fileHint = it.original_filename || it.image_path.split('/').pop() || '';
        const badgeInline = isSample
            ? '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle mb-2">Sample</span>'
            : '';
        const badgeCorner = isSample
            ? '<span class="position-absolute top-0 end-0 m-2 badge bg-info-subtle text-info-emphasis border border-info-subtle">Sample</span>'
            : '';
        const checkboxHtml =
            '<input type="checkbox" class="form-check-input m-0" value="' +
            it.id +
            (isSample ? '" disabled aria-disabled="true" aria-label="Sample image">' : '" aria-label="Select image">');
        const actionButtons = isSample
            ? '<button type="button" class="btn btn-outline-primary vk-sg-view">View</button>' +
              '<button type="button" class="btn btn-outline-secondary" disabled>Demo only</button>'
            : '<button type="button" class="btn btn-outline-primary vk-sg-view">View</button>' +
              '<button type="button" class="btn btn-outline-secondary vk-sg-edit" data-id="' +
              it.id +
              '">Edit</button>' +
              '<button type="button" class="btn btn-outline-danger vk-sg-del" data-id="' +
              it.id +
              '">Delete</button>';

        article.innerHTML =
            '<div class="position-relative vk-sg-thumb-wrap">' +
            '<label class="vk-sg-bulk-cb position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75 align-items-center gap-1" style="z-index:2">' +
            checkboxHtml +
            '</label>' +
            badgeCorner +
            (thumbUrl
                ? '<img class="vk-sg-thumb vk-sg-lazy" src="' +
                  esc(thumbUrl) +
                  '" alt="" loading="lazy" width="400" height="225">'
                : '<div class="vk-sg-skeleton w-100 h-100"></div>') +
            '</div>' +
            '<div class="card-body py-2 px-3">' +
            badgeInline +
            '<div class="fw-semibold small text-truncate" title="' +
            esc(title) +
            '">' +
            esc(title) +
            '</div>' +
            '<div class="text-muted small text-truncate">' +
            esc(svc) +
            '</div>' +
            '<div class="text-muted small">' +
            esc(when || (isSample ? 'Demo preview' : '')) +
            '</div>' +
            (fileHint ? '<div class="text-muted small text-truncate" title="' + esc(fileHint) + '">' + esc(fileHint) + '</div>' : '') +
            '</div>' +
            '<div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">' +
            '<div class="btn-group btn-group-sm w-100" role="group">' +
            actionButtons +
            '</div></div>';

        return article;
    }

    function refreshGrid() {
        state.page = 1;
        el.grid.innerHTML = '';
        state.items = [];
        return fetchList(false);
    }

    function openLightbox(index) {
        if (index < 0 || index >= state.items.length) {
            showToast('Open filters or load more to include this image in the list.', 'warning');
            return;
        }
        state.lbIndex = index;
        state.lbZoom = 1;
        applyLbZoom();
        const it = state.items[index];
        el.lbImg.src = it.image_url || '';
        el.lbImg.alt = it.title || '';
        el.lbTitle.textContent = it.title || 'Gallery';
        el.lbMeta.textContent =
            (it.service_name || '') + (it.created_at ? ' · ' + String(it.created_at).slice(0, 10) : '');
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
        state.lbIndex = (state.lbIndex + delta + n) % n;
        const it = state.items[state.lbIndex];
        el.lbImg.src = it.image_url || '';
        el.lbImg.alt = it.title || '';
        el.lbTitle.textContent = it.title || 'Gallery';
        el.lbMeta.textContent =
            (it.service_name || '') + (it.created_at ? ' · ' + String(it.created_at).slice(0, 10) : '');
        state.lbZoom = 1;
        applyLbZoom();
    }

    function openDeleteModal(id) {
        el.delTarget.value = String(id);
        if (!delModalInst) {
            delModalInst = new bootstrap.Modal(el.delModal);
        }
        delModalInst.show();
    }

    async function apiDelete(id) {
        const res = await fetch(BASE + '/api/service_gallery_delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ id: id }),
        });
        const data = await res.json().catch(function () {
            return {};
        });
        if (!res.ok || !data.ok) {
            throw new Error(data.error || 'Delete failed');
        }
    }

    function validateFile(f) {
        if (f.size > MAX_BYTES) {
            return 'Each file must be 3MB or smaller: ' + f.name;
        }
        if (ACCEPT.indexOf(f.type) === -1) {
            return 'Use JPG, PNG, or WebP only: ' + f.name;
        }
        return null;
    }

    function uploadOne(file, onProgress) {
        return new Promise(function (resolve, reject) {
            const fd = new FormData();
            fd.append('service_id', String(state.serviceId));
            fd.append('file', file);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', BASE + '/api/service_gallery_upload.php');
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
            if (xhr.upload && typeof onProgress === 'function') {
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
        if (state.serviceId <= 0) {
            showToast('Select a service before uploading.', 'warning');
            return;
        }
        const files = Array.prototype.slice.call(fileList || [], 0).slice(0, 24);
        if (!files.length) {
            return;
        }
        el.queue.hidden = false;
        el.queue.innerHTML = '';
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
            } catch (e) {
                barInner.classList.add('bg-danger');
                statusEl.textContent = e && e.message ? e.message : 'Failed';
                statusEl.classList.add('text-danger');
            }
        }
        showToast('Upload queue finished.', 'success');
        await refreshGrid();
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
                const btn = t.closest('.vk-sg-edit');
                const id = parseInt(btn.getAttribute('data-id') || '0', 10);
                const row = state.items.find(function (x) {
                    return x.id === id;
                });
                el.editId.value = String(id);
                el.editTitle.value = row ? row.title || '' : '';
                if (!editModalInst) {
                    editModalInst = new bootstrap.Modal(el.editModal);
                }
                editModalInst.show();
            } else if (t.closest('.vk-sg-del')) {
                const id = parseInt(t.closest('.vk-sg-del').getAttribute('data-id') || '0', 10);
                if (id > 0) {
                    openDeleteModal(id);
                }
            }
        });
    }

    el.apply.addEventListener('click', function () {
        state.serviceId = parseInt(el.filterService.value, 10) || 0;
        state.sort = el.sort.value === 'oldest' ? 'oldest' : 'newest';
        state.q = el.search.value.trim();
        state.dateFrom = el.dateFrom.value;
        state.dateTo = el.dateTo.value;
        state.page = 1;
        syncUrl();
        updateUploadUi();
        refreshGrid().catch(function (e) {
            showToast(e.message || 'Load failed', 'danger');
        });
    });

    el.reset.addEventListener('click', function () {
        el.filterService.value = '0';
        el.sort.value = 'newest';
        el.search.value = '';
        el.dateFrom.value = '';
        el.dateTo.value = '';
        state.serviceId = 0;
        state.sort = 'newest';
        state.q = '';
        state.dateFrom = '';
        state.dateTo = '';
        state.page = 1;
        syncUrl();
        updateUploadUi();
        refreshGrid().catch(function (e) {
            showToast(e.message || 'Load failed', 'danger');
        });
    });

    el.filterService.addEventListener('change', function () {
        state.serviceId = parseInt(el.filterService.value, 10) || 0;
        updateUploadUi();
    });

    el.loadMore.addEventListener('click', function () {
        state.page += 1;
        fetchList(true).catch(function (e) {
            showToast(e.message || 'Load failed', 'danger');
        });
    });

    el.bulkToggle.addEventListener('click', function () {
        state.bulkMode = !state.bulkMode;
        setBulkUi();
    });

    el.bulkDelete.addEventListener('click', async function () {
        const ids = selectedIds();
        if (!ids.length) {
            showToast('Select at least one image.', 'warning');
            return;
        }
        if (!window.confirm('Delete ' + ids.length + ' selected image(s)?')) {
            return;
        }
        for (let i = 0; i < ids.length; i++) {
            try {
                await apiDelete(ids[i]);
            } catch (e) {
                showToast(e.message || 'Delete failed', 'danger');
                break;
            }
        }
        showToast('Bulk delete completed.', 'success');
        await refreshGrid();
    });

    el.lbPrev.addEventListener('click', function () {
        lbStep(-1);
    });
    el.lbNext.addEventListener('click', function () {
        lbStep(1);
    });
    el.lbZoomIn.addEventListener('click', function () {
        state.lbZoom = Math.min(3, Math.round((state.lbZoom + 0.25) * 100) / 100);
        applyLbZoom();
    });
    el.lbZoomOut.addEventListener('click', function () {
        state.lbZoom = Math.max(0.5, Math.round((state.lbZoom - 0.25) * 100) / 100);
        applyLbZoom();
    });
    el.lbZoomReset.addEventListener('click', function () {
        state.lbZoom = 1;
        applyLbZoom();
    });

    el.lbImg.addEventListener('dblclick', function () {
        state.lbZoom = state.lbZoom > 1.01 ? 1 : 2;
        applyLbZoom();
    });

    el.delBtn.addEventListener('click', async function () {
        const id = parseInt(el.delTarget.value || '0', 10);
        if (id <= 0) {
            return;
        }
        try {
            await apiDelete(id);
            delModalInst.hide();
            showToast('Image deleted.', 'success');
            await refreshGrid();
        } catch (e) {
            showToast(e.message || 'Delete failed', 'danger');
        }
    });

    el.editSave.addEventListener('click', async function () {
        const id = parseInt(el.editId.value || '0', 10);
        const title = el.editTitle.value.trim();
        if (id <= 0 || !title) {
            showToast('Enter a title.', 'warning');
            return;
        }
        try {
            const res = await fetch(BASE + '/api/service_gallery_patch.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ id: id, title: title }),
            });
            const data = await res.json().catch(function () {
                return {};
            });
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Save failed');
            }
            editModalInst.hide();
            showToast('Caption updated.', 'success');
            await refreshGrid();
        } catch (e) {
            showToast(e.message || 'Save failed', 'danger');
        }
    });

    el.dropzone.addEventListener('click', function () {
        if (state.serviceId > 0) {
            el.fileInput.click();
        }
    });
    el.dropzone.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && state.serviceId > 0) {
            e.preventDefault();
            el.fileInput.click();
        }
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
        el.dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            el.dropzone.classList.add('vk-sg-dropzone--active');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        el.dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (ev === 'dragleave') {
                el.dropzone.classList.remove('vk-sg-dropzone--active');
            }
        });
    });
    el.dropzone.addEventListener('drop', function (e) {
        el.dropzone.classList.remove('vk-sg-dropzone--active');
        handleFiles(e.dataTransfer.files);
    });
    el.fileInput.addEventListener('change', function () {
        handleFiles(el.fileInput.files);
        el.fileInput.value = '';
    });

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
        }
    });

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
