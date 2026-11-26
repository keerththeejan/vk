<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-12">
        <div class="pc-dropzone border rounded p-4 text-center" id="pcDropzone" tabindex="0" role="button" aria-label="Choose product image">
            <i class="bi bi-image fs-2 text-secondary d-block mb-2" aria-hidden="true"></i>
            <p class="mb-1">Product image <span class="text-secondary">(optional)</span></p>
            <p class="small text-secondary mb-3">JPG, PNG, WebP, or GIF · up to 10 MB</p>
            <input type="file" class="visually-hidden" id="images" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif">
            <button type="button" class="btn btn-outline-primary btn-sm" id="pcBrowseMedia">Choose image</button>
        </div>
    </div>
    <div class="col-12"><div class="pc-gallery row g-2" id="pcGallery" aria-live="polite"></div></div>
</div>
