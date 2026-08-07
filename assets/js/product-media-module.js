/**
 * Enterprise Media Library — AJAX upload, gallery, crop, rotate, sort, primary image.
 * Syncs with product-admin.js via window.VKMediaLibrary and shared DOM ids.
 */
(function () {
  "use strict";

  /** @type {{ id: string, file: File, primary: boolean, progress: number, optimized: boolean, stagedUrl: string|null, rotation: number }[]} */
  let items = [];
  let cropTargetId = null;
  let cropRatio = null;

  const uid = () => `m_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;

  const isImage = (file) => file.type.startsWith("image/") && file.type !== "image/gif";
  const isVideo = (file) => file.type.startsWith("video/");
  const isPdf = (file) => file.type === "application/pdf";

  document.addEventListener("DOMContentLoaded", function () {
    const module = document.getElementById("mediaLibraryModule");
    if (!module) return;

    const uploadUrl = module.dataset.uploadUrl || "";
    const fileInput = document.getElementById("images");
    const pdfInput = document.getElementById("mediaPdfInput");
    const dropzone = document.getElementById("dropzone");
    const previewGrid = document.getElementById("previewGrid");
    const galleryEmpty = document.getElementById("mediaGalleryEmpty");
    const dataTransfer = new DataTransfer();

    const setCount = (n) => {
      const node = document.getElementById("mediaImageCount");
      if (node) node.innerHTML = `<i class="bi bi-collection"></i> ${n} file${n === 1 ? "" : "s"}`;
    };

    const setOptimization = (text) => {
      ["mediaOptimizationState", "mediaOptimizationTile"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = id === "mediaOptimizationState" ? `<i class="bi bi-magic"></i> ${text}` : text;
      });
    };

    const syncFileInput = () => {
      dataTransfer.items.clear();
      items.forEach((item) => dataTransfer.items.add(item.file));
      if (fileInput) fileInput.files = dataTransfer.files;
      document.dispatchEvent(new CustomEvent("vk:media-changed", { detail: { count: items.length } }));
    };

    const updateSidebar = () => {
      const primary = items.find((i) => i.primary) || items[0];
      const pane = document.getElementById("previewMediaPane");
      const thumb = document.getElementById("mediaPrimaryThumb");
      const sideCheck = document.getElementById("checkMediaStatus");

      if (sideCheck) {
        sideCheck.textContent = items.length ? `${items.length} media item(s) ready` : "Media not uploaded yet";
      }

      const renderPreview = (container, file) => {
        if (!container) return;
        container.innerHTML = "";
        if (!file) {
          container.innerHTML = "<i class=\"bi bi-image\"></i>";
          return;
        }
        const url = URL.createObjectURL(file);
        if (isVideo(file)) {
          container.innerHTML = `<video src="${url}" muted playsinline></video>`;
        } else if (isPdf(file)) {
          container.innerHTML = `<div class="media-card-pdf"><i class="bi bi-file-earmark-pdf"></i>${file.name}</div>`;
        } else {
          container.innerHTML = `<img src="${url}" alt="${file.name}">`;
        }
      };

      renderPreview(thumb, primary?.file || null);

      if (pane && primary && isImage(primary.file)) {
        pane.innerHTML = "";
        const img = document.createElement("img");
        img.src = URL.createObjectURL(primary.file);
        img.alt = primary.file.name;
        pane.appendChild(img);
        document.getElementById("previewMediaFallback")?.remove();
      }
    };

    const validateImages = (showMessage = false) => {
      const hasImage = items.some((i) => isImage(i.file));
      dropzone?.classList.remove("is-invalid", "is-valid");
      const msg = document.getElementById("imagesValidationMessage");
      if (msg) msg.hidden = true;
      if (!hasImage && showMessage) {
        dropzone?.classList.add("is-invalid");
        if (msg) msg.hidden = false;
        return false;
      }
      if (hasImage) dropzone?.classList.add("is-valid");
      return hasImage || items.length === 0;
    };

    const uploadFileAjax = async (item) => {
      if (!uploadUrl) {
        item.progress = 100;
        return;
      }
      const formData = new FormData();
      formData.append("file", item.file);
      item.progress = 10;

      try {
        const xhr = new XMLHttpRequest();
        await new Promise((resolve, reject) => {
          xhr.upload.addEventListener("progress", (e) => {
            if (e.lengthComputable) item.progress = Math.round((e.loaded / e.total) * 90) + 10;
            renderGallery();
          });
          xhr.addEventListener("load", () => {
            if (xhr.status >= 200 && xhr.status < 300) {
              try {
                const data = JSON.parse(xhr.responseText);
                if (data.ok && data.item) {
                  item.stagedUrl = data.item.url;
                  item.optimized = Boolean(data.item.optimized);
                  item.progress = 100;
                }
              } catch (_) {
                item.progress = 100;
              }
              resolve();
            } else {
              reject(new Error("Upload failed"));
            }
          });
          xhr.addEventListener("error", () => reject(new Error("Network error")));
          xhr.open("POST", uploadUrl);
          xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
          xhr.send(formData);
        });
      } catch (_) {
        item.progress = 100;
      }
    };

    const compressImage = async (file, quality = 0.82, maxEdge = 1920) => {
      if (!isImage(file)) return file;
      const bitmap = await createImageBitmap(file);
      let { width, height } = bitmap;
      const scale = Math.min(1, maxEdge / Math.max(width, height));
      width = Math.round(width * scale);
      height = Math.round(height * scale);
      const canvas = document.createElement("canvas");
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext("2d");
      ctx.drawImage(bitmap, 0, 0, width, height);
      bitmap.close();
      const blob = await new Promise((res) => canvas.toBlob(res, "image/jpeg", quality));
      if (!blob) return file;
      return new File([blob], file.name.replace(/\.\w+$/, ".jpg"), { type: "image/jpeg" });
    };

    const aiOptimizeImage = async (file) => {
      if (!isImage(file)) return file;
      const bitmap = await createImageBitmap(file);
      const canvas = document.createElement("canvas");
      canvas.width = bitmap.width;
      canvas.height = bitmap.height;
      const ctx = canvas.getContext("2d");
      ctx.filter = "contrast(1.08) saturate(1.12) brightness(1.03)";
      ctx.drawImage(bitmap, 0, 0);
      bitmap.close();
      const blob = await new Promise((res) => canvas.toBlob(res, "image/webp", 0.86));
      if (!blob) return file;
      return new File([blob], file.name.replace(/\.\w+$/, ".webp"), { type: "image/webp" });
    };

    const rotateImage = async (file, degrees = 90) => {
      if (!isImage(file)) return file;
      const bitmap = await createImageBitmap(file);
      const canvas = document.createElement("canvas");
      const rad = (degrees * Math.PI) / 180;
      const sin = Math.abs(Math.sin(rad));
      const cos = Math.abs(Math.cos(rad));
      canvas.width = Math.round(bitmap.width * cos + bitmap.height * sin);
      canvas.height = Math.round(bitmap.width * sin + bitmap.height * cos);
      const ctx = canvas.getContext("2d");
      ctx.translate(canvas.width / 2, canvas.height / 2);
      ctx.rotate(rad);
      ctx.drawImage(bitmap, -bitmap.width / 2, -bitmap.height / 2);
      bitmap.close();
      const blob = await new Promise((res) => canvas.toBlob(res, file.type, 0.92));
      if (!blob) return file;
      return new File([blob], file.name, { type: file.type });
    };

    const mediaMarkup = (file) => {
      const url = URL.createObjectURL(file);
      if (isVideo(file)) return `<video src="${url}" muted playsinline></video>`;
      if (isPdf(file)) {
        return `<div class="media-card-pdf"><i class="bi bi-file-earmark-pdf"></i><span>${file.name}</span></div>`;
      }
      return `<img src="${url}" alt="${file.name}">`;
    };

    const renderGallery = () => {
      if (!previewGrid) return;
      previewGrid.innerHTML = "";
      galleryEmpty?.classList.toggle("is-visible", items.length === 0);
      setCount(items.length);

      let totalProgress = 0;
      items.forEach((item, index) => {
        totalProgress += item.progress;
        const col = document.createElement("div");
        col.className = "col-6 col-md-4 col-xl-3";
        col.draggable = true;
        col.dataset.itemId = item.id;

        const typeLabel = isVideo(item.file) ? "video" : isPdf(item.file) ? "pdf" : "image";
        col.innerHTML = `
          <article class="card media-card border-0 h-100 ${item.primary ? "is-primary" : ""}">
            <div class="media-card-media">
              ${item.primary ? '<span class="media-primary-badge"><i class="bi bi-star-fill"></i> Primary</span>' : ""}
              <span class="media-type-badge">${typeLabel}</span>
              ${mediaMarkup(item.file)}
            </div>
            <div class="media-card-body">
              <strong title="${item.file.name}">${item.file.name}</strong>
              <div class="media-card-progress"><span style="width:${item.progress}%"></span></div>
              <div class="media-card-actions">
                <button type="button" data-action="preview" title="Preview"><i class="bi bi-eye"></i></button>
                <button type="button" data-action="primary" title="Set primary"><i class="bi bi-star"></i></button>
                ${isImage(item.file) ? '<button type="button" data-action="crop" title="Crop"><i class="bi bi-crop"></i></button>' : ""}
                ${isImage(item.file) ? '<button type="button" data-action="rotate" title="Rotate"><i class="bi bi-arrow-clockwise"></i></button>' : ""}
                <button type="button" data-action="up" title="Move up"><i class="bi bi-arrow-up"></i></button>
                <button type="button" data-action="down" title="Move down"><i class="bi bi-arrow-down"></i></button>
                <button type="button" data-action="remove" title="Delete"><i class="bi bi-trash"></i></button>
              </div>
            </div>
          </article>`;

        col.addEventListener("dragstart", () => col.classList.add("is-dragging"));
        col.addEventListener("dragend", () => col.classList.remove("is-dragging"));
        col.addEventListener("dragover", (e) => e.preventDefault());
        col.addEventListener("drop", (e) => {
          e.preventDefault();
          const fromId = document.querySelector(".is-dragging")?.dataset.itemId;
          if (!fromId || fromId === item.id) return;
          const fromIdx = items.findIndex((i) => i.id === fromId);
          const toIdx = items.findIndex((i) => i.id === item.id);
          if (fromIdx < 0 || toIdx < 0) return;
          const [moved] = items.splice(fromIdx, 1);
          items.splice(toIdx, 0, moved);
          renderGallery();
          syncFileInput();
        });

        col.querySelectorAll("[data-action]").forEach((btn) => {
          btn.addEventListener("click", async (e) => {
            e.preventDefault();
            e.stopPropagation();
            const action = btn.dataset.action;
            const idx = items.findIndex((i) => i.id === item.id);
            if (idx < 0) return;

            if (action === "remove") {
              items.splice(idx, 1);
              if (items.length && !items.some((i) => i.primary)) items[0].primary = true;
            }
            if (action === "primary") items.forEach((i) => { i.primary = i.id === item.id; });
            if (action === "up" && idx > 0) [items[idx - 1], items[idx]] = [items[idx], items[idx - 1]];
            if (action === "down" && idx < items.length - 1) [items[idx + 1], items[idx]] = [items[idx], items[idx + 1]];
            if (action === "preview") openPreview(item.file);
            if (action === "rotate" && isImage(item.file)) {
              item.file = await rotateImage(item.file);
              item.progress = 80;
              await uploadFileAjax(item);
            }
            if (action === "crop" && isImage(item.file)) openCropModal(item.id);

            renderGallery();
            syncFileInput();
            updateSidebar();
            validateImages(false);
            notifyDirty();
          });
        });

        previewGrid.appendChild(col);
      });

      const overall = document.getElementById("mediaOverallProgress");
      if (overall) {
        const pct = items.length ? Math.round(totalProgress / items.length) : 0;
        overall.style.width = `${pct}%`;
      }

      syncFileInput();
      updateSidebar();
      validateImages(false);
    };

    const notifyDirty = () => {
      if (window.VKProductStudio?.setDirty) window.VKProductStudio.setDirty(true);
    };

    const addFiles = async (fileList) => {
      const incoming = [...fileList];
      for (const file of incoming) {
        const item = {
          id: uid(),
          file,
          primary: items.length === 0,
          progress: 0,
          optimized: false,
          stagedUrl: null,
          rotation: 0,
        };
        items.push(item);
        renderGallery();
        await uploadFileAjax(item);
      }
      renderGallery();
      notifyDirty();
    };

    const openPreview = (file) => {
      const body = document.getElementById("mediaPreviewModalBody");
      const label = document.getElementById("mediaPreviewModalLabel");
      if (!body || !window.bootstrap?.Modal) return;
      if (label) label.textContent = file.name;
      const url = URL.createObjectURL(file);
      if (isVideo(file)) body.innerHTML = `<video src="${url}" controls autoplay class="w-100"></video>`;
      else if (isPdf(file)) body.innerHTML = `<iframe src="${url}" class="w-100" style="min-height:480px;border:0" title="${file.name}"></iframe>`;
      else body.innerHTML = `<img src="${url}" alt="${file.name}">`;
      window.bootstrap.Modal.getOrCreateInstance(document.getElementById("mediaPreviewModal")).show();
    };

    const openCropModal = async (itemId) => {
      cropTargetId = itemId;
      const item = items.find((i) => i.id === itemId);
      const canvas = document.getElementById("mediaCropCanvas");
      if (!item || !canvas || !isImage(item.file)) return;
      const bitmap = await createImageBitmap(item.file);
      canvas.width = bitmap.width;
      canvas.height = bitmap.height;
      canvas.getContext("2d").drawImage(bitmap, 0, 0);
      bitmap.close();
      window.bootstrap?.Modal.getOrCreateInstance(document.getElementById("mediaCropModal"))?.show();
    };

    document.querySelectorAll("[data-crop-ratio]").forEach((btn) => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("[data-crop-ratio]").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        const val = btn.dataset.cropRatio;
        cropRatio = val === "free" ? null : Number.parseFloat(val);
      });
    });

    document.getElementById("mediaCropApply")?.addEventListener("click", async () => {
      const item = items.find((i) => i.id === cropTargetId);
      const canvas = document.getElementById("mediaCropCanvas");
      if (!item || !canvas) return;
      let { width, height } = canvas;
      let sx = 0;
      let sy = 0;
      let sw = width;
      let sh = height;
      if (cropRatio) {
        if (width / height > cropRatio) {
          sw = Math.round(height * cropRatio);
          sx = Math.round((width - sw) / 2);
        } else {
          sh = Math.round(width / cropRatio);
          sy = Math.round((height - sh) / 2);
        }
      } else {
        sw = Math.round(width * 0.85);
        sh = Math.round(height * 0.85);
        sx = Math.round((width - sw) / 2);
        sy = Math.round((height - sh) / 2);
      }
      const out = document.createElement("canvas");
      out.width = sw;
      out.height = sh;
      out.getContext("2d").drawImage(canvas, sx, sy, sw, sh, 0, 0, sw, sh);
      const blob = await new Promise((res) => out.toBlob(res, "image/jpeg", 0.9));
      if (blob) {
        item.file = new File([blob], item.file.name, { type: "image/jpeg" });
        item.progress = 50;
        await uploadFileAjax(item);
      }
      window.bootstrap?.Modal.getInstance(document.getElementById("mediaCropModal"))?.hide();
      renderGallery();
      syncFileInput();
      notifyDirty();
    });

    document.getElementById("mediaBrowseButton")?.addEventListener("click", (e) => {
      e.preventDefault();
      fileInput?.click();
    });

    fileInput?.addEventListener("change", () => {
      if (fileInput.files?.length) addFiles(fileInput.files);
      fileInput.value = "";
    });

    pdfInput?.addEventListener("change", () => {
      if (pdfInput.files?.length) addFiles(pdfInput.files);
      pdfInput.value = "";
    });

    if (dropzone) {
      ["dragenter", "dragover"].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => {
          e.preventDefault();
          dropzone.classList.add("is-dragover");
        });
      });
      ["dragleave", "drop"].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => {
          e.preventDefault();
          dropzone.classList.remove("is-dragover");
        });
      });
      dropzone.addEventListener("drop", (e) => {
        if (e.dataTransfer?.files?.length) addFiles(e.dataTransfer.files);
      });
    }

    document.getElementById("optimizeMediaButton")?.addEventListener("click", async () => {
      setOptimization("Optimizing…");
      for (const item of items) {
        if (isImage(item.file)) {
          item.file = await aiOptimizeImage(item.file);
          item.optimized = true;
          item.progress = 60;
          await uploadFileAjax(item);
        }
      }
      setOptimization("Optimized");
      renderGallery();
      syncFileInput();
      notifyDirty();
    });

    document.getElementById("mediaCompressAll")?.addEventListener("click", async () => {
      setOptimization("Compressing…");
      for (const item of items) {
        if (isImage(item.file)) {
          item.file = await compressImage(item.file);
          item.optimized = true;
          item.progress = 60;
          await uploadFileAjax(item);
        }
      }
      setOptimization("Compressed");
      renderGallery();
      syncFileInput();
      notifyDirty();
    });

    document.getElementById("attachPdfButton")?.addEventListener("click", () => pdfInput?.click());

    document.getElementById("clearMediaButton")?.addEventListener("click", () => {
      items = [];
      renderGallery();
      setOptimization("Pending");
      notifyDirty();
    });

    window.VKMediaLibrary = {
      getItems: () => items,
      getFiles: () => dataTransfer.files,
      render: renderGallery,
      validate: validateImages,
      addFiles,
      clear: () => {
        items = [];
        renderGallery();
      },
    };

    renderGallery();
  });
})();
