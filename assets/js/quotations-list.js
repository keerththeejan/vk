(function () {
    'use strict';
    document.addEventListener('keydown', function (e) {
        if (e.target && /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return;
        if (e.key === 'n' || e.key === 'N') {
            const fab = document.querySelector('.qtn-fab');
            if (fab) window.location.href = fab.getAttribute('href');
        }
    });
})();
