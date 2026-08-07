/**
 * Enterprise Product Basic Information module controller.
 * Integrates with product-admin.js without changing backend field names.
 */
(function () {
  "use strict";

  const BASIC_REQUIRED = ["name", "sku", "brand_id", "category_id", "unit_type", "description", "status"];

  document.addEventListener("DOMContentLoaded", function () {
    const module = document.getElementById("basicInfoModule");
    const form = document.getElementById("product-form");
    if (!module || !form) return;

    const autosaveMs = Number.parseInt(module.dataset.autosaveMs || "10000", 10);
    const autosaveUrl = module.dataset.autosaveUrl || form.action || window.location.href;
    const autosaveBadge = document.getElementById("basicAutosaveBadge");
    const validationBadge = document.getElementById("basicValidationBadge");
    const progressBar = document.getElementById("basicModuleProgress");
    const duplicatePanel = document.getElementById("basicDuplicatePanel");
    const duplicateList = document.getElementById("basicDuplicateList");
    const descriptionField = document.getElementById("description");
    const richEditor = document.getElementById("descriptionRichEditor");
    const markdownEditor = document.getElementById("descriptionMarkdown");
    const previewPane = document.getElementById("descriptionPreview");
    const subcategorySelect = document.getElementById("subcategory_id");
    const categorySelect = document.getElementById("category_id");
    const seoUrlInput = document.getElementById("seo_url");

    let autosaveBusy = false;
    let moduleDirty = false;

    const getShell = (control) => control?.closest(".basic-field-shell, .studio-floating, .studio-search-select");

    const ensureMessage = (control) => {
      const shell = getShell(control);
      if (!shell) return null;
      let node = shell.querySelector(".basic-invalid-message, .studio-invalid-message");
      if (!node) {
        node = document.createElement("div");
        node.className = "basic-invalid-message";
        node.hidden = true;
        shell.appendChild(node);
      }
      return node;
    };

    const setFieldState = (control, state, message = "") => {
      const shell = getShell(control);
      const messageNode = ensureMessage(control);
      shell?.classList.remove("is-invalid", "is-valid");
      control?.classList.remove("is-invalid", "is-valid");
      if (messageNode) {
        messageNode.hidden = true;
        messageNode.textContent = message;
      }
      if (!state) return;
      shell?.classList.add(`is-${state}`);
      control?.classList.add(`is-${state}`);
      if (messageNode && state === "invalid" && message) {
        messageNode.hidden = false;
      }
    };

    const validateControl = (control) => {
      if (!control || control.id === "description") return true;
      const value = control.type === "checkbox" ? control.checked : String(control.value || "").trim();
      const label = control.dataset.requiredLabel || control.labels?.[0]?.textContent?.replace("*", "").trim() || control.name;
      if (control.hasAttribute("required") && !value) {
        setFieldState(control, "invalid", `${label} is required.`);
        return false;
      }
      if (value) setFieldState(control, "valid");
      else setFieldState(control, null);
      return true;
    };

    const validateDescription = () => {
      const text = String(descriptionField?.value || "").trim();
      if (!text) {
        setFieldState(richEditor, "invalid", "Product Description is required.");
        return false;
      }
      setFieldState(richEditor, "valid");
      return true;
    };

    const validateModule = () => {
      let valid = true;
      BASIC_REQUIRED.forEach((id) => {
        const control = document.getElementById(id);
        if (id === "description") {
          if (!validateDescription()) valid = false;
          return;
        }
        if (!validateControl(control)) valid = false;
      });

      if (validationBadge) {
        validationBadge.classList.toggle("is-valid", valid);
        validationBadge.classList.toggle("is-invalid", !valid);
        validationBadge.innerHTML = valid
          ? '<i class="bi bi-shield-check"></i> All required fields valid'
          : '<i class="bi bi-exclamation-circle"></i> Required fields missing';
      }

      const filled = BASIC_REQUIRED.filter((id) => {
        if (id === "description") return String(descriptionField?.value || "").trim().length > 0;
        return Boolean(String(document.getElementById(id)?.value || "").trim());
      }).length;
      const pct = Math.round((filled / BASIC_REQUIRED.length) * 100);
      if (progressBar) {
        progressBar.style.width = `${pct}%`;
        progressBar.setAttribute("aria-valuenow", String(pct));
      }
      return valid;
    };

    const updateCounter = (input, counterId, max, suffix) => {
      const counter = document.getElementById(counterId);
      if (!input || !counter) return;
      const len = input.value.length;
      counter.textContent = suffix ? `${len}${suffix}` : `${len} / ${max}`;
      const meta = counter.closest(".basic-field-meta");
      if (meta && max) {
        meta.classList.toggle("is-warning", len > max * 0.85);
        meta.classList.toggle("is-success", len > 3 && len <= max * 0.85);
      }
    };

    const updateTagsCounter = () => {
      const input = document.getElementById("product_tags");
      const counter = document.getElementById("tagsCharCount");
      if (!input || !counter) return;
      const tags = input.value.split(",").map((t) => t.trim()).filter(Boolean);
      counter.textContent = `${tags.length} tag${tags.length === 1 ? "" : "s"}`;
    };

    const slugify = (value) =>
      String(value || "")
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") || "product";

    const updateSlugPreview = () => {
      const name = document.getElementById("name")?.value || "";
      const slugNode = document.getElementById("slugPreviewText");
      if (seoUrlInput && !seoUrlInput.dataset.manualEdit) {
        seoUrlInput.value = slugify(name);
      }
      if (slugNode) {
        slugNode.textContent = slugify(seoUrlInput?.value || name);
      }
    };

    const htmlToMarkdown = (html) => {
      const div = document.createElement("div");
      div.innerHTML = html || "";
      return div.innerText.replace(/\n{3,}/g, "\n\n").trim();
    };

    const markdownToHtml = (md) => {
      if (!md) return "";
      let html = md
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
      html = html.replace(/^## (.+)$/gm, "<h2>$1</h2>");
      html = html.replace(/^- (.+)$/gm, "<li>$1</li>");
      html = html.replace(/(<li>.*<\/li>\n?)+/g, (block) => `<ul>${block}</ul>`);
      html = html.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
      html = html.replace(/\*(.+?)\*/g, "<em>$1</em>");
      html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
      html = html.replace(/\n/g, "<br>");
      return html;
    };

    const syncDescriptionFromRich = () => {
      if (!descriptionField || !richEditor) return;
      const html = richEditor.innerHTML.trim();
      descriptionField.value = htmlToMarkdown(html) || html.replace(/<[^>]+>/g, " ").trim();
      if (markdownEditor) markdownEditor.value = descriptionField.value;
      updateDescriptionMetrics();
      form.dispatchEvent(new Event("input", { bubbles: true }));
    };

    const syncDescriptionFromMarkdown = () => {
      if (!descriptionField || !markdownEditor) return;
      descriptionField.value = markdownEditor.value;
      if (richEditor) richEditor.innerHTML = markdownToHtml(markdownEditor.value);
      updateDescriptionMetrics();
      form.dispatchEvent(new Event("input", { bubbles: true }));
    };

    const updateDescriptionMetrics = () => {
      const text = String(descriptionField?.value || "").trim();
      const words = text ? text.split(/\s+/).filter(Boolean).length : 0;
      const chars = text.length;
      const readingMin = Math.max(1, Math.ceil(words / 200));

      const wordNode = document.getElementById("descriptionWordCount");
      const charNode = document.getElementById("descriptionCharacterCount");
      const readNode = document.getElementById("descriptionReadingTime");
      const readability = document.getElementById("descriptionReadability");
      const charCount = document.getElementById("descriptionCharCount");
      const shortCount = document.getElementById("shortDescCharCount");

      if (wordNode) wordNode.textContent = String(words);
      if (charNode) charNode.textContent = String(chars);
      if (readNode) readNode.textContent = `${readingMin} min`;
      if (readability) readability.textContent = words >= 80 ? "Strong" : words >= 35 ? "Good" : "Needs work";
      if (charCount) charCount.textContent = `${chars} chars`;
      if (shortCount) {
        const shortLen = document.getElementById("short_description")?.value.length || 0;
        shortCount.textContent = `${shortLen} / 512`;
      }
      if (previewPane) previewPane.innerHTML = markdownToHtml(text);
      validateDescription();
    };

    const loadSubcategories = (parentId, selected) => {
      if (!subcategorySelect) return;
      let tree = {};
      try {
        tree = JSON.parse(document.getElementById("basicCategoryTree")?.textContent || "{}");
      } catch (_) {
        tree = {};
      }
      const items = tree[String(parentId)] || tree[parentId] || [];
      subcategorySelect.innerHTML = '<option value="">Select sub category</option>';
      items.forEach((row) => {
        const opt = document.createElement("option");
        opt.value = String(row.id);
        opt.textContent = row.name;
        if (String(selected) === String(row.id)) opt.selected = true;
        subcategorySelect.appendChild(opt);
      });
      if (subcategorySelect.tomselect) {
        subcategorySelect.tomselect.clearOptions();
        subcategorySelect.tomselect.addOption({ value: "", text: "Select sub category" });
        items.forEach((row) => subcategorySelect.tomselect.addOption({ value: String(row.id), text: row.name }));
        subcategorySelect.tomselect.setValue(selected || "", true);
      }
    };

    const markDirty = () => {
      moduleDirty = true;
    };

    const onDraftSaved = (mode) => {
      moduleDirty = false;
      if (!autosaveBadge) return;
      autosaveBadge.className = "basic-autosave-badge is-saved";
      autosaveBadge.innerHTML = `<i class="bi bi-cloud-check"></i> Saved ${new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}`;
    };

    document.addEventListener("vk:draft-saved", (event) => {
      onDraftSaved(event.detail?.mode || "autosave");
    });

    const observeGlobalAutosave = () => {
      const globalBadge = document.getElementById("autosaveStatus");
      if (!globalBadge || !autosaveBadge) return;
      const observer = new MutationObserver(() => {
        const text = globalBadge.textContent || "";
        if (text.includes("Auto-saving") || text.includes("Saving draft")) {
          autosaveBadge.className = "basic-autosave-badge is-saving";
          autosaveBadge.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving draft…';
        } else if (text.includes("synced") || text.includes("Draft synced")) {
          onDraftSaved("autosave");
        } else if (text.includes("failed")) {
          autosaveBadge.className = "basic-autosave-badge is-error";
          autosaveBadge.innerHTML = '<i class="bi bi-cloud-slash"></i> Auto-save failed';
        }
      });
      observer.observe(globalBadge, { childList: true, characterData: true, subtree: true });
    };

    const detectDuplicates = async () => {
      const name = document.getElementById("name")?.value.trim() || "";
      const sku = document.getElementById("sku")?.value.trim() || "";
      if (!name && !sku) return;

      module.classList.add("is-loading");
      try {
        const payload = new FormData();
        payload.set("intent", "detect_duplicate");
        payload.set("name", name);
        payload.set("sku", sku);
        const response = await fetch(autosaveUrl, {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
          body: payload,
        });
        const data = await response.json();
        const matches = data.matches || [];
        if (!duplicatePanel || !duplicateList) return;
        if (matches.length === 0) {
          duplicatePanel.hidden = true;
          duplicateList.innerHTML = "";
          return;
        }
        duplicatePanel.hidden = false;
        duplicateList.innerHTML = matches
          .map((item) => `<li>${item.name}${item.sku ? ` <small>(${item.sku})</small>` : ""}</li>`)
          .join("");
      } finally {
        module.classList.remove("is-loading");
      }
    };

    /* Rich text toolbar */
    document.querySelectorAll("[data-rich-cmd]").forEach((btn) => {
      btn.addEventListener("click", () => {
        richEditor?.focus();
        document.execCommand(btn.dataset.richCmd, false);
        syncDescriptionFromRich();
        markDirty();
      });
    });

    document.querySelectorAll("[data-rich-md]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const sel = window.getSelection()?.toString() || "Heading";
        if (btn.dataset.richMd === "h2") {
          document.execCommand("insertHTML", false, `<h2>${sel}</h2>`);
        }
        if (btn.dataset.richMd === "link") {
          const url = window.prompt("Enter URL", "https://");
          if (url) document.execCommand("createLink", false, url);
        }
        syncDescriptionFromRich();
        markDirty();
      });
    });

    richEditor?.addEventListener("input", () => {
      syncDescriptionFromRich();
      markDirty();
      validateModule();
    });

    markdownEditor?.addEventListener("input", () => {
      syncDescriptionFromMarkdown();
      markDirty();
      validateModule();
    });

    document.getElementById("preview-tab")?.addEventListener("shown.bs.tab", updateDescriptionMetrics);

    categorySelect?.addEventListener("change", () => {
      loadSubcategories(categorySelect.value, "");
      markDirty();
      validateModule();
    });

    document.getElementById("basicDuplicateCheck")?.addEventListener("click", detectDuplicates);

    [
      ["name", "nameCharCount", 120],
      ["subtitle", "subtitleCharCount", 160],
      ["classification", "productCodeCharCount", 64],
      ["hsn_sac_code", "hsnCharCount", 32],
      ["support_contact", "supportCharCount", 120],
    ].forEach(([id, counterId, max]) => {
      const input = document.getElementById(id);
      input?.addEventListener("input", () => {
        updateCounter(input, counterId, max);
        if (id === "name") updateSlugPreview();
        markDirty();
        validateModule();
      });
    });

    document.getElementById("product_tags")?.addEventListener("input", () => {
      updateTagsCounter();
      markDirty();
    });

    document.getElementById("short_description")?.addEventListener("input", () => {
      updateDescriptionMetrics();
      markDirty();
    });

    form.querySelectorAll("#basicInfoModule input, #basicInfoModule select, #basicInfoModule textarea").forEach((el) => {
      el.addEventListener("blur", () => validateControl(el));
      el.addEventListener("input", () => {
        markDirty();
        validateModule();
      });
    });

    if (seoUrlInput) {
      seoUrlInput.addEventListener("input", () => {
        seoUrlInput.dataset.manualEdit = seoUrlInput.value.trim() ? "1" : "";
        updateSlugPreview();
      });
    }

    /* Bootstrap initial description content */
    if (richEditor && descriptionField?.value) {
      richEditor.innerHTML = markdownToHtml(descriptionField.value);
      if (markdownEditor) markdownEditor.value = descriptionField.value;
    }

    updateCounter(document.getElementById("name"), "nameCharCount", 120);
    updateCounter(document.getElementById("subtitle"), "subtitleCharCount", 160);
    updateCounter(document.getElementById("classification"), "productCodeCharCount", 64);
    updateCounter(document.getElementById("hsn_sac_code"), "hsnCharCount", 32);
    updateCounter(document.getElementById("support_contact"), "supportCharCount", 120);
    updateTagsCounter();
    updateSlugPreview();
    updateDescriptionMetrics();
    validateModule();

    if (categorySelect?.value) {
      loadSubcategories(categorySelect.value, subcategorySelect?.value || "");
    }

    observeGlobalAutosave();

    let duplicateTimer;
    document.getElementById("name")?.addEventListener("input", () => {
      window.clearTimeout(duplicateTimer);
      duplicateTimer = window.setTimeout(detectDuplicates, 1200);
    });
  });
})();
