document.addEventListener("DOMContentLoaded", () => {
  const toast = (message, type = "info") => {
    if (typeof window.showToast === "function") {
      window.showToast(message, type);
      return;
    }
  };

  const legacyWarrantyToggle = document.getElementById("warranty-toggle");
  const legacyWarrantyFields = document.getElementById("warranty-fields");
  const legacyWarrantyStart = document.getElementById("warranty-start");
  const legacyWarrantyPeriod = document.getElementById("warranty-period");
  const legacyWarrantyExpiry = document.getElementById("warranty-expiry");

  const calcExpiry = (startValue, periodValue, unitValue = "months") => {
    if (!startValue || !periodValue) return "";
    const date = new Date(`${startValue}T00:00:00`);
    const amount = Number.parseInt(periodValue, 10);
    if (!Number.isFinite(amount) || amount <= 0) return "";
    if (unitValue === "days") date.setDate(date.getDate() + amount);
    if (unitValue === "months") date.setMonth(date.getMonth() + amount);
    if (unitValue === "years") date.setFullYear(date.getFullYear() + amount);
    return date.toISOString().slice(0, 10);
  };

  if (legacyWarrantyToggle && legacyWarrantyFields) {
    const syncLegacyWarranty = () => {
      legacyWarrantyFields.classList.toggle("d-none", !legacyWarrantyToggle.checked);
      if (legacyWarrantyExpiry && legacyWarrantyStart && legacyWarrantyPeriod) {
        legacyWarrantyExpiry.value = calcExpiry(legacyWarrantyStart.value, legacyWarrantyPeriod.value, "months");
      }
    };
    legacyWarrantyToggle.addEventListener("change", syncLegacyWarranty);
    legacyWarrantyStart?.addEventListener("change", syncLegacyWarranty);
    legacyWarrantyPeriod?.addEventListener("input", syncLegacyWarranty);
    document.getElementById("generate-warranty-card")?.addEventListener("click", () => {
      const name = document.getElementById("name")?.value || "Product";
      const popup = window.open("", "_blank", "width=640,height=800");
      if (!popup) return;
      popup.document.write(`<html><head><title>Warranty Card</title><style>body{font-family:Inter,sans-serif;background:#09111f;color:#fff;padding:32px}.card{max-width:420px;margin:auto;padding:24px;border:1px solid rgba(255,255,255,.12);border-radius:18px;background:#0f1b32}</style></head><body><div class="card"><h1>${name}</h1><p>Warranty card generated from the product studio.</p></div></body></html>`);
      popup.document.close();
    });
    syncLegacyWarranty();
  }

  const studio = document.getElementById("productStudio");
  if (!studio) return;

  const form = document.getElementById("product-form");
  const intentField = document.getElementById("form-intent");
  const autosaveStatus = document.getElementById("autosaveStatus");
  const workflowBadge = document.getElementById("workflowBadge");
  const topbar = document.querySelector(".studio-topbar");
  const previewName = document.getElementById("previewName");
  const previewShortDescription = document.getElementById("previewShortDescription");
  const previewSku = document.getElementById("previewSku");
  const previewPrice = document.getElementById("previewPrice");
  const previewCategory = document.getElementById("previewCategory");
  const previewStock = document.getElementById("previewStock");
  const inlinePreviewName = document.getElementById("inlinePreviewName");
  const inlinePreviewCategory = document.getElementById("inlinePreviewCategory");
  const inlinePreviewSku = document.getElementById("inlinePreviewSku");
  const inlinePreviewStock = document.getElementById("inlinePreviewStock");
  const inlinePreviewPrice = document.getElementById("inlinePreviewPrice");
  const inlinePreviewStatus = document.getElementById("inlinePreviewStatus");
  const inlinePreviewAvatar = document.getElementById("inlinePreviewAvatar");
  const previewStatusBadge = document.getElementById("previewStatusBadge");
  const previewTypeBadge = document.getElementById("previewTypeBadge");
  const previewMediaPane = document.getElementById("previewMediaPane");
  const previewGrid = document.getElementById("previewGrid");
  const stepperItems = [...document.querySelectorAll(".studio-stepper-item")];
  const sectionButtons = [...document.querySelectorAll("[data-section-toggle]")];
  const sections = [...document.querySelectorAll(".studio-section")];
  const physicalSections = [...document.querySelectorAll("[data-physical-only='true']")];
  const saveDraftButtons = [
    document.getElementById("saveDraftButton"),
    document.getElementById("reviewDraftButton"),
    document.getElementById("mobileDraftButton"),
  ].filter(Boolean);
  const publishButton = document.getElementById("publishButton");
  const saveAndNewButton = document.getElementById("saveAndNewButton");
  const mobileSaveNewButton = document.getElementById("mobileSaveNewButton");
  const resetFormButton = document.getElementById("resetFormButton");
  const generateSkuButton = document.getElementById("generateSkuButton");
  const warrantyToggle = document.getElementById("enableWarranty");
  const warrantyFields = document.getElementById("warrantyFields");
  const warrantyPeriod = document.getElementById("warranty_period");
  const warrantyUnit = document.getElementById("warranty_unit");
  const warrantyStart = document.getElementById("warranty_start_date");
  const warrantyExpiry = document.getElementById("warranty_expiry");
  const warrantyProvider = document.getElementById("warranty_provider");
  const readinessValue = document.getElementById("readinessValue");
  const completionRingValue = document.getElementById("completionRingValue");
  const completionChip = document.getElementById("completionChip");
  const seoPulseValue = document.getElementById("seoPulseValue");
  const sideSeoMetric = document.getElementById("sideSeoMetric");
  const profitMarginValue = document.getElementById("profitMarginValue");
  const marginSummary = document.getElementById("marginSummary");
  const sideProfitMetric = document.getElementById("sideProfitMetric");
  const sideRevenueMetric = document.getElementById("sideRevenueMetric");
  const inlineRevenueEstimate = document.getElementById("inlineRevenueEstimate");
  const inlineMarginHealth = document.getElementById("inlineMarginHealth");
  const inlineDiscountPressure = document.getElementById("inlineDiscountPressure");
  const taxInclusiveValue = document.getElementById("taxInclusiveValue");
  const promoRecommendation = document.getElementById("promoRecommendation");
  const inventoryStatusText = document.getElementById("inventoryStatusText");
  const sideStockMetric = document.getElementById("sideStockMetric");
  const sideWarrantyMetric = document.getElementById("sideWarrantyMetric");
  const warrantyStatusText = document.getElementById("warrantyStatusText");
  const warrantyExpiryText = document.getElementById("warrantyExpiryText");
  const warrantyProviderText = document.getElementById("warrantyProviderText");
  const sideMediaCheck = document.getElementById("checkMediaStatus");
  const sideSeoCheck = document.getElementById("checkSeoStatus");
  const sideCopyCheck = document.getElementById("checkCopyStatus");
  const descriptionWordCount = document.getElementById("descriptionWordCount");
  const descriptionCharacterCount = document.getElementById("descriptionCharacterCount");
  const descriptionReadability = document.getElementById("descriptionReadability");
  const mobilePreviewName = document.getElementById("mobilePreviewName");
  const mobilePreviewPrice = document.getElementById("mobilePreviewPrice");
  const mobilePreviewCategory = document.getElementById("mobilePreviewCategory");
  const skuStatus = document.getElementById("skuStatus");
  const titleAssistStatus = document.getElementById("titleAssistStatus");
  const warrantyCoverageTimeline = document.getElementById("warrantyTimelineCoverage");
  const sideStockBars = {
    stock: document.querySelector('[data-chart-bar="stock"] span'),
    buffer: document.querySelector('[data-chart-bar="buffer"] span'),
    reorder: document.querySelector('[data-chart-bar="reorder"] span'),
  };
  const variantMatrix = document.getElementById("variantMatrix");
  const analyticsCard = document.getElementById("analyticsCard");
  const openMobileDrawerButton = document.getElementById("openMobileDrawer");
  const mobileInsightsButton = document.getElementById("mobileInsightsButton");
  const quickDuplicate = document.getElementById("quickDuplicate");
  const quickArchive = document.getElementById("quickArchive");
  const quickExport = document.getElementById("quickExport");
  const quickTemplate = document.getElementById("quickTemplate");
  const optimizeMediaButton = document.getElementById("optimizeMediaButton");
  const clearMediaButton = document.getElementById("clearMediaButton");
  const attachPdfButton = document.getElementById("attachPdfButton");
  const barcodePreview = document.getElementById("barcodePreview");
  const qrPreview = document.getElementById("qrPreview");
  const googleSnippetUrl = document.getElementById("googleSnippetUrl");
  const googleSnippetTitle = document.getElementById("googleSnippetTitle");
  const googleSnippetDescription = document.getElementById("googleSnippetDescription");
  const mediaOptimizationState = document.getElementById("mediaOptimizationState");
  const imagesValidationMessage = document.getElementById("imagesValidationMessage");

  const fileInput = document.getElementById("images");
  const dropzone = document.getElementById("dropzone");
  const dataTransfer = new DataTransfer();

  let isDirty = false;
  let lastSavedSnapshot = "";
  let isSubmitting = false;
  let autosaveTimer = null;
  let skuCheckTimer = null;
  let duplicateCheckTimer = null;

  const numberValue = (id) => {
    const value = Number.parseFloat(document.getElementById(id)?.value || "0");
    return Number.isFinite(value) ? value : 0;
  };

  const getFieldShell = (control) => control.closest(".studio-floating, .studio-search-select, .basic-field-shell, .pricing-field-shell, .inventory-field-shell");

  const ensureValidationMessage = (control) => {
    const shell = getFieldShell(control);
    if (!shell) return null;
    let message = shell.querySelector(".studio-invalid-message");
    if (!message) {
      message = document.createElement("div");
      message.className = "studio-invalid-message";
      message.hidden = true;
      shell.appendChild(message);
    }
    return message;
  };

  const setFieldState = (control, state, messageText = "") => {
    const shell = getFieldShell(control);
    const message = ensureValidationMessage(control);
    const tomWrapper = control.tomselect?.wrapper;
    shell?.classList.remove("is-invalid", "is-valid");
    control.classList.remove("is-invalid", "is-valid");
    tomWrapper?.classList.remove("is-invalid", "is-valid");
    if (message) {
      message.hidden = true;
      message.textContent = messageText;
    }

    if (!state) return;
    shell?.classList.add(`is-${state}`);
    control.classList.add(`is-${state}`);
    tomWrapper?.classList.add(`is-${state}`);
    if (message && state === "invalid" && messageText) {
      message.hidden = false;
    }
  };

  const validateControl = (control) => {
    if (!control) return true;
    const value = control.type === "checkbox" ? control.checked : (control.value || "").trim();
    const label = control.dataset.requiredLabel || control.name || "This field";
    const isRequired = control.hasAttribute("required");
    if (isRequired && !value) {
      setFieldState(control, "invalid", `${label} is required.`);
      return false;
    }
    if (control.type === "number" && value !== "" && Number.isNaN(Number.parseFloat(value))) {
      setFieldState(control, "invalid", `${label} must be numeric.`);
      return false;
    }
    if (value !== "") {
      setFieldState(control, "valid");
    } else {
      setFieldState(control, null);
    }
    return true;
  };

  const validateImages = (showMessage = false) => {
    if (window.VKMediaLibrary?.validate) {
      return window.VKMediaLibrary.validate(showMessage);
    }
    const hasImages = dataTransfer.files.length > 0;
    dropzone?.classList.remove("is-invalid", "is-valid");
    if (imagesValidationMessage) imagesValidationMessage.hidden = true;
    if (!hasImages && showMessage) {
      dropzone?.classList.add("is-invalid");
      if (imagesValidationMessage) imagesValidationMessage.hidden = false;
      return false;
    }
    if (hasImages) dropzone?.classList.add("is-valid");
    return hasImages;
  };

  const validatePublishForm = () => {
    const requiredIds = ["name", "sku", "category_id", "brand_id", "cost_price", "selling_price", "opening_stock", "unit_type", "description", "status"];
    const valid = requiredIds.every((id) => validateControl(document.getElementById(id)));
    const imageValid = validateImages(true);
    return valid && imageValid;
  };

  const syncFloatingFields = () => {
    [...form.querySelectorAll(".studio-floating")].forEach((field) => {
      if (field.classList.contains("has-enhanced-select")) return;
      const control = field.querySelector(".form-control, .form-select, textarea");
      if (!control) return;
      const hasValue = control.tagName === "SELECT" ? Boolean(control.value) : Boolean(control.value?.trim());
      field.classList.toggle("is-active", hasValue || document.activeElement === control);
    });
  };

  const initializeEnhancedSelects = () => {
    if (typeof window.TomSelect !== "function") return;
    const selects = [...form.querySelectorAll("select.form-select")];

    selects.forEach((select) => {
      if (select.tomselect) return;

      const floatingParent = select.closest(".studio-floating");
      const searchParent = select.closest(".studio-search-select");
      const firstOption = select.options[0];
      const hasEmptyOption = firstOption && firstOption.value === "";
      const placeholder = select.dataset.placeholder || (hasEmptyOption ? firstOption.textContent.trim() : "");
      const maxOptions = Number.parseInt(select.dataset.maxOptions || "250", 10);
      const updateFloatingState = () => {
        if (!floatingParent) return;
        floatingParent.classList.toggle("is-active", Boolean(select.value));
      };

      if (floatingParent) floatingParent.classList.add("has-enhanced-select");
      if (searchParent) searchParent.classList.add("is-enhanced");

      const instance = new window.TomSelect(select, {
        plugins: hasEmptyOption ? ["clear_button"] : [],
        create: false,
        allowEmptyOption: hasEmptyOption,
        placeholder,
        searchField: ["text"],
        maxOptions,
        dropdownDirection: "auto",
        dropdownParent: "body",
        copyClassesToDropdown: false,
        render: {
          no_results(data, escape) {
            return `<div class="no-results">No results for "${escape(data.input)}"</div>`;
          },
        },
        onInitialize() {
          instance.wrapper.classList.add("form-select");
          instance.wrapper.setAttribute("aria-label", select.getAttribute("aria-label") || select.name || "Select");
          updateFloatingState();
        },
      });

      instance.on("change", () => {
        updateFloatingState();
        validateControl(select);
        syncAll();
        if (simpleSnapshot() !== lastSavedSnapshot) setDirty(true);
      });

      instance.on("focus", () => {
        floatingParent?.classList.add("is-active");
      });

      instance.on("blur", () => {
        updateFloatingState();
      });

      instance.on("dropdown_open", () => {
        const dropdown = instance.dropdown;
        if (!dropdown) return;
        const rect = dropdown.getBoundingClientRect();
        dropdown.style.minWidth = `${instance.wrapper.getBoundingClientRect().width}px`;
        if (rect.right > window.innerWidth - 12) {
          dropdown.style.left = "auto";
          dropdown.style.right = "0";
        } else {
          dropdown.style.left = "";
          dropdown.style.right = "";
        }
      });
    });
  };

  const formatMoney = (amount) => {
    if (typeof formatCurrency === "function") {
      return formatCurrency(amount);
    }
    let n = Number(amount);
    if (!Number.isFinite(n)) n = 0;
    const fixed = n.toFixed(2);
    const parts = fixed.split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return `Rs. ${parts.join(".")}`;
  };

  const simpleSnapshot = () => {
    const formData = new FormData(form);
    formData.delete("images[]");
    formData.delete("warranty_document");
    return JSON.stringify([...formData.entries()]);
  };

  const setDirty = (value) => {
    isDirty = value;
    workflowBadge.textContent = value ? "Workflow: Unsaved changes" : "Workflow: Draft";
    workflowBadge.className = `studio-badge ${value ? "studio-badge-dark" : "studio-badge-info"}`;
  };

  const activeStepButton = (stepKey) => stepperItems.find((item) => item.dataset.stepKey === stepKey);

  const togglePhysicalSections = () => {
    const type = document.getElementById("product_type")?.value || "simple";
    const isDigitalChecked = document.getElementById("is_digital")?.checked;
    const requiresShipping = document.getElementById("requires_shipping")?.checked;
    const digitalMode = ["virtual", "downloadable"].includes(type) || isDigitalChecked || !requiresShipping;

    physicalSections.forEach((section) => {
      section.classList.toggle("is-context-hidden", digitalMode);
    });

    const inventoryStep = activeStepButton("inventory");
    const shippingStep = activeStepButton("shipping");
    [inventoryStep, shippingStep].forEach((step) => {
      if (!step) return;
      step.disabled = digitalMode;
      step.classList.toggle("opacity-50", digitalMode);
      step.setAttribute("aria-disabled", digitalMode ? "true" : "false");
    });
  };

  const buildPayload = (intent, includeFiles = false) => {
    const formData = new FormData(form);
    formData.set("intent", intent);
    if (!includeFiles) {
      formData.delete("images[]");
      formData.delete("warranty_document");
    }
    return formData;
  };

  const updatePreviewMedia = (file) => {
    const oldImage = previewMediaPane.querySelector("img");
    if (oldImage) oldImage.remove();
    const fallback = document.getElementById("previewMediaFallback");
    if (!file) {
      if (!fallback) {
        const wrapper = document.createElement("div");
        wrapper.id = "previewMediaFallback";
        wrapper.innerHTML = "<i class='bi bi-image'></i>";
        previewMediaPane.appendChild(wrapper);
      }
      return;
    }
    fallback?.remove();
    const image = document.createElement("img");
    image.src = URL.createObjectURL(file);
    image.alt = file.name;
    previewMediaPane.appendChild(image);
  };

  const renderFiles = () => {
    if (window.VKMediaLibrary?.render) {
      window.VKMediaLibrary.render();
      const count = window.VKMediaLibrary.getItems?.().length || 0;
      sideMediaCheck.textContent = count ? `${count} media item(s) ready` : "Media not uploaded yet";
      return;
    }

    previewGrid.innerHTML = "";
    const files = [...dataTransfer.files];
    if (fileInput) fileInput.files = dataTransfer.files;
    sideMediaCheck.textContent = files.length ? `${files.length} media item(s) ready` : "Media not uploaded yet";
    updatePreviewMedia(files[0] || null);
    validateImages(false);

    files.forEach((file, index) => {
      const card = document.createElement("article");
      card.className = "studio-preview-card";
      const mediaMarkup = file.type.startsWith("video/")
        ? `<video src="${URL.createObjectURL(file)}" muted playsinline></video>`
        : `<img src="${URL.createObjectURL(file)}" alt="${file.name}">`;
      const percent = Math.min(100, 35 + index * 18);

      card.innerHTML = `
        <div class="studio-preview-card-media">${mediaMarkup}</div>
        <div class="studio-preview-card-body">
          <strong>${file.name}</strong>
          <div class="studio-progress"><span style="width:${percent}%"></span></div>
          <div class="studio-preview-actions">
            <button type="button" data-action="up">Up</button>
            <button type="button" data-action="down">Down</button>
            <button type="button" data-action="remove">Remove</button>
          </div>
        </div>
      `;

      card.querySelectorAll("button").forEach((button) => {
        button.addEventListener("click", () => {
          const nextFiles = [...dataTransfer.files];
          if (button.dataset.action === "remove") nextFiles.splice(index, 1);
          if (button.dataset.action === "up" && index > 0) [nextFiles[index - 1], nextFiles[index]] = [nextFiles[index], nextFiles[index - 1]];
          if (button.dataset.action === "down" && index < nextFiles.length - 1) [nextFiles[index + 1], nextFiles[index]] = [nextFiles[index], nextFiles[index + 1]];
          dataTransfer.items.clear();
          nextFiles.forEach((item) => dataTransfer.items.add(item));
          renderFiles();
          setDirty(true);
        });
      });

      previewGrid.appendChild(card);
    });
  };

  const addFiles = (fileList) => {
    if (window.VKMediaLibrary?.addFiles) {
      window.VKMediaLibrary.addFiles(fileList);
      return;
    }
    [...fileList].forEach((file) => dataTransfer.items.add(file));
    renderFiles();
    validateImages(false);
    setDirty(true);
  };

  const filterSelectOptions = (input) => {
    const select = document.getElementById(input.dataset.filterTarget);
    if (!select) return;
    const term = input.value.trim().toLowerCase();
    [...select.options].forEach((option, index) => {
      if (index === 0) {
        option.hidden = false;
        return;
      }
      option.hidden = term !== "" && !option.text.toLowerCase().includes(term);
    });
  };

  const updateWarranty = () => {
    const enabled = warrantyToggle?.checked ?? false;
    if (warrantyFields) warrantyFields.classList.toggle("is-disabled", !enabled);
    const expiry = calcExpiry(warrantyStart?.value, warrantyPeriod?.value, warrantyUnit?.value);
    if (warrantyExpiry) warrantyExpiry.value = expiry;
    warrantyStatusText.textContent = enabled ? "Active" : "Inactive";
    warrantyExpiryText.textContent = expiry || "Pending";
    warrantyProviderText.textContent = warrantyProvider?.value || "Unassigned";
    sideWarrantyMetric.textContent = enabled ? (expiry || "Enabled") : "Off";
    if (warrantyCoverageTimeline) {
      const timelineText = expiry ? `Coverage active until ${expiry}` : "Start and expiry dates update automatically";
      warrantyCoverageTimeline.querySelector("span").textContent = timelineText;
      warrantyCoverageTimeline.classList.toggle("active", !!enabled);
    }
  };

  const updateVariantMatrix = () => {
    if (!variantMatrix) return;
    const readList = (id) =>
      (document.getElementById(id)?.value || "")
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
    const colors = readList("variant_colors");
    const sizes = readList("variant_sizes");
    const materials = readList("variant_materials");
    variantMatrix.innerHTML = "";
    if (!colors.length && !sizes.length && !materials.length) return;

    const baseColors = colors.length ? colors : ["Standard"];
    const baseSizes = sizes.length ? sizes : ["Single"];
    const baseMaterials = materials.length ? materials : ["Default"];

    baseColors.slice(0, 4).forEach((color) => {
      baseSizes.slice(0, 3).forEach((size) => {
        baseMaterials.slice(0, 2).forEach((material) => {
          const item = document.createElement("article");
          item.className = "studio-variant-card";
          item.innerHTML = `<strong>${color} / ${size}</strong><span>${material}</span><small class="text-secondary">Auto-generated merchandising variant</small>`;
          variantMatrix.appendChild(item);
        });
      });
    });
  };

  const updateCodePreviews = () => {
    const sku = document.getElementById("sku")?.value.trim() || "SKU-0000";
    const qrValue = document.getElementById("qr_code")?.value.trim() || sku;

    if (barcodePreview && window.JsBarcode) {
      try {
        window.JsBarcode(barcodePreview, sku, {
          lineColor: "#8ec5ff",
          background: "transparent",
          height: 34,
          width: 1.4,
          displayValue: false,
          margin: 0,
        });
      } catch {
        barcodePreview.innerHTML = "";
      }
    }

    if (qrPreview && window.QRCode) {
      qrPreview.innerHTML = "";
      new window.QRCode(qrPreview, {
        text: qrValue,
        width: 76,
        height: 76,
        colorDark: "#dff2ff",
        colorLight: "transparent",
      });
    }
  };

  const updateDescriptionInsights = () => {
    const shortText = document.getElementById("short_description")?.value.trim() || "";
    const longText = document.getElementById("description")?.value.trim() || "";
    const combined = `${shortText} ${longText}`.trim();
    const words = combined ? combined.split(/\s+/).length : 0;
    const chars = combined.length;
    descriptionWordCount.textContent = `${words}`;
    descriptionCharacterCount.textContent = `${chars}`;
    descriptionReadability.textContent = words >= 80 ? "Strong" : words >= 35 ? "Good" : "Needs work";
  };

  const updateMetrics = () => {
    const cost = numberValue("cost_price");
    const price = numberValue("selling_price");
    const tax = numberValue("tax_rate") + numberValue("vat_gst");
    const opening = numberValue("opening_stock");
    const current = numberValue("current_stock") || opening;
    const minimum = numberValue("minimum_stock");
    const reorder = numberValue("reorder_level");
    const promo = numberValue("promotional_price");
    const discountType = document.getElementById("discount_type")?.value || "none";
    const discountVal = numberValue("discount_value");
    let effective = price;
    if (discountType === "percentage") effective = price * (1 - discountVal / 100);
    else if (discountType === "fixed") effective = Math.max(0, price - discountVal);
    if (promo > 0 && promo < effective) effective = promo;

    const margin = price > 0 ? ((price - cost) / price) * 100 : 0;
    const profit = effective - cost;
    const taxInclusive = effective + effective * (tax / 100);
    const estimatedRevenue = effective * opening;
    const recommended = cost > 0 ? cost / (1 - 0.35) : price;

    document.getElementById("profit_margin").value = margin.toFixed(2);
    profitMarginValue.textContent = `${margin.toFixed(1)}%`;
    marginSummary.textContent = `${margin.toFixed(1)}%`;
    sideProfitMetric.textContent = `${margin.toFixed(1)}%`;
    sideRevenueMetric.textContent = formatMoney(estimatedRevenue || 0);
    inlineRevenueEstimate.textContent = formatMoney(estimatedRevenue || 0);
    inlineMarginHealth.textContent = margin >= 35 ? "High" : margin >= 20 ? "Healthy" : "Thin";
    inlineDiscountPressure.textContent = promo > 0 ? "Active promo" : "Low";
    previewPrice.textContent = formatMoney(price || 0);
    mobilePreviewPrice.textContent = formatMoney(price || 0);
    inlinePreviewPrice.textContent = formatMoney(price || 0);
    taxInclusiveValue.textContent = formatMoney(taxInclusive || 0);
    promoRecommendation.textContent = promo > 0 && promo < price ? `Launch at ${formatMoney(promo)}` : "No recommendation";

    const liveProfit = document.getElementById("liveProfitValue");
    if (liveProfit) liveProfit.textContent = formatMoney(profit);
    const recommendedNode = document.getElementById("recommendedSellingPrice");
    if (recommendedNode) recommendedNode.textContent = formatMoney(recommended);
    const effectiveNode = document.getElementById("pricingEffectivePrice");
    if (effectiveNode) effectiveNode.textContent = formatMoney(effective);

    const status = current <= minimum ? "Critical" : current <= reorder ? "Monitor" : "Healthy";
    inventoryStatusText.textContent = status;
    sideStockMetric.textContent = status;
    previewStock.textContent = `Stock ${status}`;
    inlinePreviewStock.textContent = status;
    sideStockBars.stock.textContent = `Stock ${current}`;
    sideStockBars.stock.style.width = `${Math.min(100, Math.max(12, current))}%`;
    sideStockBars.buffer.textContent = `Minimum ${minimum}`;
    sideStockBars.buffer.style.width = `${Math.min(100, Math.max(12, minimum))}%`;
    sideStockBars.reorder.textContent = `Reorder ${reorder}`;
    sideStockBars.reorder.style.width = `${Math.min(100, Math.max(12, reorder))}%`;
  };

  const updateSeoScore = () => {
    const name = document.getElementById("name")?.value.trim() || "";
    const slug = document.getElementById("seo_url")?.value.trim() || "";
    const metaTitle = document.getElementById("meta_title")?.value.trim() || "";
    const metaDescription = document.getElementById("meta_description")?.value.trim() || "";
    const tags = document.getElementById("product_tags")?.value.trim() || "";

    let score = 0;
    if (name.length >= 8) score += 22;
    if (slug.length >= 8) score += 18;
    if (metaTitle.length >= 25) score += 20;
    if (metaDescription.length >= 80) score += 24;
    if (tags.length >= 4) score += 16;

    seoPulseValue.textContent = `${score}`;
    sideSeoMetric.textContent = `${score}`;
    sideSeoCheck.textContent = score >= 70 ? "SEO structure is strong" : "SEO needs attention";
    googleSnippetUrl.textContent = `https://example.com/${slug || "product-slug"}`;
    googleSnippetTitle.textContent = metaTitle || name || "SEO title preview";
    googleSnippetDescription.textContent = metaDescription || "Meta description preview appears here as the user types content.";
    return score;
  };

  const updatePreview = () => {
    const name = document.getElementById("name")?.value.trim() || "Untitled product";
    const sku = document.getElementById("sku")?.value.trim() || "SKU pending";
    const shortDescription = document.getElementById("short_description")?.value.trim() || "Add a short summary to see your listing narrative here.";
    const status = document.getElementById("status")?.value || "draft";
    const type = document.getElementById("product_type")?.selectedOptions[0]?.text || "Simple";
    const category = document.getElementById("category_id")?.selectedOptions[0]?.text || "Category pending";
    const firstLetter = (name || "P").charAt(0).toUpperCase();

    previewName.textContent = name;
    mobilePreviewName.textContent = name;
    previewSku.textContent = sku;
    previewShortDescription.textContent = shortDescription;
    previewCategory.textContent = category;
    mobilePreviewCategory.textContent = category;
    inlinePreviewName.textContent = name;
    inlinePreviewCategory.textContent = category;
    inlinePreviewSku.textContent = sku;
    inlinePreviewStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    inlinePreviewAvatar.textContent = firstLetter;
    previewStatusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    previewTypeBadge.textContent = type;
    sideCopyCheck.textContent = shortDescription.length >= 60 ? "Description quality looks strong" : "Description pending refinement";
    titleAssistStatus.textContent = name.length >= 8 ? "Title strength looks strong and ready for search." : "Use a clearer title with product family and differentiator.";
    titleAssistStatus.className = `basic-inline-status studio-inline-status ${name.length >= 8 ? "is-success" : "is-warning"}`;
  };

  const updateCompletion = () => {
    const checks = [
      !!document.getElementById("name")?.value.trim(),
      !!document.getElementById("sku")?.value.trim(),
      !!document.getElementById("category_id")?.value,
      !!document.getElementById("brand_id")?.value,
      numberValue("selling_price") > 0,
      document.getElementById("short_description")?.value.trim().length > 20,
      document.getElementById("description")?.value.trim().length > 60,
      document.getElementById("meta_title")?.value.trim().length > 20,
      document.getElementById("meta_description")?.value.trim().length > 70,
      dataTransfer.files.length > 0,
    ];
    const percent = Math.round((checks.filter(Boolean).length / checks.length) * 100);
    readinessValue.textContent = `${percent}%`;
    completionRingValue.textContent = `${percent}%`;
    completionChip.textContent = `${percent}%`;
    document.querySelector(".studio-ring")?.style.setProperty("--progress", String(percent));
    sideMediaCheck.textContent = dataTransfer.files.length > 0 ? `${dataTransfer.files.length} media item(s) ready` : "Media not uploaded yet";
  };

  const stepRules = {
    basic: () => !!document.getElementById("name")?.value.trim() && !!document.getElementById("sku")?.value.trim(),
    pricing: () => numberValue("selling_price") > 0 && numberValue("cost_price") >= 0,
    inventory: () => document.querySelector("#section-inventory")?.classList.contains("is-context-hidden") || !!document.getElementById("warehouse_id")?.value || numberValue("opening_stock") >= 0,
    warranty: () => !warrantyToggle?.checked || !!warrantyProvider?.value.trim(),
    variants: () => true,
    media: () => (window.VKMediaLibrary?.getItems?.().length || dataTransfer.files.length) > 0,
    shipping: () => document.querySelector("#section-shipping")?.classList.contains("is-context-hidden") || !!document.getElementById("delivery_sla")?.value.trim(),
    seo: () => document.getElementById("meta_title")?.value.trim().length > 20 && document.getElementById("meta_description")?.value.trim().length > 70,
    review: () => true,
  };

  const updateStepperStates = () => {
    stepperItems.forEach((item) => {
      const complete = stepRules[item.dataset.stepKey]?.();
      const isActive = item.classList.contains("active");
      item.classList.toggle("is-complete", !!complete && !isActive);
      item.classList.toggle("is-warning", !complete && !isActive);
      item.setAttribute("aria-current", isActive ? "step" : "false");
    });
  };

  const validateSku = async () => {
    const sku = document.getElementById("sku")?.value.trim() || "";
    if (!sku) {
      skuStatus.textContent = "SKU uniqueness will be checked automatically.";
      skuStatus.className = "studio-inline-status";
      return;
    }
    try {
      const payload = new FormData();
      payload.set("intent", "check_sku");
      payload.set("sku", sku);
      const response = await fetch(studio.dataset.autosaveUrl, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        body: payload,
      });
      const data = await response.json();
      if (data.exists) {
        skuStatus.textContent = `Duplicate SKU warning: already used by ${data.product?.name || "another product"}.`;
        skuStatus.className = "studio-inline-status is-danger";
      } else {
        skuStatus.textContent = "SKU is available.";
        skuStatus.className = "studio-inline-status is-success";
      }
    } catch {
      skuStatus.textContent = "SKU check unavailable right now.";
      skuStatus.className = "studio-inline-status is-warning";
    }
  };

  const detectDuplicates = async (silent = true) => {
    const name = document.getElementById("name")?.value.trim() || "";
    const sku = document.getElementById("sku")?.value.trim() || "";
    if (!name && !sku) return;
    try {
      const payload = new FormData();
      payload.set("intent", "detect_duplicate");
      payload.set("name", name);
      payload.set("sku", sku);
      const response = await fetch(studio.dataset.autosaveUrl, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        body: payload,
      });
      const data = await response.json();
      if (!silent) {
        if (data.matches?.length) {
          toast(`Potential duplicates found: ${data.matches.map((item) => item.name).join(", ")}`, "warning");
        } else {
          toast("No close duplicate found across current products.", "success");
        }
      }
    } catch {
      if (!silent) toast("Duplicate detection is unavailable right now.", "warning");
    }
  };

  const syncAll = () => {
    togglePhysicalSections();
    updatePreview();
    updateMetrics();
    updateWarranty();
    updateVariantMatrix();
    updateSeoScore();
    updateDescriptionInsights();
    updateCodePreviews();
    updateCompletion();
    updateStepperStates();
  };

  const runAiAction = (action) => {
    const name = document.getElementById("name")?.value.trim() || "product";
    const category = document.getElementById("category_id")?.selectedOptions[0]?.text || "catalog";
    const price = numberValue("selling_price");
    if (action === "description") {
      const value = `${name} is positioned as a premium ${category.toLowerCase()} solution with enterprise-grade reliability, clean usability, and a launch-ready merchandising story.`;
      document.getElementById("short_description").value = value;
      document.getElementById("description").value = `${value}\n\nHighlights:\n- Optimized for fast onboarding\n- Built for commercial use cases\n- Strong support and lifecycle readiness`;
      toast("AI description generated.", "success");
    }
    if (action === "tags") {
      document.getElementById("product_tags").value = `${name.split(" ")[0].toLowerCase()}, premium, enterprise, ${category.toLowerCase()}`;
      toast("Suggested tags added.", "success");
    }
    if (action === "seo") {
      const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
      document.getElementById("seo_url").value = slug;
      document.getElementById("meta_title").value = `${name} | Premium ${category}`;
      document.getElementById("meta_description").value = `Explore ${name}, a premium ${category.toLowerCase()} product designed for fast operations, reliable performance, and enterprise-ready lifecycle management.`;
      toast("SEO fields generated.", "success");
    }
    if (action === "price") {
      const recommendation = price > 0 ? (price * 1.08) : 0;
      document.getElementById("promotional_price").value = recommendation.toFixed(2);
      toast(`Price recommendation applied: ${formatMoney(recommendation)}`, "success");
    }
    if (action === "category") {
      toast("Suggested category: Electronics > Smart Devices", "info");
    }
    if (action === "duplicate") {
      detectDuplicates(false);
      return;
    }
    syncAll();
    setDirty(true);
  };

  const saveDraft = async (mode = "draft") => {
    if (isSubmitting) return;
    autosaveStatus.textContent = mode === "autosave" ? "Auto-saving..." : "Saving draft...";
    autosaveStatus.className = "studio-badge studio-badge-dark";

    try {
      const response = await fetch(studio.dataset.autosaveUrl, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        body: buildPayload(mode, false),
      });
      const data = await response.json();
      if (!response.ok || data.error) throw new Error(data.error || "Unable to save draft");
      lastSavedSnapshot = simpleSnapshot();
      setDirty(false);
      autosaveStatus.textContent = `Draft synced ${new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}`;
      autosaveStatus.className = "studio-badge studio-badge-success";
      if (mode !== "autosave") toast("Draft saved.", "success");
      document.dispatchEvent(new CustomEvent("vk:draft-saved", { detail: { mode } }));
    } catch (error) {
      autosaveStatus.textContent = "Draft sync failed";
      autosaveStatus.className = "studio-badge studio-badge-dark";
      toast(error.message || "Draft save failed.", "danger");
    }
  };

  const publish = async (intent = "publish") => {
    if (isSubmitting) return;
    isSubmitting = true;
    publishButton.disabled = true;
    if (saveAndNewButton) saveAndNewButton.disabled = true;
    if (mobileSaveNewButton) mobileSaveNewButton.disabled = true;
    const publishCopy = intent === "publish_new" ? "Saving & opening new..." : "Saving product...";
    publishButton.innerHTML = "<span class='spinner-border spinner-border-sm'></span> Saving...";
    autosaveStatus.textContent = publishCopy;
    autosaveStatus.className = "studio-badge studio-badge-dark";

    try {
      const response = await fetch(studio.dataset.autosaveUrl, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        body: buildPayload(intent, true),
      });
      const data = await response.json();
      if (!response.ok || data.error) throw new Error(data.error || "Publish failed");
      setDirty(false);
      toast(intent === "publish_new" ? "Product saved. Ready for the next one." : "Product saved.", "success");
      window.location.href = data.redirect || `${studio.dataset.baseUrl}/modules/products/list.php`;
    } catch (error) {
      toast(error.message || "Save failed.", "danger");
      autosaveStatus.textContent = "Save failed";
      autosaveStatus.className = "studio-badge studio-badge-dark";
    } finally {
      isSubmitting = false;
      publishButton.disabled = false;
      if (saveAndNewButton) saveAndNewButton.disabled = false;
      if (mobileSaveNewButton) mobileSaveNewButton.disabled = false;
      publishButton.innerHTML = "<i class='bi bi-rocket-takeoff'></i> Save Product";
    }
  };

  sectionButtons.forEach((button) => {
    const body = document.getElementById(button.dataset.sectionToggle);
    if (!body) return;
    const shouldOpen = button.dataset.sectionToggle === "section-basic-body";
    button.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    body.hidden = !shouldOpen;
    button.addEventListener("click", () => {
      const expanded = button.getAttribute("aria-expanded") === "true";
      button.setAttribute("aria-expanded", expanded ? "false" : "true");
      body.hidden = expanded;
    });
  });

  stepperItems.forEach((item) => {
    item.addEventListener("click", () => {
      if (item.disabled) return;
      const target = document.getElementById(item.dataset.stepTarget);
      if (!target) return;
      const toggleButton = target.querySelector(".studio-section-toggle");
      const body = toggleButton ? document.getElementById(toggleButton.dataset.sectionToggle) : null;
      if (toggleButton && body?.hidden) {
        toggleButton.setAttribute("aria-expanded", "true");
        body.hidden = false;
      }
      target.scrollIntoView({ behavior: "smooth", block: "start" });
      stepperItems.forEach((step) => step.classList.toggle("active", step === item));
    });
  });

  const io = new IntersectionObserver(
    (entries) => {
      const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (!visible) return;
      stepperItems.forEach((step) => step.classList.toggle("active", step.dataset.stepTarget === visible.target.id));
    },
    { threshold: 0.35 }
  );

  document.querySelectorAll(".studio-section").forEach((section) => io.observe(section));

  document.querySelectorAll(".studio-filter-input").forEach((input) => {
    input.addEventListener("input", () => filterSelectOptions(input));
  });

  document.getElementById("sku")?.addEventListener("input", () => {
    window.clearTimeout(skuCheckTimer);
    skuCheckTimer = window.setTimeout(validateSku, 450);
  });

  document.getElementById("name")?.addEventListener("input", () => {
    window.clearTimeout(duplicateCheckTimer);
    duplicateCheckTimer = window.setTimeout(() => detectDuplicates(true), 900);
  });

  saveDraftButtons.forEach((button) => button.addEventListener("click", () => saveDraft("draft")));
  saveAndNewButton?.addEventListener("click", async () => {
    if (!validatePublishForm()) {
      toast("Please complete the required product fields before saving.", "warning");
      return;
    }
    await publish("publish_new");
  });
  mobileSaveNewButton?.addEventListener("click", async () => {
    if (!validatePublishForm()) {
      toast("Please complete the required product fields before saving.", "warning");
      return;
    }
    await publish("publish_new");
  });
  resetFormButton?.addEventListener("click", () => {
    const okay = window.confirm("Reset this product form? Unsaved changes will be lost.");
    if (!okay) return;
    form.reset();
    dataTransfer.items.clear();
    renderFiles();
    initializeEnhancedSelects();
    syncFloatingFields();
    syncAll();
    setDirty(false);
    toast("Form reset.", "info");
  });
  generateSkuButton?.addEventListener("click", () => {
    const name = document.getElementById("name")?.value.trim() || "product";
    const prefix = name
      .replace(/[^a-z0-9 ]/gi, " ")
      .trim()
      .split(/\s+/)
      .slice(0, 3)
      .map((part) => part.slice(0, 3).toUpperCase())
      .join("-");
    const suffix = String(Date.now()).slice(-5);
    document.getElementById("sku").value = `${prefix || "PRD"}-${suffix}`;
    validateSku();
    syncFloatingFields();
    syncAll();
    setDirty(true);
    toast("SKU generated.", "success");
  });
  document.getElementById("loadSampleData")?.addEventListener("click", () => {
    document.getElementById("name").value = "VK Edge Secure Hub";
    document.getElementById("sku").value = "VK-EDGE-001";
    document.getElementById("short_description").value = "Enterprise-ready edge security appliance for distributed branch operations.";
    document.getElementById("description").value = "The VK Edge Secure Hub unifies surveillance, networking, and remote observability into one deployable enterprise kit for modern distributed operations.";
    document.getElementById("selling_price").value = "2499.00";
    document.getElementById("cost_price").value = "1640.00";
    document.getElementById("opening_stock").value = "42";
    document.getElementById("minimum_stock").value = "8";
    document.getElementById("reorder_level").value = "15";
    document.getElementById("variant_colors").value = "Matte Black, Arctic Silver";
    document.getElementById("variant_sizes").value = "128GB, 256GB";
    document.getElementById("variant_materials").value = "Aluminum";
    runAiAction("seo");
    updateVariantMatrix();
    syncAll();
    setDirty(true);
    toast("Premium sample loaded.", "success");
  });

  document.querySelectorAll("[data-ai-action]").forEach((button) => {
    button.addEventListener("click", () => runAiAction(button.dataset.aiAction));
  });
  document.getElementById("aiDescriptionButton")?.addEventListener("click", () => runAiAction("description"));
  document.getElementById("aiTagsButton")?.addEventListener("click", () => runAiAction("tags"));
  document.getElementById("aiSeoButton")?.addEventListener("click", () => runAiAction("seo"));
  document.getElementById("duplicateAssistant")?.addEventListener("click", () => runAiAction("duplicate"));
  quickDuplicate?.addEventListener("click", () => runAiAction("duplicate"));
  quickArchive?.addEventListener("click", () => {
    document.getElementById("status").value = "archived";
    syncAll();
    setDirty(true);
    toast("Draft marked as archived.", "info");
  });
  quickExport?.addEventListener("click", () => {
    const payload = {
      name: document.getElementById("name")?.value || "",
      sku: document.getElementById("sku")?.value || "",
      description: document.getElementById("short_description")?.value || "",
      price: document.getElementById("selling_price")?.value || "",
    };
    navigator.clipboard?.writeText(JSON.stringify(payload, null, 2));
    toast("Product brief copied to clipboard.", "success");
  });
  quickTemplate?.addEventListener("click", () => {
    saveDraft("draft");
    toast("Draft saved as reusable template snapshot.", "success");
  });
  optimizeMediaButton?.addEventListener("click", () => {
    if (window.VKMediaLibrary) return;
    if (mediaOptimizationState) mediaOptimizationState.textContent = "Optimized";
    toast("Media optimization queued for uploaded files.", "success");
  });
  attachPdfButton?.addEventListener("click", () => {
    if (window.VKMediaLibrary) return;
    toast("PDF attachment support is ready in the warranty upload center.", "info");
  });
  clearMediaButton?.addEventListener("click", () => {
    if (window.VKMediaLibrary) return;
    dataTransfer.items.clear();
    renderFiles();
    setDirty(true);
    toast("Media library cleared.", "info");
  });
  document.getElementById("generateVariantsButton")?.addEventListener("click", updateVariantMatrix);
  document.getElementById("refreshPreview")?.addEventListener("click", syncAll);
  document.getElementById("previewTrigger")?.addEventListener("click", () => {
    document.querySelector(".studio-sidebar-card")?.scrollIntoView({ behavior: "smooth", block: "start" });
    toast("Live preview refreshed.", "info");
  });

  document.getElementById("generateWarrantyCard")?.addEventListener("click", () => {
    const popup = window.open("", "_blank", "width=760,height=900");
    if (!popup) return;
    popup.document.write("<html><head><title>Warranty Card</title><style>body{font-family:Manrope,Arial,sans-serif;background:#07111f;color:#fff;padding:32px}.card{max-width:520px;margin:auto;background:#0d1b33;border-radius:24px;border:1px solid rgba(255,255,255,.12);padding:28px}.pill{display:inline-block;padding:8px 12px;border-radius:999px;background:rgba(60,224,255,.12);border:1px solid rgba(60,224,255,.3);margin-bottom:16px}</style></head><body><div class='card'><span class='pill'>Warranty Ready</span><h1 id='cardName'></h1><p id='cardMeta'></p><div id='qr'></div></div><script>document.getElementById('cardName').textContent = opener.document.getElementById('name').value || 'Product';document.getElementById('cardMeta').textContent = 'Coverage until ' + (opener.document.getElementById('warranty_expiry').value || 'pending');new opener.QRCode(document.getElementById('qr'), {text: JSON.stringify({product: opener.document.getElementById('name').value, sku: opener.document.getElementById('sku').value, expiry: opener.document.getElementById('warranty_expiry').value}), width: 180, height: 180});</script></body></html>");
    popup.document.close();
  });

  document.querySelectorAll("[data-insert-template='description']").forEach((button) => {
    button.addEventListener("click", () => {
      const textarea = document.getElementById("description");
      if (!textarea.value.trim()) {
        textarea.value = "Overview:\n- Premium build quality\n- Streamlined deployment\n- Enterprise warranty support\n\nUse Cases:\n- Retail operations\n- Branch infrastructure\n- Managed service environments";
      }
      textarea.focus();
      setDirty(true);
      syncAll();
    });
  });

  form.addEventListener("input", () => {
    syncFloatingFields();
    const active = document.activeElement;
    if (active && active.closest("#product-form")) validateControl(active);
    syncAll();
    if (simpleSnapshot() !== lastSavedSnapshot) setDirty(true);
  });

  form.addEventListener("change", () => {
    syncFloatingFields();
    const active = document.activeElement;
    if (active && active.closest("#product-form")) validateControl(active);
    syncAll();
    if (simpleSnapshot() !== lastSavedSnapshot) setDirty(true);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!validatePublishForm()) {
      toast("Please complete the required product fields before saving.", "warning");
      return;
    }
    intentField.value = "publish";
    await publish("publish");
  });

  window.addEventListener("beforeunload", (event) => {
    if (!isDirty || isSubmitting) return;
    event.preventDefault();
    event.returnValue = "";
  });

  document.addEventListener("keydown", async (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
      event.preventDefault();
      await saveDraft("draft");
    }
    if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
      event.preventDefault();
      await publish();
    }
  });

  if (!window.VKMediaLibrary) {
    if (fileInput) {
      fileInput.addEventListener("change", () => addFiles(fileInput.files));
    }

    if (dropzone) {
      ["dragenter", "dragover"].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          dropzone.classList.add("is-dragover");
        });
      });
      ["dragleave", "drop"].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          dropzone.classList.remove("is-dragover");
        });
      });
      dropzone.addEventListener("drop", (event) => {
        if (event.dataTransfer?.files?.length) addFiles(event.dataTransfer.files);
      });
    }
  }

  [warrantyToggle, warrantyStart, warrantyPeriod, warrantyUnit, warrantyProvider].forEach((element) => {
    element?.addEventListener("input", updateWarranty);
    element?.addEventListener("change", updateWarranty);
  });

  ["variant_colors", "variant_sizes", "variant_materials"].forEach((id) => {
    document.getElementById(id)?.addEventListener("input", updateVariantMatrix);
  });

  ["product_type", "is_digital", "requires_shipping", "category_id", "status"].forEach((id) => {
    document.getElementById(id)?.addEventListener("change", syncAll);
  });

  const openMobileDrawer = () => {
    const drawer = document.getElementById("mobileStudioDrawer");
    if (!drawer || !window.bootstrap?.Offcanvas) return;
    window.bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
  };

  openMobileDrawerButton?.addEventListener("click", openMobileDrawer);
  mobileInsightsButton?.addEventListener("click", openMobileDrawer);

  initializeEnhancedSelects();
  syncFloatingFields();
  lastSavedSnapshot = simpleSnapshot();
  syncAll();
  renderFiles();
  validateSku();

  autosaveTimer = window.setInterval(() => {
    if (!isDirty || isSubmitting) return;
    saveDraft("autosave");
  }, 10000);
  window.VKProductStudio = {
    saveDraft,
    isSubmitting: () => isSubmitting,
    validateControl,
    syncAll,
    setDirty,
    runAiAction,
    detectDuplicates: (silent) => detectDuplicates(silent),
  };

  window.__studioAutosaveInterval = autosaveTimer;

  window.addEventListener("scroll", () => {
    topbar?.classList.toggle("is-scrolled", window.scrollY > 12);
  }, { passive: true });

  window.setTimeout(() => analyticsCard?.classList.add("is-loaded"), 900);
});
