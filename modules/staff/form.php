<?php
declare(strict_types=1);
$isEdit = !empty($data['id']);
$imageUrl = vk_staff_image_url($data['image'] ?? null);
$imageOnError = vk_staff_image_onerror_attr();
?>
<div class="card vk-card" style="max-width: 920px;">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" data-loading>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="name">Full name <span class="text-danger">*</span></label>
                    <input class="form-control" id="name" name="name" required maxlength="150" value="<?= e((string) ($data['name'] ?? '')) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                    <input class="form-control" id="role" name="role" required maxlength="80" placeholder="Owner, Technician, Admin" value="<?= e((string) ($data['role'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Bio / description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?= e((string) ($data['description'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="skills">Skills</label>
                    <textarea class="form-control" id="skills" name="skills" rows="3" placeholder="Laptop repair, CCTV, Networking"><?= e((string) ($data['skills'] ?? '')) ?></textarea>
                    <div class="form-text">Comma, semicolon, or line separated.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="social_links">Social links</label>
                    <textarea class="form-control" id="social_links" name="social_links" rows="3" placeholder="LinkedIn | https://linkedin.com/in/name"><?= e((string) ($data['social_links'] ?? '')) ?></textarea>
                    <div class="form-text">One per line. Format: Label | https://example.com</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="experience">Experience</label>
                    <input class="form-control" id="experience" name="experience" maxlength="150" placeholder="8+ years" value="<?= e((string) ($data['experience'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" maxlength="150" value="<?= e((string) ($data['email'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" maxlength="40" value="<?= e((string) ($data['phone'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="image">Profile image</label>
                    <input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG, or WebP, max 2 MB. Uploads to <code>uploads/staff</code>.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?= e((string) ($data['sort_order'] ?? 0)) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="active" id="active" value="1" <?= (int) ($data['active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Published</label>
                    </div>
                </div>
                <?php if ($imageUrl !== ''): ?>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= e($imageUrl) ?>" alt="" class="img-fluid rounded object-fit-cover" style="width:96px;height:96px;" width="96" height="96" loading="lazy" decoding="async" onerror="<?= $imageOnError ?>">
                            <span class="small text-muted">Current profile image</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update profile' : 'Create profile' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/staff/list.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
