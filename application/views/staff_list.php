<?php
// Note: Header and footer are included by staff.php controller
$staffMembers = $staffMembers ?? [];
?>
<section class="vk-pub-section py-5">
    <div class="container py-lg-4">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="vk-section-kicker">Staff &amp; Owner Portfolio</span>
            <h1 class="vk-section-title mb-2">Our Team</h1>
            <p class="vk-section-lead mx-auto mb-0">Meet the owners, technicians, and support team keeping each service request clear and accountable.</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($staffMembers as $member):
                $image = $member['image'] ?? '';
                $description = $member['description'] ?? '';
                $skills = $member['skills'] ?? '';
                $skillsArray = function_exists('vk_staff_skills_list')
                    ? vk_staff_skills_list(is_array($skills) ? implode(',', $skills) : (string) $skills)
                    : (is_string($skills) ? array_filter(array_map('trim', preg_split('/[,;\r\n]+/', $skills) ?: [])) : (is_array($skills) ? $skills : []));
                if (!$skillsArray) {
                    $skillsArray = ['Diagnostics', 'Field Service', 'Customer Care'];
                }
                $imgUrl = function_exists('vk_staff_image_url') ? vk_staff_image_url((string) $image) : base_url('assets/images/default-avatar.svg');
                $imgOnError = function_exists('vk_staff_image_onerror_attr') ? vk_staff_image_onerror_attr() : "this.onerror=null;this.src='" . e(base_url('assets/images/default-avatar.svg')) . "';";
                $socialLinks = function_exists('vk_staff_social_links') ? vk_staff_social_links((string) ($member['social_links'] ?? '')) : [];
                $role = (string) ($member['role'] ?? 'Team Member');
                $roleClass = str_contains(strtolower($role), 'owner') ? 'text-bg-primary' : 'text-bg-info';
            ?>
                <div class="col-lg-3 col-md-6 col-sm-12" data-aos="fade-up">
                    <article class="vk-team-card h-100">
                        <div class="vk-team-card-media">
                            <img
                                src="<?= e($imgUrl) ?>"
                                alt="<?= e((string) ($member['name'] ?? 'Profile')) ?> profile image"
                                width="132"
                                height="132"
                                loading="lazy"
                                decoding="async"
                                onerror="<?= $imgOnError ?>"
                            >
                        </div>
                        <div class="vk-team-card-body">
                            <span class="badge <?= e($roleClass) ?> rounded-pill mb-2"><?= e($role) ?></span>
                            <h2 class="h5 mb-2"><?= e((string) $member['name']) ?></h2>
                            <p class="text-muted small mb-3"><?= e(trim((string) $description) !== '' ? (string) $description : 'Experienced support professional focused on clean, reliable customer service.') ?></p>
                            <?php if ($skillsArray): ?>
                                <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                                    <?php foreach ($skillsArray as $skill): ?>
                                        <span class="badge rounded-pill text-bg-light border"><?= e((string) $skill) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-center gap-2">
                                <?php if ($socialLinks): ?>
                                    <?php foreach (array_slice($socialLinks, 0, 3) as $link):
                                        $label = (string) ($link['label'] ?? 'Profile');
                                        ?>
                                        <a class="vk-social-btn" href="<?= e((string) $link['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?> for <?= e((string) ($member['name'] ?? 'team member')) ?>"><i data-lucide="link"></i></a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <a class="vk-social-btn" href="<?= e(BASE_URL) ?>/book.php" aria-label="Book service with VK Network"><i data-lucide="calendar-plus"></i></a>
                                    <a class="vk-social-btn" href="<?= e(BASE_URL) ?>/portfolio.php" aria-label="View completed work"><i data-lucide="images"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
