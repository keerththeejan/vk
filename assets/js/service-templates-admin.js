(function () {
    'use strict';

    const app = document.getElementById('vkStApp');
    if (!app) {
        return;
    }

    const apiUrl = app.dataset.apiUrl || '';
    const csrf = app.dataset.csrf || window.VK_CSRF_TOKEN || '';
    const baseUrl = (app.dataset.baseUrl || window.VK_BASE_URL || '').replace(/\/$/, '');
    const canCreate = app.dataset.canCreate === '1';
    let perms = {};
    try {
        perms = JSON.parse(app.dataset.permissions || '{}');
    } catch (e) {
        perms = {};
    }

    const storedPerPage = localStorage.getItem('vkStPerPage') || '25';
    const storedSort = localStorage.getItem('vkStSort') || 'created_at';
    const storedSortDir = localStorage.getItem('vkStSortDir') || 'desc';

    const state = {
        page: 1,
        total: 0,
        totalPages: 1,
        perPage: storedPerPage,
        sort: storedSort,
        sortDir: storedSortDir,
        q: '',
        category: '',
        status: '',
        isDefault: '',
        dateFrom: '',
        dateTo: '',
        items: [],
        stats: null,
        bulkMode: false,
        searchTimer: null,
        deleteId: 0,
        loading: false,
    };

    const el = {
        search: document.getElementById('vkStSearch'),
        category: document.getElementById('vkStFilterCategory'),
        status: document.getElementById('vkStFilterStatus'),
        isDefault: document.getElementById('vkStFilterDefault'),
        perPage: document.getElementById('vkStPerPage'),
        dateFrom: document.getElementById('vkStDateFrom'),
        dateTo: document.getElementById('vkStDateTo'),
        apply: document.getElementById('vkStApply'),
        reset: document.getElementById('vkStReset'),
        bulkToggle: document.getElementById('vkStBulkToggle'),
        bulkBar: document.getElementById('vkStBulkBar'),
        bulkAction: document.getElementById('vkStBulkAction'),
        bulkRun: document.getElementById('vkStBulkRun'),
        body: document.getElementById('vkStBody'),
        mobileList: document.getElementById('vkStMobileList'),
        meta: document.getElementById('vkStMeta'),
        pagePrev: document.getElementById('vkStPagePrev'),
        pageNext: document.getElementById('vkStPageNext'),
        pageNums: document.getElementById('vkStPageNums'),
        exportCsv: document.getElementById('vkStExportCsv'),
        exportJson: document.getElementById('vkStExportJson'),
        viewModal: document.getElementById('vkStViewModal'),
        viewTitle: document.getElementById('vkStViewTitle'),
        viewBody: document.getElementById('vkStViewBody'),
        viewEdit: document.getElementById('vkStViewEdit'),
        previewModal: document.getElementById('vkStPreviewModal'),
        previewFrame: document.getElementById('vkStPreviewFrame'),
        deleteModal: document.getElementById('vkStDeleteModal'),
        deleteConfirm: document.getElementById('vkStDeleteConfirm'),
        deleteId: document.getElementById('vkStDeleteId'),
        selectAll: document.getElementById('vkStSelectAll'),
        statTotal: document.getElementById('vkStStatTotal'),
        statActive: document.getElementById('vkStStatActive'),
        statInactive: document.getElementById('vkStStatInactive'),
        statCategories: document.getElementById('vkStStatCategories'),
        statValue: document.getElementById('vkStStatValue'),
        statMostUsed: document.getElementById('vkStStatMostUsed'),
        statTotalTrend: document.getElementById('vkStStatTotalTrend'),
        statActiveTrend: document.getElementById('vkStStatActiveTrend'),
        statInactiveTrend: document.getElementById('vkStStatInactiveTrend'),
        statCategoriesTrend: document.getElementById('vkStStatCategoriesTrend'),
        statValueTrend: document.getElementById('vkStStatValueTrend'),
        statMostUsedTrend: document.getElementById('vkStStatMostUsedTrend'),
    };

    let viewModalInst = null;
    let previewModalInst = null;
    let deleteModalInst = null;

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type || 'success');
        }
    }

    function formatRs(amount) {
        const n = Number(amount) || 0;
        return 'Rs. ' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDateShort(iso) {
        if (!iso) {
            return '—';
        }
        const raw = iso.replace(' ', 'T');
        const d = new Date(raw.length > 10 ? raw : raw + 'T00:00:00');
        if (Number.isNaN(d.getTime())) {
            return iso.slice(0, 10);
        }
        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function formatTimeShort(iso) {
        if (!iso || iso.length < 11) {
            return '';
        }
        const d = new Date(iso.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) {
            return '';
        }
        return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    function hasActiveFilters() {
        return !!(state.q || state.category || state.status || state.isDefault || state.dateFrom || state.dateTo);
    }

    function catBadge(category) {
        const c = (category || 'general').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        const cls = 'vk-st-cat vk-st-cat-' + (c || 'general');
        return '<span class="' + cls + '">' + esc(category || 'general') + '</span>';
    }

    function statusBadge(status) {
        const s = (status || 'active').toLowerCase();
        const map = { active: 'active', inactive: 'inactive', draft: 'draft', archived: 'archived' };
        const cls = 'vk-st-pill vk-st-pill-' + (map[s] || 'inactive');
        return '<span class="' + cls + '">' + esc(s) + '</span>';
    }

    function renderThumb(it) {
        if (it.thumb_url) {
            return (
                '<img src="' +
                esc(it.thumb_url) +
                '" alt="" class="vk-st-thumb" width="32" height="32" loading="lazy" decoding="async">'
            );
        }
        return '<span class="vk-st-thumb-fallback" aria-hidden="true"><i class="bi bi-image"></i></span>';
    }

    function buildActions(it) {
        const id = it.id;
        const cls = 'vk-st-act btn-action';
        const btns = [];

        btns.push(
            '<button type="button" class="' +
                cls +
                ' vk-st-view" data-id="' +
                id +
                '" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></button>'
        );
        btns.push(
            '<button type="button" class="' +
                cls +
                ' vk-st-preview" data-id="' +
                id +
                '" data-bs-toggle="tooltip" title="Preview"><i class="bi bi-display"></i></button>'
        );
        if (perms.can_edit) {
            btns.push(
                '<a class="' +
                    cls +
                    '" href="' +
                    esc(baseUrl + '/modules/service_templates/edit.php?id=' + id) +
                    '" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>'
            );
        }
        if (perms.can_create) {
            btns.push(
                '<a class="' +
                    cls +
                    ' vk-st-dup" href="' +
                    esc(baseUrl + '/modules/service_templates/duplicate.php?id=' + id) +
                    '" data-bs-toggle="tooltip" title="Duplicate"><i class="bi bi-copy"></i></a>'
            );
        }
        btns.push(
            '<button type="button" class="' +
                cls +
                ' vk-st-history" data-id="' +
                id +
                '" data-bs-toggle="tooltip" title="History"><i class="bi bi-clock-history"></i></button>'
        );
        if (perms.can_delete) {
            btns.push(
                '<button type="button" class="' +
                    cls +
                    ' vk-st-act-danger vk-st-del" data-id="' +
                    id +
                    '" data-usage="' +
                    it.usage_count +
                    '" data-bs-toggle="tooltip" title="Delete"><i class="bi bi-trash"></i></button>'
            );
        }
        return btns.join('');
    }

    function renderStats(stats) {
        if (!stats) {
            return;
        }
        state.stats = stats;
        if (el.statTotal) {
            el.statTotal.textContent = String(stats.total ?? '—');
        }
        if (el.statActive) {
            el.statActive.textContent = String(stats.active ?? '—');
        }
        if (el.statInactive) {
            el.statInactive.textContent = String(stats.inactive ?? '—');
        }
        if (el.statCategories) {
            el.statCategories.textContent = String(stats.categories ?? '—');
        }
        if (el.statValue) {
            el.statValue.textContent = formatRs(stats.total_value ?? 0);
        }
        if (el.statMostUsed) {
            const name = stats.most_used_name || '';
            const count = stats.most_used_count || 0;
            if (name) {
                el.statMostUsed.textContent = name;
                el.statMostUsed.title = name + ' (' + count + ' uses)';
            } else {
                el.statMostUsed.textContent = '—';
                el.statMostUsed.title = '';
            }
        }
        const activePct = stats.total > 0 ? Math.round((stats.active / stats.total) * 100) : 0;
        if (el.statTotalTrend) {
            el.statTotalTrend.textContent = hasActiveFilters() ? 'filtered view' : 'in catalog';
            el.statTotalTrend.className = 'vk-st-kpi-trend';
        }
        if (el.statActiveTrend) {
            el.statActiveTrend.textContent = activePct + '% of total';
            el.statActiveTrend.className = 'vk-st-kpi-trend is-up';
        }
        if (el.statInactiveTrend) {
            el.statInactiveTrend.textContent = stats.inactive ? stats.inactive + ' paused' : 'none';
            el.statInactiveTrend.className = 'vk-st-kpi-trend';
        }
        if (el.statCategoriesTrend) {
            el.statCategoriesTrend.textContent = stats.categories + ' types';
            el.statCategoriesTrend.className = 'vk-st-kpi-trend';
        }
        if (el.statValueTrend) {
            el.statValueTrend.textContent = 'combined amount';
            el.statValueTrend.className = 'vk-st-kpi-trend';
        }
        if (el.statMostUsedTrend && stats.most_used_count) {
            el.statMostUsedTrend.textContent = '↑ ' + stats.most_used_count + ' uses';
            el.statMostUsedTrend.className = 'vk-st-kpi-trend is-up';
        } else if (el.statMostUsedTrend) {
            el.statMostUsedTrend.textContent = '';
        }
    }

    function initTooltips(root) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }
        (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (node) {
            const existing = bootstrap.Tooltip.getInstance(node);
            if (existing) {
                existing.dispose();
            }
            new bootstrap.Tooltip(node, { container: 'body', boundary: 'window' });
        });
    }

    function skeletonRows() {
        const colSpan = perms.can_bulk ? 11 : 10;
        let html = '';
        for (let i = 0; i < 8; i++) {
            html +=
                '<tr class="vk-st-skeleton-row" aria-hidden="true">' +
                '<td colspan="' +
                colSpan +
                '"><div class="d-flex align-items-center gap-2">' +
                '<div class="vk-st-skel vk-st-skel-thumb"></div>' +
                '<div class="flex-grow-1"><div class="vk-st-skel vk-st-skel-wide"></div><div class="vk-st-skel vk-st-skel-sm"></div></div>' +
                '</div></td></tr>';
        }
        return html;
    }

    function skeletonCards() {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html +=
                '<div class="vk-st-mcard" aria-hidden="true">' +
                '<div class="vk-st-skel vk-st-skel-thumb"></div>' +
                '<div class="flex-grow-1"><div class="vk-st-skel vk-st-skel-wide mb-1"></div><div class="vk-st-skel vk-st-skel-md"></div></div>' +
                '</div>';
        }
        return html;
    }

    function emptyStateHtml(filtered) {
        if (filtered) {
            return (
                '<div class="vk-st-empty-wrap" role="status">' +
                '<div class="vk-st-empty-icon"><i class="bi bi-search"></i></div>' +
                '<div class="vk-st-empty-title">No templates found</div>' +
                '<p class="vk-st-empty-text">Try adjusting your search or filters.</p>' +
                '<button type="button" class="vk-st-btn vk-st-btn-ghost" id="vkStEmptyReset">Clear filters</button>' +
                '</div>'
            );
        }
        let cta = '';
        if (canCreate) {
            cta =
                '<a class="vk-st-btn vk-st-btn-primary" href="' +
                esc(baseUrl + '/modules/service_templates/add.php') +
                '"><i class="bi bi-plus-lg"></i> Create your first template</a>';
        }
        return (
            '<div class="vk-st-empty-wrap" role="status">' +
            '<div class="vk-st-empty-icon"><i class="bi bi-layers"></i></div>' +
            '<div class="vk-st-empty-title">No service templates yet</div>' +
            '<p class="vk-st-empty-text">Create reusable pricing templates for your repair jobs.</p>' +
            cta +
            '</div>'
        );
    }

    function updateSortHeaders() {
        document.querySelectorAll('.vk-st-sortable').forEach(function (th) {
            th.classList.remove('is-sorted-asc', 'is-sorted-desc');
            if (th.dataset.sort === state.sort) {
                th.classList.add(state.sortDir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            }
        });
    }

    function renderDateCell(iso, compact) {
        const d = formatDateShort(iso);
        if (compact) {
            const t = formatTimeShort(iso);
            return d + (t ? ', ' + t : '');
        }
        return '<span class="vk-st-date" title="' + esc(iso || '') + '">' + esc(d) + '</span>';
    }

    function renderTableRow(it) {
        const defaultBadge = it.is_default ? '<span class="vk-st-badge-default">Default</span>' : '';
        const bulkCb = perms.can_bulk
            ? '<td class="vk-st-sticky-col vk-st-th-check"><input type="checkbox" class="form-check-input vk-st-row-cb" value="' +
              it.id +
              '" aria-label="Select ' +
              esc(it.name) +
              '"></td>'
            : '';
        const nameTitle = esc(it.name + (it.description ? ' — ' + it.description : ''));

        return (
            '<tr data-id="' +
            it.id +
            '">' +
            bulkCb +
            '<td class="vk-st-sticky-col vk-st-th-thumb vk-st-td-thumb">' +
            renderThumb(it) +
            '</td>' +
            '<td class="vk-st-sticky-col vk-st-sticky-name vk-st-td-name">' +
            '<div class="vk-st-name-line" title="' +
            nameTitle +
            '">' +
            '<span class="vk-st-name">' +
            esc(it.name) +
            defaultBadge +
            '</span>' +
            '</div></td>' +
            '<td><span class="vk-st-code" title="' +
            esc(it.template_code || '') +
            '">' +
            esc(it.template_code || '—') +
            '</span></td>' +
            '<td class="d-none d-md-table-cell">' +
            catBadge(it.category) +
            '</td>' +
            '<td class="text-end"><span class="vk-st-amt">' +
            esc(formatRs(it.default_amount)) +
            '</span></td>' +
            '<td>' +
            statusBadge(it.status) +
            '</td>' +
            '<td class="text-center d-none d-lg-table-cell"><span class="vk-st-ver">v' +
            esc(it.version) +
            '</span></td>' +
            '<td class="text-center d-none d-lg-table-cell"><span class="vk-st-usage">' +
            esc(it.usage_count) +
            '</span></td>' +
            '<td class="d-none d-xl-table-cell vk-st-td-date">' +
            renderDateCell(it.created_at, false) +
            '</td>' +
            '<td class="text-end vk-st-td-act"><div class="vk-st-actions">' +
            buildActions(it) +
            '</div></td></tr>'
        );
    }

    function renderMobileCard(it) {
        const defaultBadge = it.is_default ? '<span class="vk-st-badge-default">Default</span>' : '';
        return (
            '<article class="vk-st-mcard" data-id="' +
            it.id +
            '">' +
            renderThumb(it) +
            '<div class="vk-st-mcard-body">' +
            '<div class="vk-st-mcard-top">' +
            '<div class="vk-st-mcard-name">' +
            esc(it.name) +
            defaultBadge +
            '</div>' +
            statusBadge(it.status) +
            '</div>' +
            '<div class="vk-st-mcard-meta">' +
            catBadge(it.category) +
            '<span class="vk-st-amt">' +
            esc(formatRs(it.default_amount)) +
            '</span>' +
            '<span class="vk-st-date">' +
            esc(renderDateCell(it.created_at, true)) +
            '</span>' +
            '</div>' +
            '<div class="vk-st-mcard-actions">' +
            buildActions(it) +
            '</div></div></article>'
        );
    }

    function renderList() {
        const filtered = hasActiveFilters();
        const colSpan = perms.can_bulk ? 11 : 10;

        if (!state.items.length) {
            const catalogEmpty = !filtered && ((state.stats && state.stats.total === 0) || state.total === 0);
            const empty = emptyStateHtml(!catalogEmpty);
            if (el.body) {
                el.body.innerHTML = '<tr><td colspan="' + colSpan + '">' + empty + '</td></tr>';
            }
            if (el.mobileList) {
                el.mobileList.innerHTML = empty;
            }
            const emptyReset = document.getElementById('vkStEmptyReset');
            if (emptyReset) {
                emptyReset.addEventListener('click', function () {
                    if (el.reset) {
                        el.reset.click();
                    }
                });
            }
            return;
        }

        if (el.body) {
            el.body.innerHTML = state.items.map(renderTableRow).join('');
        }
        if (el.mobileList) {
            el.mobileList.innerHTML = state.items.map(renderMobileCard).join('');
        }
        if (el.selectAll) {
            el.selectAll.checked = false;
            el.selectAll.indeterminate = false;
        }
        initTooltips(el.body);
        initTooltips(el.mobileList);
        updateSortHeaders();
    }

    function showLoading() {
        if (el.body && !state.items.length) {
            el.body.innerHTML = skeletonRows();
        }
        if (el.mobileList && !state.items.length) {
            el.mobileList.innerHTML = skeletonCards();
        }
    }

    function updateMeta() {
        if (!el.meta) {
            return;
        }
        if (state.total <= 0) {
            el.meta.textContent = 'Showing 0';
            return;
        }
        const per = state.perPage === 'all' ? state.total : parseInt(state.perPage, 10) || 25;
        const from = (state.page - 1) * per + 1;
        const to = Math.min(state.page * per, state.total);
        el.meta.textContent = 'Showing ' + from + '–' + to + ' of ' + state.total;
    }

    function renderPagination() {
        if (el.pagePrev) {
            el.pagePrev.disabled = state.page <= 1;
        }
        if (el.pageNext) {
            el.pageNext.disabled = state.page >= state.totalPages;
        }
        if (!el.pageNums) {
            return;
        }
        const pages = state.totalPages;
        if (pages <= 1) {
            el.pageNums.innerHTML = '';
            return;
        }
        let start = Math.max(1, state.page - 2);
        let end = Math.min(pages, start + 4);
        start = Math.max(1, end - 4);
        let html = '';
        for (let p = start; p <= end; p++) {
            html +=
                '<button type="button" class="vk-st-page-num' +
                (p === state.page ? ' is-active' : '') +
                '" data-page="' +
                p +
                '" aria-label="Page ' +
                p +
                '"' +
                (p === state.page ? ' aria-current="page"' : '') +
                '>' +
                p +
                '</button>';
        }
        el.pageNums.innerHTML = html;
    }

    function listParams() {
        return {
            page: String(state.page),
            per_page: state.perPage,
            q: state.q,
            category: state.category,
            status: state.status,
            is_default: state.isDefault,
            date_from: state.dateFrom,
            date_to: state.dateTo,
            sort: state.sort,
            sort_dir: state.sortDir,
        };
    }

    function applyFiltersFromUi() {
        state.q = el.search ? el.search.value.trim() : '';
        state.category = el.category ? el.category.value : '';
        state.status = el.status ? el.status.value : '';
        state.isDefault = el.isDefault ? el.isDefault.value : '';
        state.dateFrom = el.dateFrom ? el.dateFrom.value : '';
        state.dateTo = el.dateTo ? el.dateTo.value : '';
        state.perPage = el.perPage ? el.perPage.value : '25';
        localStorage.setItem('vkStPerPage', state.perPage);
        state.page = 1;
    }

    async function apiPost(action, payload) {
        const res = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: JSON.stringify(Object.assign({ action, csrf_token: csrf }, payload || {})),
        });
        const data = await res.json().catch(function () {
            return { ok: false };
        });
        if (!res.ok || data.ok === false) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    async function fetchList(options) {
        const opts = options || {};
        state.loading = true;
        if (!opts.silent) {
            showLoading();
        }
        if (opts.applyLoading && el.apply) {
            el.apply.classList.add('is-loading');
            el.apply.disabled = true;
        }
        try {
            const params = new URLSearchParams(Object.assign({ action: 'list' }, listParams()));
            const res = await fetch(apiUrl + '?' + params.toString(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await res.json().catch(function () {
                return { ok: false };
            });
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Could not load templates');
            }
            state.items = data.items || [];
            state.total = data.total || 0;
            state.totalPages = data.total_pages || 1;
            renderStats(data.stats);
            renderList();
            updateMeta();
            renderPagination();
        } finally {
            state.loading = false;
            if (opts.applyLoading && el.apply) {
                el.apply.classList.remove('is-loading');
                el.apply.disabled = false;
            }
        }
    }

    function selectedIds() {
        return Array.prototype.map.call(document.querySelectorAll('.vk-st-row-cb:checked'), function (c) {
            return parseInt(c.value, 10);
        });
    }

    function setBulkUi() {
        document.body.classList.toggle('vk-st-bulk-on', state.bulkMode);
        if (el.bulkBar) {
            el.bulkBar.classList.toggle('d-none', !state.bulkMode);
        }
    }

    async function openView(id, focusHistory) {
        const params = new URLSearchParams({ action: 'get', id: String(id) });
        const res = await fetch(apiUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!data.ok || !data.item) {
            toast(data.error || 'Could not load template', 'danger');
            return;
        }
        const it = data.item;
        el.viewTitle.textContent = focusHistory ? 'Version history — ' + (it.name || 'Template') : it.name || 'Template';
        const historyBlock =
            it.versions && it.versions.length
                ? '<hr id="vkStVersionHistory"><h3 class="h6 mb-2">Version history</h3><ul class="list-unstyled small mb-0">' +
                  it.versions
                      .map(function (v) {
                          return (
                              '<li class="mb-1">v' +
                              esc(v.version) +
                              ' · ' +
                              esc(v.change_log || '') +
                              ' · ' +
                              esc(v.created_at || '') +
                              ' · ' +
                              esc(v.author || '') +
                              '</li>'
                          );
                      })
                      .join('') +
                  '</ul>'
                : focusHistory
                  ? '<hr id="vkStVersionHistory"><p class="text-muted small mb-0">No version snapshots recorded yet.</p>'
                  : '';
        el.viewBody.innerHTML =
            '<dl class="row mb-0">' +
            '<dt class="col-sm-3">Code</dt><dd class="col-sm-9"><code>' +
            esc(it.template_code) +
            '</code></dd>' +
            '<dt class="col-sm-3">Category</dt><dd class="col-sm-9">' +
            esc(it.category) +
            ' / ' +
            esc(it.service_type) +
            '</dd>' +
            '<dt class="col-sm-3">Amount</dt><dd class="col-sm-9">' +
            esc(formatRs(it.default_amount)) +
            '</dd>' +
            '<dt class="col-sm-3">Status</dt><dd class="col-sm-9">' +
            statusBadge(it.status) +
            '</dd>' +
            '<dt class="col-sm-3">Version</dt><dd class="col-sm-9">v' +
            esc(it.version) +
            '</dd>' +
            '<dt class="col-sm-3">Usage</dt><dd class="col-sm-9">' +
            esc(it.usage_count) +
            ' repair job(s)</dd>' +
            '<dt class="col-sm-3">Description</dt><dd class="col-sm-9">' +
            esc(it.description || '—') +
            '</dd>' +
            '<dt class="col-sm-3">Created</dt><dd class="col-sm-9">' +
            esc(it.created_at) +
            ' · ' +
            esc(it.creator_name || '') +
            '</dd>' +
            '<dt class="col-sm-3">Updated</dt><dd class="col-sm-9">' +
            esc(it.updated_at || '—') +
            '</dd></dl>' +
            historyBlock;
        if (el.viewEdit) {
            if (perms.can_edit) {
                el.viewEdit.href = baseUrl + '/modules/service_templates/edit.php?id=' + id;
                el.viewEdit.classList.remove('d-none');
            } else {
                el.viewEdit.classList.add('d-none');
            }
        }
        if (!viewModalInst) {
            viewModalInst = new bootstrap.Modal(el.viewModal);
        }
        viewModalInst.show();
        if (focusHistory) {
            const anchor = document.getElementById('vkStVersionHistory');
            if (anchor) {
                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    function openPreview(id) {
        if (el.previewFrame) {
            el.previewFrame.src = baseUrl + '/service-template-detail.php?id=' + id;
        }
        if (!previewModalInst) {
            previewModalInst = new bootstrap.Modal(el.previewModal);
        }
        previewModalInst.show();
    }

    function handleActionClick(ev) {
        const dup = ev.target.closest('.vk-st-dup');
        if (dup && dup.tagName === 'A') {
            if (!window.confirm('Duplicate this template?')) {
                ev.preventDefault();
            }
            return;
        }
        const t = ev.target.closest('button, a');
        if (!t) {
            return;
        }
        if (t.classList.contains('vk-st-view')) {
            openView(parseInt(t.dataset.id, 10), false);
        } else if (t.classList.contains('vk-st-history')) {
            openView(parseInt(t.dataset.id, 10), true);
        } else if (t.classList.contains('vk-st-preview')) {
            openPreview(parseInt(t.dataset.id, 10));
        } else if (t.classList.contains('vk-st-del')) {
            state.deleteId = parseInt(t.dataset.id, 10);
            const usage = parseInt(t.dataset.usage, 10) || 0;
            const msg = document.getElementById('vkStDeleteMsg');
            if (msg) {
                msg.textContent =
                    usage > 0
                        ? 'This template is used by ' + usage + ' repair job(s) and cannot be deleted. Archive it instead.'
                        : 'This will soft-delete the template. Continue?';
            }
            if (el.deleteConfirm) {
                el.deleteConfirm.disabled = usage > 0;
            }
            if (el.deleteId) {
                el.deleteId.value = String(state.deleteId);
            }
            if (!deleteModalInst) {
                deleteModalInst = new bootstrap.Modal(el.deleteModal);
            }
            deleteModalInst.show();
        }
    }

    document.querySelectorAll('[data-preview]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-preview]').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            const mode = btn.dataset.preview || 'desktop';
            if (el.previewFrame) {
                el.previewFrame.className = 'vk-st-preview vk-st-preview-' + mode;
            }
        });
    });

    document.querySelectorAll('.vk-st-sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            const col = th.dataset.sort;
            if (!col) {
                return;
            }
            if (state.sort === col) {
                state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort = col;
                state.sortDir = 'asc';
            }
            localStorage.setItem('vkStSort', state.sort);
            localStorage.setItem('vkStSortDir', state.sortDir);
            fetchList().catch(function (e) {
                toast(e.message, 'danger');
            });
        });
    });

    if (el.selectAll) {
        el.selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-st-row-cb').forEach(function (cb) {
                cb.checked = el.selectAll.checked;
            });
        });
    }

    if (el.body) {
        el.body.addEventListener('change', function (ev) {
            if (!ev.target.classList.contains('vk-st-row-cb') || !el.selectAll) {
                return;
            }
            const boxes = document.querySelectorAll('.vk-st-row-cb');
            const checked = document.querySelectorAll('.vk-st-row-cb:checked');
            el.selectAll.checked = boxes.length > 0 && checked.length === boxes.length;
            el.selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        });
        el.body.addEventListener('click', handleActionClick);
    }

    if (el.mobileList) {
        el.mobileList.addEventListener('click', handleActionClick);
    }

    if (el.pageNums) {
        el.pageNums.addEventListener('click', function (ev) {
            const btn = ev.target.closest('[data-page]');
            if (!btn) {
                return;
            }
            const p = parseInt(btn.dataset.page, 10);
            if (p && p !== state.page) {
                state.page = p;
                fetchList().catch(function (e) {
                    toast(e.message, 'danger');
                });
            }
        });
    }

    if (el.deleteConfirm) {
        el.deleteConfirm.addEventListener('click', async function () {
            const id = parseInt(el.deleteId.value || '0', 10);
            if (!id) {
                return;
            }
            try {
                await apiPost('delete', { id: id });
                deleteModalInst.hide();
                toast('Template deleted.', 'success');
                fetchList();
            } catch (e) {
                toast(e.message, 'danger');
            }
        });
    }

    if (el.apply) {
        el.apply.addEventListener('click', function () {
            applyFiltersFromUi();
            fetchList({ applyLoading: true }).catch(function (e) {
                toast(e.message, 'danger');
            });
        });
    }

    if (el.reset) {
        el.reset.addEventListener('click', function () {
            if (el.search) {
                el.search.value = '';
            }
            if (el.category) {
                el.category.value = '';
            }
            if (el.status) {
                el.status.value = '';
            }
            if (el.isDefault) {
                el.isDefault.value = '';
            }
            if (el.dateFrom) {
                el.dateFrom.value = '';
            }
            if (el.dateTo) {
                el.dateTo.value = '';
            }
            applyFiltersFromUi();
            fetchList({ applyLoading: true }).catch(function (e) {
                toast(e.message, 'danger');
            });
        });
    }

    if (el.search) {
        el.search.addEventListener('input', function () {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(function () {
                state.q = el.search.value.trim();
                state.page = 1;
                fetchList().catch(function () {});
            }, 350);
        });
    }

    if (el.pagePrev) {
        el.pagePrev.addEventListener('click', function () {
            if (state.page > 1) {
                state.page--;
                fetchList().catch(function (e) {
                    toast(e.message, 'danger');
                });
            }
        });
    }

    if (el.pageNext) {
        el.pageNext.addEventListener('click', function () {
            if (state.page < state.totalPages) {
                state.page++;
                fetchList().catch(function (e) {
                    toast(e.message, 'danger');
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

    if (el.bulkRun) {
        el.bulkRun.addEventListener('click', async function () {
            const ids = selectedIds();
            const action = el.bulkAction ? el.bulkAction.value : '';
            if (!ids.length || !action) {
                toast('Select templates and an action.', 'warning');
                return;
            }
            if (action === 'delete' && !window.confirm('Delete ' + ids.length + ' template(s)?')) {
                return;
            }
            try {
                await apiPost('bulk', { bulk_action: action, ids: ids });
                toast('Bulk action completed.', 'success');
                fetchList();
            } catch (e) {
                toast(e.message, 'danger');
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
            a.download = 'service-templates-' + new Date().toISOString().slice(0, 10) + '.json';
            a.click();
            URL.revokeObjectURL(a.href);
        });
    }

    if (el.perPage) {
        el.perPage.value = storedPerPage;
    }
    updateSortHeaders();
    setBulkUi();
    fetchList().catch(function (e) {
        toast(e.message || 'Load failed', 'danger');
    });
})();
