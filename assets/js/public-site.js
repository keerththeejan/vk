/**
 * Public site: theme, navbar scroll, AOS, Lucide icons
 */
(function () {
    "use strict";

    var STORAGE_KEY = "vk-public-theme";

    function getTheme() {
        var h = document.documentElement;
        if (h.getAttribute("data-theme") === "dark" || h.getAttribute("data-bs-theme") === "dark") {
            return "dark";
        }
        return "light";
    }

    function setTheme(mode) {
        document.documentElement.setAttribute("data-bs-theme", mode);
        document.documentElement.setAttribute("data-theme", mode);
        try {
            localStorage.setItem(STORAGE_KEY, mode);
        } catch (e) {
            /* ignore */
        }
        updateToggleUi(mode);
    }

    function updateToggleUi(mode) {
        var btn = document.querySelector("[data-vk-theme-toggle]");
        if (!btn) return;
        var sun = btn.querySelector(".vk-theme-icon-sun");
        var moon = btn.querySelector(".vk-theme-icon-moon");
        btn.setAttribute("aria-pressed", mode === "dark");
        btn.setAttribute("aria-label", mode === "dark" ? "Switch to light mode" : "Switch to dark mode");
        if (sun) {
            sun.classList.toggle("d-none", mode !== "dark");
            sun.classList.toggle("d-inline-flex", mode === "dark");
        }
        if (moon) {
            moon.classList.toggle("d-none", mode === "dark");
            moon.classList.toggle("d-inline-flex", mode !== "dark");
        }
    }

    function getLucide() {
        return typeof lucide !== "undefined" ? lucide : typeof window.lucide !== "undefined" ? window.lucide : null;
    }

    function initLucide() {
        var L = getLucide();
        if (L && typeof L.createIcons === "function") {
            L.createIcons({ attrs: { "stroke-width": 1.75 } });
        }
    }

    function initAOS() {
        if (typeof AOS === "undefined") {
            document.querySelectorAll("[data-aos]").forEach(function (el) {
                el.classList.add("aos-animate");
            });
            return;
        }
        var reduce = false;
        try {
            reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        } catch (e) {
            reduce = false;
        }
        AOS.init({
            duration: 680,
            easing: "ease-out-cubic",
            once: true,
            offset: 48,
            delay: 0,
            disable: reduce,
            anchorPlacement: "top-bottom",
        });
    }

    function refreshAOS() {
        if (typeof AOS !== "undefined" && typeof AOS.refresh === "function") {
            AOS.refresh();
        }
    }

    var nav = document.querySelector(".vk-navbar-premium");
    var scrolled = false;

    function onScroll() {
        var y = window.scrollY || document.documentElement.scrollTop || 0;
        var on = y > 16;
        if (on === scrolled) return;
        scrolled = on;
        if (nav) nav.classList.toggle("is-scrolled", on);
    }

    document.addEventListener("DOMContentLoaded", function () {
        initAOS();
        initLucide();

        updateToggleUi(getTheme());

        var toggle = document.querySelector("[data-vk-theme-toggle]");
        if (toggle) {
            toggle.addEventListener("click", function () {
                setTheme(getTheme() === "dark" ? "light" : "dark");
            });
        }

        if (nav) {
            scrolled = (window.scrollY || 0) > 16;
            nav.classList.toggle("is-scrolled", scrolled);
        }
        window.addEventListener("scroll", onScroll, { passive: true });

        var pubNav = document.getElementById("pubNav");
        if (pubNav) {
            pubNav.addEventListener("shown.bs.collapse", refreshAOS);
        }

        window.addEventListener("load", function () {
            refreshAOS();
        });

        var previewModal = document.getElementById("galleryPreviewModal");
        if (previewModal) {
            previewModal.addEventListener("show.bs.modal", function (ev) {
                var trigger = ev.relatedTarget;
                if (!trigger) return;
                var src = trigger.getAttribute("data-vk-gallery-src") || "";
                var title = trigger.getAttribute("data-vk-gallery-title") || "";
                var img = document.getElementById("galleryPreviewImage");
                var heading = document.getElementById("galleryPreviewTitle");
                if (img) {
                    img.src = src;
                    img.alt = title || "Gallery image";
                }
                if (heading) {
                    heading.textContent = title || heading.textContent;
                }
            });
        }

        var serviceRoot = document.querySelector("[data-vk-service-slug]");
        if (serviceRoot) {
            var slug = serviceRoot.getAttribute("data-vk-service-slug") || "service";
            var viewsKey = "vk:views:" + slug;
            var viewEl = document.querySelector("[data-vk-view-count]");
            var views = parseInt(localStorage.getItem(viewsKey) || "0", 10);
            views = isNaN(views) ? 1 : views + 1;
            localStorage.setItem(viewsKey, String(views));
            if (viewEl) viewEl.textContent = String(views);

            var ratingsKey = "vk:ratings:" + slug;
            var reviewsKey = "vk:reviews:" + slug;
            var stars = document.querySelectorAll("[data-vk-star]");
            var avgEl = document.querySelector("[data-vk-rating-avg]");
            var countEl = document.querySelector("[data-vk-rating-count]");
            var listEl = document.querySelector("[data-vk-review-list]");
            var reviewInput = document.getElementById("vkReviewInput");
            var reviewBtn = document.querySelector("[data-vk-review-submit]");

            function getArray(k) {
                try {
                    var raw = localStorage.getItem(k);
                    var parsed = raw ? JSON.parse(raw) : [];
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            }

            function setArray(k, arr) {
                localStorage.setItem(k, JSON.stringify(arr));
            }

            function renderRatings() {
                var arr = getArray(ratingsKey);
                var total = arr.reduce(function (acc, n) { return acc + Number(n || 0); }, 0);
                var avg = arr.length ? (total / arr.length) : 0;
                if (avgEl) avgEl.textContent = avg.toFixed(1);
                if (countEl) countEl.textContent = String(arr.length);
            }

            function renderReviews() {
                if (!listEl) return;
                var arr = getArray(reviewsKey);
                listEl.innerHTML = "";
                arr.slice(-8).reverse().forEach(function (txt) {
                    var li = document.createElement("li");
                    li.className = "list-group-item";
                    li.textContent = String(txt);
                    listEl.appendChild(li);
                });
            }

            stars.forEach(function (btn) {
                btn.addEventListener("click", function () {
                    var val = parseInt(btn.getAttribute("data-vk-star") || "0", 10);
                    if (!val || val < 1 || val > 5) return;
                    var arr = getArray(ratingsKey);
                    arr.push(val);
                    setArray(ratingsKey, arr);
                    renderRatings();
                });
            });

            if (reviewBtn && reviewInput) {
                reviewBtn.addEventListener("click", function () {
                    var txt = (reviewInput.value || "").trim();
                    if (!txt) return;
                    var arr = getArray(reviewsKey);
                    arr.push(txt);
                    setArray(reviewsKey, arr);
                    reviewInput.value = "";
                    renderReviews();
                });
            }

            renderRatings();
            renderReviews();
        }
    });
})();
