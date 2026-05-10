<?php
declare(strict_types=1);
$isEdit = !empty($data['id']);
$imageUrl = vk_staff_display_image($data, true);
$hasCustomImage = vk_staff_image_path((string) ($data['image'] ?? '')) !== '';
$imageOnError = vk_staff_image_onerror_attr();
$status = vk_staff_normalize_status((string) ($data['status'] ?? (!empty($data['active']) ? 'active' : 'inactive')));
?>
<div class="card vk-card vk-staff-editor-card">
    <div class="card-body p-3 p-lg-4">
        <form method="post" enctype="multipart/form-data" data-loading data-staff-form>
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="vk-staff-upload-zone" data-staff-dropzone tabindex="0" role="button" aria-label="Choose or drop staff profile image">
                        <input class="visually-hidden" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-staff-file>
                        <img src="<?= e($imageUrl) ?>" alt="Profile image preview" width="180" height="180" loading="lazy" decoding="async" onerror="<?= $imageOnError ?>" data-staff-preview>
                        <div class="vk-staff-upload-copy">
                            <strong>Drop profile image here</strong>
                            <span>JPG, JPEG, PNG, or WebP up to 5MB</span>
                        </div>
                        <div class="vk-staff-upload-progress" aria-hidden="true"><span data-staff-progress></span></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-staff-change>Change image</button>
                        <?php if ($isEdit && $hasCustomImage): ?>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-staff-remove>Remove</button>
                            <input type="hidden" name="remove_image" value="0" data-staff-remove-input>
                        <?php endif; ?>
                    </div>
                    <div class="form-text mt-2">Images are validated, optimized, and stored in <code>uploads/staff/</code>.</div>
                </div>

                <div class="col-lg-8">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="name">Full name <span class="text-danger">*</span></label>
                            <input class="form-control" id="name" name="name" required maxlength="150" value="<?= e((string) ($data['name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                            <input class="form-control" id="role" name="role" required maxlength="80" placeholder="Owner, Founder, Technician" value="<?= e((string) ($data['role'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Professional biography</label>
                            <textarea class="form-control" id="description" name="description" rows="4" maxlength="1200"><?= e((string) ($data['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="skills">Skills</label>
                            <textarea class="form-control" id="skills" name="skills" rows="3" placeholder="Laptop repair, CCTV, Networking"><?= e((string) ($data['skills'] ?? '')) ?></textarea>
                            <div class="form-text">Comma, semicolon, or line separated.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="certifications">Certifications</label>
                            <textarea class="form-control" id="certifications" name="certifications" rows="3" placeholder="Cisco basics, CCTV installation, Safety training"><?= e((string) ($data['certifications'] ?? '')) ?></textarea>
                            <div class="form-text">Shown in the homepage profile modal.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="experience">Experience badge</label>
                            <input class="form-control" id="experience" name="experience" maxlength="150" placeholder="8+ years" value="<?= e((string) ($data['experience'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="years_experience">Years</label>
                            <input class="form-control" type="number" min="0" max="9999" id="years_experience" name="years_experience" value="<?= e((string) ($data['years_experience'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="completed_projects">Completed projects</label>
                            <input class="form-control" type="number" min="0" max="9999" id="completed_projects" name="completed_projects" value="<?= e((string) ($data['completed_projects'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="specialization">Specialization</label>
                            <input class="form-control" id="specialization" name="specialization" maxlength="180" placeholder="Enterprise networking and diagnostics" value="<?= e((string) ($data['specialization'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="status">Availability status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="on_leave" <?= $status === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" type="email" id="email" name="email" maxlength="150" value="<?= e((string) ($data['email'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone / WhatsApp</label>
                            <input class="form-control" id="phone" name="phone" maxlength="40" value="<?= e((string) ($data['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="social_links">Social links</label>
                            <textarea class="form-control" id="social_links" name="social_links" rows="3" placeholder="LinkedIn | https://linkedin.com/in/name"><?= e((string) ($data['social_links'] ?? '')) ?></textarea>
                            <div class="form-text">One per line. Format: Label | https://example.com</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sort_order">Sort order</label>
                            <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?= e((string) ($data['sort_order'] ?? 0)) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update profile' : 'Create profile' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/staff/list.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
