(function () {
    'use strict';

    const app = document.getElementById('vkSeoApp');
    if (!app) {
        return;
    }

    const searchInput = document.getElementById('vkSeoSearch');
    const filterScore = document.getElementById('vkSeoFilterScore');
    const filterIndex = document.getElementById('vkSeoFilterIndex');
    const filterPriority = document.getElementById('vkSeoFilterPriority');
    const filterStatus = document.getElementById('vkSeoFilterStatus');
    const resetBtn = document.getElementById('vkSeoReset');
    const refreshBtn = document.getElementById('vkSeoRefresh');
    const panel = document.getElementById('vkSeoPagePanel');
    const selectAll = document.getElementById('vkSeoSelectAll');
    const visibleCountEl = document.getElementById('vkSeoVisibleCount');
    const totalCount = parseInt(app.dataset.totalPages || '0', 10);

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

    function pageRows() {
        return document.querySelectorAll('#vkSeoTable tbody tr[data-page-id], .vk-seo-mobile-card[data-page-id]');
    }

    function applyFilters() {
        const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const scoreBand = filterScore ? filterScore.value : '';
        const idx = filterIndex ? filterIndex.value : '';
        const pri = filterPriority ? filterPriority.value : '';
        const st = filterStatus ? filterStatus.value : '';
        const re = q.length >= 2 ? new RegExp('(' + escapeRegExp(q) + ')', 'gi') : null;
        let visible = 0;

        pageRows().forEach(function (row) {
            const hay = (row.dataset.search || '').toLowerCase();
            const matchQ = !q || hay.indexOf(q) !== -1;
            const matchScore = !scoreBand || row.dataset.scoreBand === scoreBand;
            const matchIdx = !idx || row.dataset.index === idx;
            const matchPri = !pri || row.dataset.priority === pri;
            const matchSt = !st || row.dataset.status === st;
            const show = matchQ && matchScore && matchIdx && matchPri && matchSt;
            row.classList.toggle('is-hidden', !show);
            if (show) {
                visible++;
            }

            if (re && show) {
                row.querySelectorAll('.vk-seo-highlight-target').forEach(function (el) {
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

        const empty = document.getElementById('vkSeoTableEmpty');
        if (empty) {
            empty.hidden = visible > 0;
        }
    }

    function resetFilters() {
        if (searchInput) {
            searchInput.value = '';
        }
        [filterScore, filterIndex, filterPriority, filterStatus].forEach(function (el) {
            if (el) {
                el.value = '';
            }
        });
        applyFilters();
    }

    function animateBars() {
        document.querySelectorAll('.vk-seo-bar-fill[data-width]').forEach(function (bar) {
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
            document.querySelectorAll('#vkSeoTable .vk-seo-row-check').forEach(function (cb) {
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
    [filterScore, filterIndex, filterPriority, filterStatus].forEach(function (el) {
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
        app.classList.remove('vk-seo-skeleton');
    }, 400);
})();
