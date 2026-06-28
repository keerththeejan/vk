/**
 * Enterprise Pricing Intelligence module controller.
 * Live margin, tax calculator, formatting, validation — Bootstrap 5 only.
 */
(function () {
  "use strict";

  const MONEY_FIELDS = [
    "cost_price", "selling_price", "wholesale_price", "dealer_price",
    "distributor_price", "msrp", "promotional_price", "discount_value",
  ];

  const REQUIRED = ["cost_price", "selling_price"];
  const TARGET_MARGIN = 0.35;

  document.addEventListener("DOMContentLoaded", function () {
    const module = document.getElementById("pricingIntelligenceModule");
    const form = document.getElementById("product-form");
    if (!module || !form) return;

    const validationBadge = document.getElementById("pricingValidationBadge");
    const currencySelect = document.getElementById("currency");
    const taxClassSelect = document.getElementById("tax_class_id");
    const discountType = document.getElementById("discount_type");
    const discountPrefix = document.getElementById("discountValuePrefix");

    const getShell = (el) => el?.closest(".pricing-field-shell, .studio-floating, .studio-search-select");

    const num = (id) => {
      const v = Number.parseFloat(document.getElementById(id)?.value || "0");
      return Number.isFinite(v) ? v : 0;
    };

    const formatter = () => {
      const currency = currencySelect?.value || "USD";
      try {
        return new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: 2 });
      } catch (_) {
        return new Intl.NumberFormat(undefined, { style: "currency", currency: "USD", maximumFractionDigits: 2 });
      }
    };

    const formatMoney = (value) => formatter().format(value || 0);

    const setText = (id, text) => {
      const node = document.getElementById(id);
      if (node) node.textContent = text;
    };

    const ensureMessage = (control) => {
      const shell = getShell(control);
      if (!shell) return null;
      let node = shell.querySelector(".pricing-invalid-message");
      if (!node) {
        node = document.createElement("div");
        node.className = "pricing-invalid-message";
        node.hidden = true;
        shell.appendChild(node);
      }
      return node;
    };

    const setFieldState = (control, state, message = "") => {
      const shell = getShell(control);
      const msg = ensureMessage(control);
      shell?.classList.remove("is-invalid", "is-valid");
      control?.classList.remove("is-invalid", "is-valid");
      if (msg) {
        msg.hidden = true;
        msg.textContent = message;
      }
      if (!state) return;
      shell?.classList.add(`is-${state}`);
      control?.classList.add(`is-${state}`);
      if (msg && state === "invalid" && message) msg.hidden = false;
    };

    const validateControl = (control) => {
      if (!control) return true;
      const value = String(control.value || "").trim();
      const label = control.dataset.requiredLabel || control.name || "Field";
      if (control.hasAttribute("required") && !value) {
        setFieldState(control, "invalid", `${label} is required.`);
        return false;
      }
      if (control.type === "number" && value !== "" && Number.isNaN(Number.parseFloat(value))) {
        setFieldState(control, "invalid", `${label} must be a number.`);
        return false;
      }
      if (value !== "") setFieldState(control, "valid");
      else setFieldState(control, null);
      return true;
    };

    const validateDateRanges = () => {
      let valid = true;
      const pairs = [
        ["price_valid_from", "price_valid_to", "priceValidRangeError"],
        ["promo_start_date", "promo_end_date", "promoRangeError"],
      ];

      pairs.forEach(([fromId, toId, errorId]) => {
        const from = document.getElementById(fromId)?.value || "";
        const to = document.getElementById(toId)?.value || "";
        const errorNode = document.getElementById(errorId);
        if (from && to && to < from) {
          if (errorNode) errorNode.hidden = false;
          setFieldState(document.getElementById(toId), "invalid", "End date must be on or after start date.");
          valid = false;
        } else {
          if (errorNode) errorNode.hidden = true;
        }
      });
      return valid;
    };

    const validateModule = () => {
      let valid = REQUIRED.every((id) => validateControl(document.getElementById(id)));
      valid = validateDateRanges() && valid;

      if (validationBadge) {
        validationBadge.classList.toggle("is-valid", valid);
        validationBadge.classList.toggle("is-invalid", !valid);
        validationBadge.innerHTML = valid
          ? '<i class="bi bi-shield-check"></i> Pricing valid'
          : '<i class="bi bi-exclamation-circle"></i> Fix pricing fields';
      }
      return valid;
    };

    const effectivePrice = (price) => {
      let effective = price;
      const type = discountType?.value || "none";
      const discount = num("discount_value");
      if (type === "percentage") effective = price * (1 - discount / 100);
      else if (type === "fixed") effective = Math.max(0, price - discount);
      const promo = num("promotional_price");
      if (promo > 0 && promo < effective) effective = promo;
      return effective;
    };

    const calculatePricing = () => {
      module.classList.add("is-calculating");

      const fmt = formatter();
      const cost = num("cost_price");
      const price = num("selling_price");
      const taxRate = num("tax_rate") + num("vat_gst");
      const effective = effectivePrice(price);
      const margin = price > 0 ? ((price - cost) / price) * 100 : 0;
      const profit = effective - cost;
      const taxAmount = effective * (taxRate / 100);
      const taxInclusive = effective + taxAmount;
      const opening = num("opening_stock");
      const revenue = effective * opening;
      const recommended = cost > 0 ? cost / (1 - TARGET_MARGIN) : price;
      const promo = num("promotional_price");

      const profitMarginField = document.getElementById("profit_margin");
      if (profitMarginField) profitMarginField.value = margin.toFixed(2);

      setText("profitMarginValue", `${margin.toFixed(1)}%`);
      setText("liveProfitValue", fmt.format(profit));
      setText("taxInclusiveValue", fmt.format(taxInclusive));
      setText("inlineRevenueEstimate", fmt.format(revenue));
      setText("recommendedSellingPrice", fmt.format(recommended));
      setText("pricingEffectivePrice", fmt.format(effective));
      setText("taxCalcBase", fmt.format(effective));
      setText("taxCalcAmount", fmt.format(taxAmount));
      setText("taxCalcRate", `${taxRate.toFixed(2)}%`);

      const marginHealth = margin >= 35 ? "High" : margin >= 20 ? "Healthy" : margin > 0 ? "Thin" : "Neutral";
      setText("inlineMarginHealth", marginHealth);
      setText("inlineDiscountPressure", promo > 0 ? "Active promo" : "Low");

      const promoRec = document.getElementById("promoRecommendation");
      if (promoRec) {
        promoRec.textContent =
          promo > 0 && promo < price ? `Launch at ${fmt.format(promo)}` : recommended > price && cost > 0 ? `Try ${fmt.format(recommended)}` : "—";
      }

      MONEY_FIELDS.forEach((id) => {
        const hint = document.querySelector(`[data-formatted-for="${id}"]`);
        if (hint) hint.textContent = fmt.format(num(id));
      });

      if (window.VKProductStudio?.syncAll) {
        window.VKProductStudio.syncAll();
      } else {
        const marginSummary = document.getElementById("marginSummary");
        const sideProfit = document.getElementById("sideProfitMetric");
        const sideRevenue = document.getElementById("sideRevenueMetric");
        if (marginSummary) marginSummary.textContent = `${margin.toFixed(1)}%`;
        if (sideProfit) sideProfit.textContent = `${margin.toFixed(1)}%`;
        if (sideRevenue) sideRevenue.textContent = fmt.format(revenue);
      }

      module.classList.remove("is-calculating");
      validateModule();
    };

    const formatInput = (input) => {
      if (!input || input.value === "") return;
      const value = Number.parseFloat(input.value);
      if (!Number.isFinite(value)) return;
      input.value = value.toFixed(2);
    };

    const updateCurrencyUI = () => {
      const option = currencySelect?.selectedOptions[0];
      const symbol = option?.dataset.symbol || "$";
      const code = currencySelect?.value || "USD";
      document.querySelectorAll("[data-currency-prefix]").forEach((el) => {
        el.textContent = symbol;
      });
      setText("pricingCurrencyLabel", code);
      calculatePricing();
    };

    const updateDiscountPrefix = () => {
      if (!discountPrefix) return;
      const type = discountType?.value || "none";
      if (type === "percentage") {
        discountPrefix.textContent = "%";
      } else if (type === "fixed") {
        const sym = currencySelect?.selectedOptions[0]?.dataset.symbol || "$";
        discountPrefix.textContent = sym;
      } else {
        discountPrefix.textContent = "—";
      }
    };

    const applyTaxClass = () => {
      const option = taxClassSelect?.selectedOptions[0];
      if (!option || !option.value) return;
      const rate = Number.parseFloat(option.dataset.rate || "0");
      const taxType = option.dataset.taxType || "vat";
      const taxRateInput = document.getElementById("tax_rate");
      const vatInput = document.getElementById("vat_gst");
      if (!Number.isFinite(rate)) return;
      if (taxType === "gst" || taxType === "vat") {
        if (vatInput) vatInput.value = rate.toFixed(2);
      } else if (taxRateInput) {
        taxRateInput.value = rate.toFixed(2);
      }
      calculatePricing();
    };

    MONEY_FIELDS.forEach((id) => {
      const input = document.getElementById(id);
      input?.addEventListener("input", calculatePricing);
      input?.addEventListener("blur", () => {
        formatInput(input);
        calculatePricing();
      });
    });

    ["tax_rate", "vat_gst"].forEach((id) => {
      document.getElementById(id)?.addEventListener("input", calculatePricing);
    });

    currencySelect?.addEventListener("change", () => {
      updateCurrencyUI();
      updateDiscountPrefix();
    });

    discountType?.addEventListener("change", () => {
      updateDiscountPrefix();
      calculatePricing();
    });

    taxClassSelect?.addEventListener("change", applyTaxClass);

    document.getElementById("pricingRecalculateTax")?.addEventListener("click", calculatePricing);

    ["price_valid_from", "price_valid_to", "promo_start_date", "promo_end_date"].forEach((id) => {
      document.getElementById(id)?.addEventListener("change", validateModule);
    });

    REQUIRED.forEach((id) => {
      document.getElementById(id)?.addEventListener("blur", () => validateControl(document.getElementById(id)));
    });

    updateCurrencyUI();
    updateDiscountPrefix();
    if (taxClassSelect?.value) applyTaxClass();
    calculatePricing();
  });
})();
