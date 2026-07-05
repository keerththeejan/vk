(function () {
    'use strict';

    const app = document.getElementById('vkMktApp');
    if (!app) {
        return;
    }

    const searchInput = document.getElementById('vkMktSearch');
    const filterStatus = document.getElementById('vkMktFilterStatus');
    const filterChannel = document.getElementById('vkMktFilterChannel');
    const filterType = document.getElementById('vkMktFilterType');
    const resetBtn = document.getElementById('vkMktReset');
    const refreshBtn = document.getElementById('vkMktRefresh');
    const panel = document.getElementById('vkMktCampaignPanel');
    const selectAll = document.getElementById('vkMktSelectAll');
    const visibleCountEl = document.getElementById('vkMktVisibleCount');
    const totalCount = parseInt(app.dataset.totalCampaigns || '0', 10);

    function debounce(fn, ms) {
        let t;
        return function () {
            const args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(null, args);
            }, ms);
        };
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function initTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function campaignRows() {
        return document.querySelectorAll('#vkMktTable tbody tr[data-campaign-id], .vk-mkt-mobile-card[data-campaign-id]');
    }

    function applyFilters() {
        const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const st = filterStatus ? filterStatus.value : '';
        const ch = filterChannel ? filterChannel.value : '';
        const tp = filterType ? filterType.value : '';
        const re = q.length >= 2 ? new RegExp('(' + escapeRegExp(q) + ')', 'gi') : null;
        let visible = 0;

        campaignRows().forEach(function (row) {
            const hay = (row.dataset.search || '').toLowerCase();
            const matchQ = !q || hay.indexOf(q) !== -1;
            const matchSt = !st || row.dataset.status === st;
            const matchCh = !ch || row.dataset.channel === ch;
            const matchTp = !tp || row.dataset.type === tp;
            const show = matchQ && matchSt && matchCh && matchTp;
            row.classList.toggle('is-hidden', !show);
            if (show) {
                visible++;
            }

            if (re && show) {
                row.querySelectorAll('.vk-mkt-highlight-target').forEach(function (el) {
                    const raw = el.dataset.raw || el.textContent;
                    el.dataset.raw = raw;
                    if (re.test(raw)) {
                        el.innerHTML = raw.replace(re, '<mark>$1</mark>');
                    } else {
                        el.textContent = raw;
                    }
                });
            }
        });

        if (visibleCountEl) {
            const from = visible > 0 ? 1 : 0;
            visibleCountEl.textContent = 'Showing ' + from + '–' + visible + ' of ' + totalCount;
        }

        const empty = document.getElementById('vkMktTableEmpty');
        if (empty) {
            empty.hidden = visible > 0;
        }
    }

    function resetFilters() {
        if (searchInput) {
            searchInput.value = '';
        }
        if (filterStatus) {
            filterStatus.value = '';
        }
        if (filterChannel) {
            filterChannel.value = '';
        }
        if (filterType) {
            filterType.value = '';
        }
        applyFilters();
    }

    function animateBars() {
        document.querySelectorAll('.vk-mkt-bar-fill[data-width]').forEach(function (bar) {
            const w = bar.dataset.width || '0';
            requestAnimationFrame(function () {
                bar.style.width = w + '%';
            });
        });
    }

    function animateCounters() {
        document.querySelectorAll('[data-count-to]').forEach(function (el) {
            const target = parseFloat(el.dataset.countTo || '0');
            const suffix = el.dataset.countSuffix || '';
            const prefix = el.dataset.countPrefix || '';
            const isFloat = (el.dataset.countFloat || '') === '1';
            const duration = 700;
            const start = performance.now();
            function tick(now) {
                const p = Math.min(1, (now - start) / duration);
                const val = target * p;
                el.textContent = prefix + (isFloat ? val.toFixed(1) : Math.round(val).toLocaleString('en-IN')) + suffix;
                if (p < 1) {
                    requestAnimationFrame(tick);
                }
            }
            requestAnimationFrame(tick);
        });
    }

    function initSelectAll() {
        if (!selectAll) {
            return;
        }
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('#vkMktTable .vk-mkt-row-check').forEach(function (cb) {
                const tr = cb.closest('tr');
                if (tr && !tr.classList.contains('is-hidden')) {
                    cb.checked = selectAll.checked;
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', debounce(applyFilters, 300));
    }
    [filterStatus, filterChannel, filterType].forEach(function (el) {
        if (el) {
            el.addEventListener('change', applyFilters);
        }
    });
    if (resetBtn) {
        resetBtn.addEventListener('click', resetFilters);
    }
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            if (panel) {
                panel.classList.add('is-loading');
            }
            window.location.reload();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement !== searchInput) {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
            }
        }
    });

    initSelectAll();
    initTooltips();
    applyFilters();
    animateBars();
    animateCounters();

    window.setTimeout(function () {
        app.classList.remove('vk-mkt-skeleton');
    }, 400);
})();
