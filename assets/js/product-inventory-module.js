/**
 * Enterprise Inventory Management module controller.
 * Live stock calculations, progress bars, movement preview — Bootstrap 5 only.
 */
(function () {
  "use strict";

  const STOCK_FIELDS = [
    "opening_stock", "current_stock", "incoming_stock", "reserved_stock",
    "minimum_stock", "reorder_level",
  ];

  document.addEventListener("DOMContentLoaded", function () {
    const module = document.getElementById("inventoryManagementModule");
    if (!module) return;

    const openingInput = document.getElementById("opening_stock");
    const currentInput = document.getElementById("current_stock");
    const historyBody = document.getElementById("inventoryMovementHistory");
    let currentStockManual = Boolean(currentInput?.dataset.manualEdit);

    const num = (id) => {
      const v = Number.parseInt(document.getElementById(id)?.value || "0", 10);
      return Number.isFinite(v) ? Math.max(0, v) : 0;
    };

    const setText = (id, text) => {
      const node = document.getElementById(id);
      if (node) node.textContent = text;
    };

    const setWidth = (id, pct) => {
      const node = document.getElementById(id);
      if (node) node.style.width = `${Math.min(100, Math.max(0, pct))}%`;
    };

    const setPillState = (id, state) => {
      const pill = document.getElementById(id);
      if (!pill) return;
      pill.classList.remove("is-healthy", "is-monitor", "is-critical");
      pill.classList.add(`is-${state}`);
    };

    const formatTime = () =>
      new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });

    const buildMovementRow = (when, type, qty, before, after, ref, badgeClass) => `
      <tr>
        <td>${when}</td>
        <td><span class="inventory-movement-badge ${badgeClass}">${type}</span></td>
        <td>${qty}</td>
        <td>${before}</td>
        <td>${after}</td>
        <td>${ref}</td>
      </tr>`;

    const renderMovementHistory = (opening, current, incoming, reserved, skuType) => {
      if (!historyBody) return;
      const rows = [];

      if (opening > 0) {
        rows.push(buildMovementRow(formatTime(), "IN", `+${opening}`, 0, opening, "Opening stock", "is-in"));
      }
      if (current !== opening && current > 0) {
        rows.push(buildMovementRow(formatTime(), "ADJ", current > opening ? `+${current - opening}` : `${current - opening}`, opening, current, "Current sync", "is-in"));
      }
      if (incoming > 0) {
        rows.push(buildMovementRow(formatTime(), "IN", `+${incoming}`, current, current + incoming, "Incoming shipment", "is-in"));
      }
      if (reserved > 0) {
        rows.push(buildMovementRow(formatTime(), "RESERVE", `-${reserved}`, current, Math.max(0, current - reserved), "Sales / holds", "is-reserve"));
      }
      if (skuType === "batch") {
        const batch = document.getElementById("batch_number")?.value.trim();
        if (batch) rows.push(buildMovementRow(formatTime(), "BATCH", "—", "—", "—", batch, "is-in"));
      }
      if (skuType === "serial") {
        const serial = document.getElementById("serial_number")?.value.trim();
        if (serial) rows.push(buildMovementRow(formatTime(), "SERIAL", "1", "—", "—", serial, "is-in"));
      }

      historyBody.innerHTML =
        rows.length > 0
          ? rows.join("")
          : '<tr><td colspan="6" class="text-center text-muted py-4">Enter stock values to preview movement timeline.</td></tr>';
    };

    const calculateInventory = () => {
      const opening = num("opening_stock");
      const current = num("current_stock");
      const incoming = num("incoming_stock");
      const reserved = num("reserved_stock");
      const minimum = num("minimum_stock");
      const reorder = num("reorder_level");
      const skuType = document.getElementById("stock_keeping_type")?.value || "standard";

      const available = Math.max(0, current - reserved);
      const projected = Math.max(0, current + incoming - reserved);
      const bufferGap = available - minimum;
      const reorderGap = Math.max(0, reorder - available);
      const maxScale = Math.max(reorder, minimum, current, opening, 1);
      const fillRate = reorder > 0 ? Math.min(100, Math.round((available / reorder) * 100)) : 100;

      let health = "Healthy";
      let healthState = "healthy";
      let reorderStatus = "No reorder needed";
      let healthScore = 100;

      if (available <= minimum) {
        health = "Critical";
        healthState = "critical";
        reorderStatus = "Reorder immediately";
        healthScore = 25;
      } else if (available <= reorder) {
        health = "Monitor";
        healthState = "monitor";
        reorderStatus = "Reorder recommended";
        healthScore = 55;
      } else {
        reorderStatus = reorderGap > 0 ? `${reorderGap} units above trigger` : "Stock above reorder";
        healthScore = Math.min(100, 60 + Math.round((available / maxScale) * 40));
      }

      setText("inventoryStatusText", health);
      setText("inventoryReorderStatus", reorderStatus);
      setText("inventoryAvailableStock", String(available));
      setText("inventoryProjectedStock", String(projected));
      setText("inventoryBufferGap", String(bufferGap));
      setText("inventoryReorderGap", String(reorderGap));
      setText("inventoryFillRate", `${fillRate}%`);
      setText("inventoryHealthScore", String(healthScore));

      setPillState("inventoryHealthPill", healthState);
      setPillState("inventoryReorderPill", healthState === "healthy" ? "healthy" : healthState === "monitor" ? "monitor" : "critical");

      setText("inventoryProgressStockLabel", `${current} units`);
      setText("inventoryProgressMinLabel", `${minimum} units`);
      setText("inventoryProgressReorderLabel", `${reorder} units`);

      setWidth("inventoryProgressStock", (current / maxScale) * 100);
      setWidth("inventoryProgressMin", (minimum / maxScale) * 100);
      setWidth("inventoryProgressReorder", (reorder / maxScale) * 100);

      renderMovementHistory(opening, current, incoming, reserved, skuType);

      if (window.VKProductStudio?.syncAll) {
        window.VKProductStudio.syncAll();
      }
    };

    const validateDates = () => {
      const mfg = document.getElementById("manufacturing_date")?.value || "";
      const exp = document.getElementById("expiry_date")?.value || "";
      const errorNode = document.getElementById("inventoryExpiryError");
      const invalid = mfg && exp && exp < mfg;
      if (errorNode) errorNode.hidden = !invalid;
      return !invalid;
    };

    openingInput?.addEventListener("input", () => {
      if (!currentStockManual && currentInput) {
        currentInput.value = openingInput.value;
      }
      calculateInventory();
    });

    currentInput?.addEventListener("input", () => {
      currentStockManual = true;
      if (currentInput) currentInput.dataset.manualEdit = "1";
      calculateInventory();
    });

    STOCK_FIELDS.forEach((id) => {
      if (id === "opening_stock" || id === "current_stock") return;
      document.getElementById(id)?.addEventListener("input", calculateInventory);
    });

    ["stock_keeping_type", "batch_number", "serial_number"].forEach((id) => {
      document.getElementById(id)?.addEventListener("input", calculateInventory);
      document.getElementById(id)?.addEventListener("change", calculateInventory);
    });

    ["manufacturing_date", "expiry_date"].forEach((id) => {
      document.getElementById(id)?.addEventListener("change", () => {
        validateDates();
        calculateInventory();
      });
    });

    document.getElementById("opening_stock")?.addEventListener("blur", () => {
      const el = document.getElementById("opening_stock");
      if (el?.hasAttribute("required") && el.value === "") return;
      el?.classList.toggle("is-valid", num("opening_stock") >= 0);
    });

    calculateInventory();
    validateDates();
  });
})();
