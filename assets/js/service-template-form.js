(function () {
    'use strict';

    function baseUrl() {
        if (typeof window.VK_BASE_URL === 'string') {
            return window.VK_BASE_URL.replace(/\/$/, '');
        }
        return '';
    }

    function updateAspectHint(file, hintEl) {
        if (!hintEl || !file || !file.type.match(/^image\//)) return;
        var img = new Image();
        var u = URL.createObjectURL(file);
        img.onload = function () {
            URL.revokeObjectURL(u);
            var w = img.naturalWidth;
            var h = img.naturalHeight;
            if (h < 1) return;
            var ratio = w / h;
            var target = 16 / 9;
            var ok = Math.abs(ratio - target) <= 0.08;
            hintEl.classList.remove('alert-info', 'alert-warning');
            if (ok) {
                hintEl.classList.add('alert-info');
                hintEl.innerHTML =
                    'Great — aspect ratio looks good for hero (<strong>16:9</strong>). Your file is <strong>' +
                    w +
                    '×' +
                    h +
                    '</strong>.';
            } else {
                hintEl.classList.add('alert-warning');
                hintEl.innerHTML =
                    'Please upload a <strong>16:9 image</strong> for the best hero and card display. Your file is <strong>' +
                    w +
                    '×' +
                    h +
                    '</strong> — it will be center-cropped.';
            }
        };
        img.onerror = function () {
            URL.revokeObjectURL(u);
        };
        img.src = u;
    }

    function initServiceImageField(wrap) {
        if (!wrap) return;

        var dropzone = wrap.querySelector('.st-svc-img-dropzone');
        var input = wrap.querySelector('input[type="file"][name="service_image"]');
        var hiddenRemove = wrap.querySelector('#remove_service_image');
        var placeholder = wrap.querySelector('.st-svc-img-placeholder');
        var preview = wrap.querySelector('.st-svc-img-preview');
        var previewImg = wrap.querySelector('#stSvcImgPreviewImg');
        var filenameEl = wrap.querySelector('#stSvcImgFilename');
        var removeBtn = wrap.querySelector('#stSvcImgRemove');
        var replaceBtn = wrap.querySelector('#stSvcImgReplace');
        var spinner = wrap.querySelector('#stSvcImgSpinner');
        var aspectHint = document.getElementById('st-aspect-hint');

        var serverPath = (wrap.getAttribute('data-current') || '').trim();

        function showPlaceholder() {
            if (placeholder) {
                placeholder.classList.remove('is-hidden');
            }
            if (preview) {
                preview.classList.add('d-none');
                preview.classList.remove('is-visible');
            }
            if (previewImg) previewImg.removeAttribute('src');
            if (filenameEl) filenameEl.textContent = '';
            if (spinner) spinner.classList.add('d-none');
        }

        function showPreviewUrl(url, name) {
            if (!preview || !previewImg) return;
            if (placeholder) placeholder.classList.add('is-hidden');
            preview.classList.remove('d-none');
            preview.classList.add('is-visible');
            previewImg.src = url;
            if (filenameEl && name) filenameEl.textContent = name;
            if (spinner) spinner.classList.add('d-none');
        }

        if (serverPath) {
            showPreviewUrl(baseUrl() + '/' + serverPath.replace(/^\//, ''), serverPath.split('/').pop() || '');
            if (hiddenRemove) hiddenRemove.value = '0';
        }

        function clearClientFile() {
            if (input) input.value = '';
        }

        function openFilePicker(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (input) input.click();
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                clearClientFile();
                if (serverPath && hiddenRemove) {
                    hiddenRemove.value = '1';
                }
                serverPath = '';
                showPlaceholder();
                if (typeof window.showToast === 'function') {
                    window.showToast('Image removed — click Save to confirm.', 'info');
                }
            });
        }

        if (replaceBtn) {
            replaceBtn.addEventListener('click', openFilePicker);
        }

        if (dropzone && input) {
            dropzone.addEventListener('click', function (e) {
                if (e.target.closest('.st-svc-img-actions')) return;
                if (e.target.closest('.st-svc-img-remove')) return;
                input.click();
            });
            dropzone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    input.click();
                }
            });

            ['dragenter', 'dragover'].forEach(function (ev) {
                dropzone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                dropzone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('is-dragover');
                });
            });
            dropzone.addEventListener('drop', function (e) {
                var dt = e.dataTransfer;
                if (!dt || !dt.files || !dt.files.length) return;
                var f = dt.files[0];
                if (!f.type.match(/^image\//)) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Please drop an image file.', 'warning');
                    }
                    return;
                }
                try {
                    var container = new DataTransfer();
                    container.items.add(f);
                    input.files = container.files;
                } catch (err) {
                    return;
                }
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (!f) return;
                if (hiddenRemove) hiddenRemove.value = '0';
                serverPath = '';
                var url = URL.createObjectURL(f);
                showPreviewUrl(url, f.name);
                if (aspectHint) {
                    updateAspectHint(f, aspectHint);
                }
                if (typeof window.showToast === 'function') {
                    window.showToast('Image ready — click Save to optimize & upload.', 'success');
                }
            });
        }

        var form = wrap.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (dropzone) dropzone.classList.add('is-busy');
                if (spinner) spinner.classList.remove('d-none');
                if (placeholder) placeholder.classList.add('d-none');
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-st-service-image]').forEach(function (el) {
            initServiceImageField(el);
        });
    });
})();
