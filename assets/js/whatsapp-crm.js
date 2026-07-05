(function () {
    'use strict';

    const app = document.getElementById('vkWaApp');
    if (!app) {
        return;
    }

    const searchInput = document.getElementById('vkWaSearch');
    const filterTabs = document.querySelectorAll('.vk-wa-filter-tab');
    const convItems = document.querySelectorAll('.vk-wa-conv');
    const shell = document.getElementById('vkWaShell');
    const composerForm = document.getElementById('vkWaComposerForm');
    const messageInput = document.getElementById('vkWaMessageInput');
    const templateSelect = document.getElementById('vkWaTemplateSelect');
    const emojiToggle = document.getElementById('vkWaEmojiToggle');
    const emojiPopover = document.getElementById('vkWaEmojiPopover');
    const dropzone = document.getElementById('vkWaDropzone');
    const mobileBack = document.getElementById('vkWaMobileBack');
    const infoToggle = document.getElementById('vkWaInfoToggle');
    const panelLeft = document.querySelector('.vk-wa-panel-left');
    const panelChat = document.querySelector('.vk-wa-panel-chat');
    const panelRight = document.querySelector('.vk-wa-panel-right');
    const messagesEl = document.getElementById('vkWaMessages');
    const typingEl = document.getElementById('vkWaTyping');

    let activeFilter = 'all';
    let typingTimer = null;

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

    function showMobileView(view) {
        if (window.innerWidth >= 768) {
            return;
        }
        if (panelLeft) {
            panelLeft.classList.toggle('is-mobile-visible', view === 'list');
        }
        if (panelChat) {
            panelChat.classList.toggle('is-mobile-visible', view === 'chat');
        }
        if (panelRight) {
            panelRight.classList.toggle('is-mobile-visible', view === 'profile');
        }
    }

    function scrollMessagesBottom() {
        if (messagesEl) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function applySearchFilter() {
        const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const re = q.length >= 2 ? new RegExp('(' + escapeRegExp(q) + ')', 'gi') : null;

        convItems.forEach(function (item) {
            const hay = (item.dataset.search || '').toLowerCase();
            const matchesSearch = !q || hay.indexOf(q) !== -1;
            const matchesFilter = matchFilter(item);
            const visible = matchesSearch && matchesFilter;
            item.classList.toggle('is-hidden', !visible);

            if (re) {
                const nameEl = item.querySelector('.vk-wa-conv-name');
                const previewEl = item.querySelector('.vk-wa-conv-preview');
                [nameEl, previewEl].forEach(function (el) {
                    if (!el) {
                        return;
                    }
                    const raw = el.dataset.raw || el.textContent;
                    el.dataset.raw = raw;
                    if (visible && re.test(raw)) {
                        el.innerHTML = raw.replace(re, '<mark>$1</mark>');
                    } else {
                        el.textContent = raw;
                    }
                });
            }
        });
    }

    function matchFilter(item) {
        if (activeFilter === 'all') {
            return true;
        }
        if (activeFilter === 'unread') {
            return item.dataset.unread === '1';
        }
        if (activeFilter === 'assigned') {
            return item.dataset.assigned === '1';
        }
        if (activeFilter === 'open') {
            return item.dataset.open === '1';
        }
        if (activeFilter === 'closed') {
            return item.dataset.closed === '1';
        }
        if (activeFilter === 'today') {
            return item.dataset.today === '1';
        }
        if (activeFilter === 'week') {
            return item.dataset.week === '1';
        }
        return true;
    }

    function autoExpandTextarea(el) {
        if (!el) {
            return;
        }
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }

    function insertQuickReply(text) {
        if (!messageInput) {
            return;
        }
        messageInput.value = text;
        autoExpandTextarea(messageInput);
        messageInput.focus();
        showTypingPulse();
    }

    function showTypingPulse() {
        if (!typingEl) {
            return;
        }
        typingEl.classList.add('is-visible');
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function () {
            typingEl.classList.remove('is-visible');
        }, 1800);
    }

    function initFilters() {
        filterTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                filterTabs.forEach(function (t) {
                    t.classList.remove('is-active');
                });
                tab.classList.add('is-active');
                activeFilter = tab.dataset.filter || 'all';
                applySearchFilter();
            });
        });
    }

    function initConversations() {
        convItems.forEach(function (link) {
            link.addEventListener('click', function () {
                showMobileView('chat');
            });
        });
    }

    function initComposer() {
        if (messageInput) {
            messageInput.addEventListener('input', function () {
                autoExpandTextarea(messageInput);
            });
            messageInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (composerForm) {
                        composerForm.requestSubmit();
                    }
                }
            });
        }

        document.querySelectorAll('.vk-wa-quick-reply').forEach(function (btn) {
            btn.addEventListener('click', function () {
                insertQuickReply(btn.dataset.reply || '');
                if (templateSelect && btn.dataset.template) {
                    templateSelect.value = btn.dataset.template;
                }
            });
        });

        if (composerForm) {
            composerForm.addEventListener('submit', function () {
                if (shell) {
                    shell.classList.add('is-submitting');
                }
            });
        }
    }

    function initEmoji() {
        if (!emojiToggle || !emojiPopover || !messageInput) {
            return;
        }
        const emojis = ['😀', '😊', '👍', '🙏', '✅', '🔧', '📦', '💳', '📅', '⚠️', '🎉', '❤️'];
        emojiPopover.innerHTML = emojis.map(function (em) {
            return '<button type="button" class="vk-wa-emoji-btn" data-emoji="' + em + '" aria-label="Insert ' + em + '">' + em + '</button>';
        }).join('');

        emojiToggle.addEventListener('click', function () {
            emojiPopover.classList.toggle('is-open');
        });

        emojiPopover.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-emoji]');
            if (!btn) {
                return;
            }
            messageInput.value += btn.dataset.emoji;
            autoExpandTextarea(messageInput);
            messageInput.focus();
            emojiPopover.classList.remove('is-open');
        });

        document.addEventListener('click', function (e) {
            if (!emojiPopover.contains(e.target) && e.target !== emojiToggle) {
                emojiPopover.classList.remove('is-open');
            }
        });
    }

    function initDropzone() {
        if (!dropzone) {
            return;
        }
        ['dragenter', 'dragover'].forEach(function (ev) {
            dropzone.addEventListener(ev, function (e) {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            dropzone.addEventListener(ev, function (e) {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', function () {
            if (typeof showToast === 'function') {
                showToast('Attach files after connecting WhatsApp Cloud API credentials.', 'info');
            }
        });
    }

    function initMobile() {
        if (mobileBack) {
            mobileBack.addEventListener('click', function () {
                showMobileView('list');
            });
        }
        if (infoToggle) {
            infoToggle.addEventListener('click', function () {
                showMobileView('profile');
            });
        }
        const profileClose = document.getElementById('vkWaProfileClose');
        if (profileClose) {
            profileClose.addEventListener('click', function () {
                showMobileView('chat');
            });
        }
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) {
                if (panelLeft) {
                    panelLeft.classList.remove('is-mobile-visible');
                }
                if (panelChat) {
                    panelChat.classList.add('is-mobile-visible');
                }
                if (panelRight) {
                    panelRight.classList.remove('is-mobile-visible');
                }
            } else {
                const hasChat = document.querySelector('.vk-wa-conv.is-active');
                showMobileView(hasChat ? 'chat' : 'list');
            }
        });
        if (window.innerWidth < 768) {
            const hasChat = document.querySelector('.vk-wa-conv.is-active');
            showMobileView(hasChat ? 'chat' : 'list');
        }
    }

    function initKeyboard() {
        document.addEventListener('keydown', function (e) {
            if (e.key === '/' && document.activeElement !== searchInput) {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                }
            }
            if (e.key === 'Escape') {
                if (emojiPopover) {
                    emojiPopover.classList.remove('is-open');
                }
                if (panelRight && panelRight.classList.contains('is-mobile-visible')) {
                    showMobileView('chat');
                }
            }
        });
    }

    function removeSkeleton() {
        if (shell) {
            shell.classList.remove('vk-wa-skeleton');
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', debounce(applySearchFilter, 280));
    }

    initFilters();
    initConversations();
    initComposer();
    initEmoji();
    initDropzone();
    initMobile();
    initKeyboard();
    initTooltips();
    applySearchFilter();
    scrollMessagesBottom();

    window.setTimeout(removeSkeleton, 400);
})();
