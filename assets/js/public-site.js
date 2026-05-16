/**
 * Public site: lightweight theme, lazy animation loading, and interaction batching.
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
        var html = document.documentElement;
        html.setAttribute("data-bs-theme", mode);
        html.setAttribute("data-theme", mode);
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

    function prefersReducedMotion() {
        try {
            return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        } catch (e) {
            return false;
        }
    }

    function initAutoReveal() {
        var revealEls = Array.prototype.slice.call(document.querySelectorAll("[data-aos]"));
        if (!revealEls.length) return;

        var reduce = prefersReducedMotion();
        revealEls.forEach(function (el) {
            el.classList.add("vk-reveal");
            var delay = el.getAttribute("data-aos-delay");
            var duration = el.getAttribute("data-aos-duration");
            if (delay) {
                el.style.transitionDelay = parseInt(delay, 10) + "ms";
            }
            if (duration) {
                el.style.transitionDuration = Math.max(260, parseInt(duration, 10)) + "ms";
            }
        });

        if (reduce || !("IntersectionObserver" in window)) {
            revealEls.forEach(function (el) {
                el.classList.add("is-visible");
            });
            return;
        }

        var observer = new IntersectionObserver(
            function (entries, obs) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add("is-visible");
                    obs.unobserve(entry.target);
                });
            },
            { rootMargin: "0px 0px -16% 0px", threshold: 0.12 }
        );

        revealEls.forEach(function (el) {
            observer.observe(el);
        });
    }

    function refreshRevealAnimations() {
        document.querySelectorAll(".vk-reveal:not(.is-visible)").forEach(function (el) {
            var rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                el.classList.add("is-visible");
            }
        });
    }

    function initCounters() {
        var counters = document.querySelectorAll(".vk-home-stat strong, .vk-analytics-kpi strong, .vk-mini-kpi strong");
        if (!counters.length) return;
        var reduce = false;
        try {
            reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        } catch (e) {
            reduce = false;
        }

        function parseValue(text) {
            var clean = String(text || "").replace(/,/g, "");
            var match = clean.match(/([A-Za-z. ]*)?(\d+(?:\.\d+)?)([KkMm%+ ]*)/);
            if (!match) return null;
            return {
                original: text,
                prefix: match[1] || "",
                value: parseFloat(match[2]),
                suffix: match[3] || "",
                decimals: match[2].includes(".") ? 1 : 0,
            };
        }

        function formatValue(data, n) {
            var value = data.decimals ? n.toFixed(data.decimals) : Math.round(n).toLocaleString();
            return data.prefix + value + data.suffix;
        }

        function animate(el) {
            if (el.dataset.vkCounted === "1") return;
            var data = parseValue(el.textContent);
            if (!data) return;
            el.dataset.vkCounted = "1";
            if (reduce) {
                el.textContent = data.original;
                return;
            }
            var start = performance.now();
            var duration = 1150 + Math.min(650, data.value * 6);
            function frame(now) {
                var t = Math.min(1, (now - start) / duration);
                var eased = 1 - Math.pow(1 - t, 3);
                el.textContent = formatValue(data, data.value * eased);
                if (t < 1) {
                    requestAnimationFrame(frame);
                } else {
                    el.textContent = data.original;
                }
            }
            requestAnimationFrame(frame);
        }

        if (!("IntersectionObserver" in window)) {
            counters.forEach(animate);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.35 });

        counters.forEach(function (el) { observer.observe(el); });
    }

    function initTestimonialsCarousel() {
        var root = document.querySelector(".vk-testimonials-swiper");
        if (!root || typeof window.Swiper === "undefined") return;
        if (root.dataset.vkSwiperReady === "1") return;
        root.dataset.vkSwiperReady = "1";
        new window.Swiper(root, {
            slidesPerView: 1,
            spaceBetween: 16,
            loop: true,
            speed: 650,
            grabCursor: true,
            watchOverflow: true,
            autoplay: {
                delay: 4200,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            navigation: {
                nextEl: ".vk-testimonial-next",
                prevEl: ".vk-testimonial-prev",
            },
            pagination: {
                el: ".vk-testimonial-pagination",
                clickable: true,
            },
            breakpoints: {
                768: { slidesPerView: 2, spaceBetween: 20 },
                992: { slidesPerView: 3, spaceBetween: 20 },
                1200: { slidesPerView: 4, spaceBetween: 20 },
            },
        });
    }

    function prefersReducedMotion() {
        try {
            return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        } catch (e) {
            return false;
        }
    }

    function createMotionTracker(element, callback) {
        var latestEvent = null;
        var frameId = null;
        function flush() {
            frameId = null;
            if (!latestEvent) return;
            callback(latestEvent);
            latestEvent = null;
        }
        element.addEventListener("pointermove", function (ev) {
            latestEvent = ev;
            if (frameId === null) {
                frameId = requestAnimationFrame(flush);
            }
        }, { passive: true });
    }

    function initEnterpriseInteractions() {
        if (prefersReducedMotion()) return;

        var hero = document.querySelector(".vk-home-hero");
        if (hero) {
            createMotionTracker(hero, function (ev) {
                var rect = hero.getBoundingClientRect();
                var x = ((ev.clientX - rect.left) / Math.max(rect.width, 1)) * 100;
                var y = ((ev.clientY - rect.top) / Math.max(rect.height, 1)) * 100;
                hero.style.setProperty("--vk-glow-x", x.toFixed(2) + "%");
                hero.style.setProperty("--vk-glow-y", y.toFixed(2) + "%");
            });
        }

        document.querySelectorAll(".vk-btn-hero-primary, .vk-btn-hero-secondary, .vk-nav-book-btn, .vk-ready-card .btn").forEach(function (btn) {
            btn.classList.add("vk-magnetic");
            createMotionTracker(btn, function (ev) {
                var rect = btn.getBoundingClientRect();
                var x = ((ev.clientX - rect.left) - rect.width / 2) * 0.12;
                var y = ((ev.clientY - rect.top) - rect.height / 2) * 0.18;
                btn.style.setProperty("--vk-magnet-x", x.toFixed(1) + "px");
                btn.style.setProperty("--vk-magnet-y", y.toFixed(1) + "px");
            });
            btn.addEventListener("pointerleave", function () {
                btn.style.setProperty("--vk-magnet-x", "0px");
                btn.style.setProperty("--vk-magnet-y", "0px");
            });
        });

        document.querySelectorAll(".vk-service-card, .vk-why-card, .vk-testimonial-card, .vk-step-card").forEach(function (card) {
            createMotionTracker(card, function (ev) {
                var rect = card.getBoundingClientRect();
                var x = ((ev.clientX - rect.left) / Math.max(rect.width, 1)) * 100;
                var y = ((ev.clientY - rect.top) / Math.max(rect.height, 1)) * 100;
                card.style.setProperty("--vk-spot-x", x.toFixed(2) + "%");
                card.style.setProperty("--vk-spot-y", y.toFixed(2) + "%");
                if (card.classList.contains("vk-service-card")) {
                    card.style.setProperty("--vk-tilt-x", (((x - 50) / 50) * 1.2).toFixed(2) + "deg");
                    card.style.setProperty("--vk-tilt-y", (((50 - y) / 50) * 1).toFixed(2) + "deg");
                }
            });
            card.addEventListener("pointerleave", function () {
                card.style.setProperty("--vk-spot-x", "50%");
                card.style.setProperty("--vk-spot-y", "0%");
                card.style.setProperty("--vk-tilt-x", "0deg");
                card.style.setProperty("--vk-tilt-y", "0deg");
            });
        });
    }

    function initLiveMetricRefresh() {
        if (prefersReducedMotion()) return;
        var nodes = document.querySelectorAll(".vk-mini-kpi small, .vk-analytics-kpi strong");
        if (!nodes.length) return;
        window.setInterval(function () {
            nodes.forEach(function (el, idx) {
                el.classList.add("vk-live-tick");
                window.setTimeout(function () {
                    el.classList.remove("vk-live-tick");
                }, 520 + idx * 20);
            });
        }, 4200);
    }

    var nav = document.querySelector(".vk-navbar-premium");
    var supportWidget = document.querySelector(".vk-support-widget");
    var whatsappWidget = document.querySelector(".vk-float-wa");
    var scrolled = false;

    function onScroll() {
        var y = window.scrollY || document.documentElement.scrollTop || 0;
        var on = y > 16;
        if (supportWidget) supportWidget.classList.toggle("is-visible", y > 520);
        if (whatsappWidget) whatsappWidget.classList.toggle("is-visible", y > 240);
        if (on !== scrolled) {
            scrolled = on;
            if (nav) nav.classList.toggle("is-scrolled", on);
        }
        updateScrollProgress();
    }

    function updateScrollProgress() {
        if (!nav) return;
        var winHeight = window.innerHeight;
        var docHeight = document.documentElement.scrollHeight;
        var totalScroll = docHeight - winHeight;
        var scrollAmount = window.scrollY || 0;
        var scrollPercent = totalScroll > 0 ? scrollAmount / totalScroll : 0;
        document.documentElement.style.setProperty("--vk-scroll-progress", String(Math.min(1, Math.max(0, scrollPercent))));
    }

    function initScrollProgressBar() {
        updateScrollProgress();
        window.addEventListener("scroll", updateScrollProgress, { passive: true });
    }

    function initActiveMenuDetection() {
        var links = document.querySelectorAll("[data-nav-link]");
        if (!links.length) return;
        var path = window.location.pathname;
        links.forEach(function (link) {
            link.classList.remove("active");
            link.removeAttribute("aria-current");
            var href = link.getAttribute("href") || "";
            var linkPath = new URL(href, window.location.origin).pathname;
            var dataSlug = link.getAttribute("data-nav-link") || "";
            if (linkPath === path || (dataSlug && window.location.pathname.includes(dataSlug))) {
                link.classList.add("active");
                link.setAttribute("aria-current", "page");
            }
        });
    }

    function initRippleEffect() {
        if (prefersReducedMotion()) return;
        document.querySelectorAll("[data-animate='ripple']").forEach(function (btn) {
            btn.addEventListener("click", function (ev) {
                if (ev.detail === 0) return;
                var rect = btn.getBoundingClientRect();
                var x = ev.clientX - rect.left;
                var y = ev.clientY - rect.top;
                var ripple = document.createElement("span");
                ripple.className = "vk-ripple-effect";
                ripple.style.left = x + "px";
                ripple.style.top = y + "px";
                btn.style.position = "relative";
                btn.appendChild(ripple);
                window.setTimeout(function () {
                    ripple.remove();
                }, 600);
            });
        });
    }

    function initSystemThemePreference() {
        try {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (stored && (stored === "dark" || stored === "light")) {
                return;
            }
            if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
                setTheme("dark");
            } else {
                setTheme("light");
            }
        } catch (e) {
            /* ignore */
        }
    }

    function initKeyboardNavigation() {
        document.addEventListener("keydown", function (ev) {
            if (ev.key === "Escape") {
                var pubNav = document.getElementById("pubNav");
                if (pubNav && pubNav.classList.contains("show")) {
                    var toggler = document.querySelector(".navbar-toggler");
                    if (toggler) toggler.click();
                }
            }
        });
    }

    function loadDeferredFeatures() {
        initAutoReveal();
        initCounters();
        initTestimonialsCarousel();
        initEnterpriseInteractions();
        initLiveMetricRefresh();
    }

    document.addEventListener("DOMContentLoaded", function () {
        initLucide();
        initActiveMenuDetection();
        initKeyboardNavigation();
        initSystemThemePreference();
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
        if (supportWidget) {
            supportWidget.classList.toggle("is-visible", (window.scrollY || 0) > 520);
        }
        if (whatsappWidget) {
            whatsappWidget.classList.toggle("is-visible", (window.scrollY || 0) > 240);
        }
        window.addEventListener("scroll", onScroll, { passive: true });

        var pubNav = document.getElementById("pubNav");
        if (pubNav) {
            pubNav.addEventListener("shown.bs.collapse", refreshRevealAnimations);
            pubNav.addEventListener("hidden.bs.collapse", function () {
                document.querySelectorAll("[data-nav-link]").forEach(function (link) {
                    link.blur();
                });
            });
        }

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

        if (typeof requestIdleCallback === "function") {
            requestIdleCallback(loadDeferredFeatures, { timeout: 900 });
        } else {
            setTimeout(loadDeferredFeatures, 360);
        }
    });

    window.addEventListener("load", function () {
        refreshRevealAnimations();
    });
})();
