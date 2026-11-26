(function () {
    'use strict';

    var sidebar = document.getElementById('sidebarOffcanvas');
    if (!sidebar) return;

    var storageKey = 'vk_sidebar_groups_v1';
    var saved = {};
    try { saved = JSON.parse(localStorage.getItem(storageKey) || '{}') || {}; } catch (e) { saved = {}; }

    sidebar.querySelectorAll('[data-vk-nav-group]').forEach(function (button) {
        var key = button.getAttribute('data-vk-nav-group');
        var target = document.querySelector(button.getAttribute('data-bs-target'));
        if (!target) return;
        var routeIsActive = !!target.querySelector('.nav-link.active');
        if (routeIsActive) {
            saved[key] = true;
        } else if (Object.prototype.hasOwnProperty.call(saved, key) && window.bootstrap && bootstrap.Collapse) {
            bootstrap.Collapse.getOrCreateInstance(target, { toggle: false })[saved[key] ? 'show' : 'hide']();
        }
        target.addEventListener('shown.bs.collapse', function () { saved[key] = true; localStorage.setItem(storageKey, JSON.stringify(saved)); });
        target.addEventListener('hidden.bs.collapse', function () { saved[key] = false; localStorage.setItem(storageKey, JSON.stringify(saved)); });
    });

    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
        var label = (link.querySelector('span') || {}).textContent;
        if (!label) label = link.getAttribute('aria-label') || '';
        if (label) {
            link.setAttribute('title', label.trim());
            if (!link.hasAttribute('aria-label')) link.setAttribute('aria-label', label.trim());
        }
        if (link.matches('a')) {
            link.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 991.98px)').matches && window.bootstrap && bootstrap.Offcanvas) {
                    bootstrap.Offcanvas.getOrCreateInstance(sidebar).hide();
                }
            });
        }
    });

    sidebar.querySelectorAll('.nav-link.active').forEach(function (link) {
        if (link.matches('a')) link.setAttribute('aria-current', 'page');
    });
}());
