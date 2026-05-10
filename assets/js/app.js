(function () {
    'use strict';

    const BASE = window.VK_BASE_URL || '';

    function showLoader(show) {
        const el = document.getElementById('pageLoader');
        if (!el) return;
        el.classList.toggle('d-none', !show);
    }

    window.showLoader = showLoader;

    window.showToast = function (message, type) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const t = document.createElement('div');
        t.className = 'toast align-items-center text-bg-' + (type || 'info') + ' border-0';
        t.setAttribute('role', 'alert');
        t.innerHTML =
            '<div class="d-flex">' +
            '<div class="toast-body">' +
            escapeHtml(String(message)) +
            '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
            '</div>';
        container.appendChild(t);
        const toast = new bootstrap.Toast(t, { delay: 4500 });
        toast.show();
        t.addEventListener('hidden.bs.toast', function () {
            t.remove();
        });
    };

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /* Theme */
    const themeKey = 'vk_billing_theme';
    function applyTheme(mode) {
        const html = document.documentElement;
        if (mode === 'dark') {
            html.setAttribute('data-bs-theme', 'dark');
        } else {
            html.setAttribute('data-bs-theme', 'light');
        }
        const darkIcon = document.getElementById('themeIconDark');
        const lightIcon = document.getElementById('themeIconLight');
        if (darkIcon && lightIcon) {
            darkIcon.classList.toggle('d-none', mode === 'dark');
            lightIcon.classList.toggle('d-none', mode !== 'dark');
        }
        localStorage.setItem(themeKey, mode);
    }

    const saved = localStorage.getItem(themeKey);
    if (saved === 'dark' || saved === 'light') {
        applyTheme(saved);
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        applyTheme('dark');
    }

    document.getElementById('themeToggle')?.addEventListener('click', function () {
        const cur = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
        applyTheme(cur === 'dark' ? 'light' : 'dark');
    });

    /* Table sort (client-side) */
    document.querySelectorAll('table.sortable').forEach(function (table) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        table.querySelectorAll('th[data-sort]').forEach(function (th) {
            th.addEventListener('click', function () {
                const col = parseInt(th.getAttribute('data-sort'), 10);
                const type = th.getAttribute('data-type') || 'string';
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const asc = th.classList.toggle('sort-asc');
                th.classList.toggle('sort-desc', !asc);
                table.querySelectorAll('th[data-sort]').forEach(function (h) {
                    if (h !== th) h.classList.remove('sort-asc', 'sort-desc');
                });
                rows.sort(function (a, b) {
                    const ta = a.children[col] ? a.children[col].textContent.trim() : '';
                    const tb = b.children[col] ? b.children[col].textContent.trim() : '';
                    let va = ta;
                    let vb = tb;
                    if (type === 'number') {
                        va = parseFloat(ta.replace(/[^0-9.-]/g, '')) || 0;
                        vb = parseFloat(tb.replace(/[^0-9.-]/g, '')) || 0;
                    }
                    if (va < vb) return asc ? -1 : 1;
                    if (va > vb) return asc ? 1 : -1;
                    return 0;
                });
                rows.forEach(function (r) {
                    tbody.appendChild(r);
                });
            });
        });
    });

    /* Form submit loading */
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            showLoader(true);
        });
    });

    document.querySelectorAll('[data-staff-form]').forEach(function (form) {
        const fileInput = form.querySelector('[data-staff-file]');
        const dropzone = form.querySelector('[data-staff-dropzone]');
        const preview = form.querySelector('[data-staff-preview]');
        const changeBtn = form.querySelector('[data-staff-change]');
        const removeBtn = form.querySelector('[data-staff-remove]');
        const removeInput = form.querySelector('[data-staff-remove-input]');
        const progress = form.querySelector('[data-staff-progress]');
        const fallbackSrc = preview ? preview.src : '';
        if (!fileInput || !dropzone || !preview) return;

        function setProgress(on) {
            if (!progress) return;
            progress.style.width = on ? '100%' : '0%';
            if (on) {
                window.setTimeout(function () {
                    progress.style.width = '0%';
                }, 850);
            }
        }

        function acceptFile(file) {
            if (!file) return;
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                window.showToast('Only JPG, PNG, and WebP images are allowed.', 'danger');
                fileInput.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                window.showToast('Profile image must be 5 MB or smaller.', 'danger');
                fileInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function (ev) {
                preview.src = ev.target.result;
                dropzone.classList.add('has-preview');
                if (removeInput) removeInput.value = '0';
                setProgress(true);
            };
            reader.readAsDataURL(file);
        }

        changeBtn?.addEventListener('click', function () {
            fileInput.click();
        });
        dropzone.addEventListener('click', function (ev) {
            if (ev.target === fileInput) return;
            fileInput.click();
        });
        dropzone.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                fileInput.click();
            }
        });
        fileInput.addEventListener('change', function () {
            acceptFile(fileInput.files && fileInput.files[0]);
        });
        ['dragenter', 'dragover'].forEach(function (name) {
            dropzone.addEventListener(name, function (ev) {
                ev.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            dropzone.addEventListener(name, function (ev) {
                ev.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', function (ev) {
            const file = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            acceptFile(file);
        });
        removeBtn?.addEventListener('click', function () {
            fileInput.value = '';
            preview.src = fallbackSrc;
            if (removeInput) removeInput.value = '1';
            window.showToast('Image will be removed after saving.', 'warning');
        });
    });

    const staffSearch = document.querySelector('[data-staff-search]');
    const staffStatus = document.querySelector('[data-staff-status-filter]');
    const staffRows = Array.from(document.querySelectorAll('[data-staff-row]'));
    function filterStaffRows() {
        const q = (staffSearch?.value || '').trim().toLowerCase();
        const status = staffStatus?.value || '';
        staffRows.forEach(function (row) {
            const okText = !q || (row.getAttribute('data-search') || '').includes(q);
            const okStatus = !status || row.getAttribute('data-status') === status;
            row.classList.toggle('d-none', !(okText && okStatus));
        });
    }
    staffSearch?.addEventListener('input', filterStaffRows);
    staffStatus?.addEventListener('change', filterStaffRows);

    window.VK_BASE_URL = BASE;
})();
