<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-12">
        <div class="pc-dropzone border rounded p-4 text-center" id="pcDropzone" tabindex="0" role="button" aria-label="Upload product media">
            <i class="bi bi-cloud-arrow-up fs-1 text-secondary d-block mb-2" aria-hidden="true"></i>
            <p class="mb-1">Drag &amp; drop images, video, or PDF</p>
            <p class="small text-secondary mb-3">Supports JPG, PNG, WebP, MP4, PDF — max 10MB each</p>
            <input type="file" class="visually-hidden" id="images" name="images[]" accept="image/*,video/mp4,application/pdf" multiple>
            <button type="button" class="btn btn-outline-primary btn-sm" id="pcBrowseMedia">Browse Files</button>
        </div>
    </div>
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pcCompressImages">Compress Images</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pcAiOptimize">AI Image Optimization</button>
        </div>
        <div class="pc-gallery row g-2" id="pcGallery" aria-live="polite"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="video_url">Video URL (optional)</label>
        <input type="url" class="form-control" id="video_url" name="video_url" placeholder="https://">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="pdf_attachment">PDF Attachment</label>
        <input type="file" class="form-control" id="pdf_attachment" name="pdf_attachment" accept="application/pdf">
    </div>
</div>
