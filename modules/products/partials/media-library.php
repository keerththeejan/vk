<?php
/**
 * Enterprise Media Library module (Add Product — Step 4).
 * Preserves #images name="images[]" for backend multipart handling.
 */
declare(strict_types=1);
?>
<section id="section-media" class="studio-card studio-section" data-step-key="media">
    <button type="button" class="studio-section-toggle" data-section-toggle="section-media-body">
        <div>
            <span class="studio-section-kicker">Step 4</span>
            <h2>Media Library</h2>
            <p>Drag-and-drop gallery with AJAX upload, compression, crop, and primary image control.</p>
        </div>
        <span class="studio-section-tools">
            <span class="studio-chip">Visuals</span>
            <i class="bi bi-chevron-down"></i>
        </span>
    </button>

    <div class="studio-section-body" id="section-media-body">
        <div id="mediaLibraryModule" class="media-library-module" data-upload-url="<?= e(base_url('api/product_media_upload.php')) ?>">
            <header class="card media-module-header border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h3 class="media-module-title mb-1"><i class="bi bi-images me-2"></i>Media Library</h3>
                        <p class="media-module-subtitle mb-0">Images, videos, and PDFs with live gallery management.</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="media-count-badge" id="mediaImageCount">
                            <i class="bi bi-collection"></i> 0 files
                        </span>
                        <span class="media-count-badge" id="mediaOptimizationState">
                            <i class="bi bi-magic"></i> Pending
                        </span>
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <div class="progress media-overall-progress" role="progressbar" aria-label="Upload progress">
                        <div class="progress-bar progress-bar-striped" id="mediaOverallProgress" style="width: 0%"></div>
                    </div>
                </div>
            </header>

            <div class="card media-form-card border-0 shadow-sm">
                <div class="card-body">
                    <label class="media-dropzone" id="dropzone" for="images">
                        <input
                            type="file"
                            id="images"
                            name="images[]"
                            multiple
                            accept="image/*,video/*,application/pdf"
                            data-required-label="Product Image Upload"
                            class="visually-hidden"
                        >
                        <input type="file" id="mediaPdfInput" accept="application/pdf" class="visually-hidden" aria-hidden="true">
                        <div class="media-dropzone-inner">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <strong>Drag &amp; drop media here <span class="studio-required">*</span></strong>
                            <span>Multiple images, videos, or PDFs — AJAX upload with progress</span>
                            <button type="button" class="btn btn-sm media-mini-btn mt-2" id="mediaBrowseButton">
                                <i class="bi bi-folder2-open"></i> Browse files
                            </button>
                        </div>
                    </label>
                    <div class="studio-invalid-message" id="imagesValidationMessage" hidden>Upload at least one product image before saving.</div>

                    <div class="media-toolbar d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-sm media-mini-btn" id="optimizeMediaButton">
                            <i class="bi bi-stars"></i> AI Optimize
                        </button>
                        <button type="button" class="btn btn-sm media-mini-btn" id="attachPdfButton">
                            <i class="bi bi-file-earmark-pdf"></i> Add PDF
                        </button>
                        <button type="button" class="btn btn-sm media-mini-btn" id="mediaCompressAll">
                            <i class="bi bi-file-zip"></i> Compress All
                        </button>
                        <button type="button" class="btn btn-sm media-mini-btn" id="clearMediaButton">
                            <i class="bi bi-trash3"></i> Clear Library
                        </button>
                    </div>
                </div>
            </div>

            <div class="card media-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h4 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Gallery</h4>
                    <span class="media-chip">Drag to reorder · First = thumbnail</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 media-gallery" id="previewGrid"></div>
                    <div class="media-gallery-empty text-center text-muted py-5" id="mediaGalleryEmpty">
                        <i class="bi bi-image fs-1 d-block mb-2"></i>
                        No media yet. Drop files above to build your product gallery.
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <article class="card media-form-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <span class="media-stat-label">Primary thumbnail</span>
                            <div class="media-primary-thumb" id="mediaPrimaryThumb">
                                <i class="bi bi-image"></i>
                            </div>
                        </div>
                    </article>
                </div>
                <div class="col-md-8">
                    <article class="card media-form-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="row g-3">
                                <article class="col-sm-4 media-stat-tile">
                                    <span>Thumbnail strategy</span>
                                    <strong>Primary + alternates</strong>
                                </article>
                                <article class="col-sm-4 media-stat-tile">
                                    <span>Optimization</span>
                                    <strong id="mediaOptimizationTile">Pending</strong>
                                </article>
                                <article class="col-sm-4 media-stat-tile">
                                    <span>Alt text SEO</span>
                                    <strong>Ready</strong>
                                </article>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade media-preview-modal" id="mediaPreviewModal" tabindex="-1" aria-labelledby="mediaPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="mediaPreviewModalLabel">Media preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="mediaPreviewModalBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn media-mini-btn" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade media-preview-modal" id="mediaCropModal" tabindex="-1" aria-labelledby="mediaCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="mediaCropModalLabel">Crop image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="media-crop-toolbar btn-group mb-3" role="group" aria-label="Crop aspect ratio">
                    <button type="button" class="btn media-mini-btn active" data-crop-ratio="free">Free</button>
                    <button type="button" class="btn media-mini-btn" data-crop-ratio="1">1:1</button>
                    <button type="button" class="btn media-mini-btn" data-crop-ratio="1.333">4:3</button>
                    <button type="button" class="btn media-mini-btn" data-crop-ratio="1.778">16:9</button>
                </div>
                <div class="media-crop-stage">
                    <canvas id="mediaCropCanvas"></canvas>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn media-mini-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn media-mini-btn media-mini-btn-primary" id="mediaCropApply">Apply crop</button>
            </div>
        </div>
    </div>
</div>
