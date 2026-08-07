/**
 * Product Studio v2 — UI enhancements (preserves all form names/IDs/backend)
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var studio = document.getElementById("productStudio");
    var form = document.getElementById("product-form");
    if (!studio || !form || studio.dataset.studioV2 !== "1") return;

    var nameInput = document.getElementById("name");
    var seoUrlInput = document.getElementById("seo_url");
    var nameCharCount = document.getElementById("nameCharCount");
    var slugPreview = document.getElementById("slugPreviewText");
    var progressFill = document.getElementById("studioProgressFill");
    var progressLabel = document.getElementById("studioProgressLabel");
    var stepperItems = Array.prototype.slice.call(document.querySelectorAll(".studio-stepper-item"));
    var sections = Array.prototype.slice.call(document.querySelectorAll(".studio-section"));
    var undoStack = [];
    var redoStack = [];
    var maxHistory = 24;

    function slugify(value) {
      return String(value || "")
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") || "product";
    }

    function snapshot() {
      var data = new FormData(form);
      var obj = {};
      data.forEach(function (val, key) {
        if (key === "images[]" || key === "warranty_document") return;
        obj[key] = val;
      });
      return JSON.stringify(obj);
    }

    function pushHistory() {
      var state = snapshot();
      if (undoStack.length && undoStack[undoStack.length - 1] === state) return;
      undoStack.push(state);
      if (undoStack.length > maxHistory) undoStack.shift();
      redoStack = [];
    }

    function restoreHistory(stateJson) {
      var state = JSON.parse(stateJson);
      Object.keys(state).forEach(function (key) {
        var el = form.elements.namedItem(key);
        if (!el) return;
        if (el instanceof RadioNodeList) {
          Array.prototype.forEach.call(el, function (node) {
            node.checked = node.value === state[key];
          });
          return;
        }
        if (el.type === "checkbox") {
          el.checked = state[key] === "on" || state[key] === "1" || state[key] === true;
          return;
        }
        el.value = state[key];
      });
      form.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function updateNameMeta() {
      var len = (nameInput && nameInput.value ? nameInput.value.length : 0);
      if (nameCharCount) {
        nameCharCount.textContent = len + " / 120";
        nameCharCount.parentElement.classList.toggle("is-warning", len > 100);
        nameCharCount.parentElement.classList.toggle("is-success", len > 3 && len <= 100);
      }
      if (seoUrlInput && !seoUrlInput.dataset.manualEdit) {
        var slug = slugify(nameInput ? nameInput.value : "");
        seoUrlInput.value = slug;
      }
      if (slugPreview) {
        slugPreview.textContent = slugify(seoUrlInput ? seoUrlInput.value : nameInput ? nameInput.value : "product");
      }
    }

    function stepCompletion() {
      var checks = {
        basic: Boolean(nameInput && nameInput.value.trim() && document.getElementById("sku") && document.getElementById("sku").value.trim()),
        pricing: Number(document.getElementById("selling_price") && document.getElementById("selling_price").value) > 0,
        inventory: document.getElementById("opening_stock") && document.getElementById("opening_stock").value !== "",
        media: document.getElementById("previewGrid") && document.getElementById("previewGrid").children.length > 0,
        shipping: Boolean(document.getElementById("shipping_weight") && document.getElementById("shipping_weight").value),
        seo: Boolean(document.getElementById("meta_title") && document.getElementById("meta_title").value.trim()),
        warranty: document.getElementById("enableWarranty") && document.getElementById("enableWarranty").checked,
        variants: Boolean(document.getElementById("variantMatrix") && document.getElementById("variantMatrix").children.length),
        review: true,
      };

      var done = 0;
      stepperItems.forEach(function (btn) {
        var key = btn.dataset.stepKey;
        var complete = checks[key] || false;
        btn.classList.toggle("is-complete", complete);
        if (complete) done += 1;
      });

      var pct = Math.round((done / stepperItems.length) * 100);
      if (progressFill) progressFill.style.width = pct + "%";
      if (progressLabel) progressLabel.textContent = done + " of " + stepperItems.length + " steps complete";
    }

    function highlightActiveSection() {
      var scrollY = window.scrollY + 140;
      var active = sections[0];
      sections.forEach(function (section) {
        if (section.offsetTop <= scrollY) active = section;
      });
      sections.forEach(function (section) {
        section.classList.toggle("is-active-step", section === active);
      });
      if (active && active.dataset.stepKey) {
        stepperItems.forEach(function (btn) {
          var on = btn.dataset.stepKey === active.dataset.stepKey;
          btn.classList.toggle("active", on);
          btn.setAttribute("aria-current", on ? "step" : "false");
        });
      }
    }

    function bindRipple() {
      document.querySelectorAll(".studio-btn").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          var rect = btn.getBoundingClientRect();
          var ripple = document.createElement("span");
          ripple.className = "studio-ripple";
          ripple.style.width = ripple.style.height = Math.max(rect.width, rect.height) + "px";
          ripple.style.left = e.clientX - rect.left - ripple.offsetWidth / 2 + "px";
          ripple.style.top = e.clientY - rect.top - ripple.offsetHeight / 2 + "px";
          btn.appendChild(ripple);
          window.setTimeout(function () {
            ripple.remove();
          }, 600);
        });
      });
    }

    /* FAB */
    var fabCluster = document.getElementById("studioFabCluster");
    var fabToggle = document.getElementById("studioFabToggle");
    fabToggle && fabToggle.addEventListener("click", function () {
      fabCluster && fabCluster.classList.toggle("is-open");
    });
    document.getElementById("fabSave") && document.getElementById("fabSave").addEventListener("click", function () {
      document.getElementById("saveDraftButton") && document.getElementById("saveDraftButton").click();
    });
    document.getElementById("fabPublish") && document.getElementById("fabPublish").addEventListener("click", function () {
      document.getElementById("publishButton") && document.getElementById("publishButton").click();
    });
    document.getElementById("fabTop") && document.getElementById("fabTop").addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
    document.getElementById("fabTopQuick") && document.getElementById("fabTopQuick").addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
    document.getElementById("saveRailDraft") && document.getElementById("saveRailDraft").addEventListener("click", function () {
      document.getElementById("saveDraftButton") && document.getElementById("saveDraftButton").click();
    });
    document.getElementById("saveRailPublish") && document.getElementById("saveRailPublish").addEventListener("click", function () {
      document.getElementById("publishButton") && document.getElementById("publishButton").click();
    });
    document.getElementById("fabUndo") && document.getElementById("fabUndo").addEventListener("click", function () {
      if (undoStack.length < 2) return;
      redoStack.push(undoStack.pop());
      restoreHistory(undoStack[undoStack.length - 1]);
    });
    document.getElementById("fabRedo") && document.getElementById("fabRedo").addEventListener("click", function () {
      if (!redoStack.length) return;
      var state = redoStack.pop();
      undoStack.push(state);
      restoreHistory(state);
    });

  if (seoUrlInput) {
      seoUrlInput.addEventListener("input", function () {
        seoUrlInput.dataset.manualEdit = seoUrlInput.value.trim() ? "1" : "";
        if (slugPreview) slugPreview.textContent = slugify(seoUrlInput.value);
      });
    }

    form.addEventListener("input", function () {
      updateNameMeta();
      stepCompletion();
      window.clearTimeout(form._histTimer);
      form._histTimer = window.setTimeout(pushHistory, 600);
    });

    form.addEventListener("change", function () {
      updateNameMeta();
      stepCompletion();
      pushHistory();
    });

    window.addEventListener("scroll", highlightActiveSection, { passive: true });

    bindRipple();
    pushHistory();
    updateNameMeta();
    stepCompletion();
    highlightActiveSection();

    /* Mark analytics skeleton loaded for v2 fade */
    var analyticsCard = document.getElementById("analyticsCard");
    if (analyticsCard) analyticsCard.classList.add("is-loaded");
  });
})();
