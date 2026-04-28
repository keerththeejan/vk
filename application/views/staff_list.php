<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/public_header.php';
$staffMembers = $staff ?? $staffMembers ?? [];
?>
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="vk-section-title mb-2">Our Team</h1>
            <p class="vk-section-lead mx-auto mb-0">A sample staff and owner portfolio page for demonstrating the public UI.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($staffMembers as $member): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card vk-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <img
                                src="<?= e((string) $member['image']) ?>"
                                alt="<?= e((string) $member['name']) ?>"
                                class="rounded-circle mb-3"
                                style="width: 128px; height: 128px; object-fit: cover;"
                                loading="lazy"
                            >
                            <span class="badge text-bg-primary mb-2"><?= e((string) $member['role']) ?></span>
                            <h2 class="h5 mb-2"><?= e((string) $member['name']) ?></h2>
                            <p class="text-muted small mb-3"><?= e((string) $member['description']) ?></p>
                            <?php if (!empty($member['skills']) && is_array($member['skills'])): ?>
                                <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                                    <?php foreach ($member['skills'] as $skill): ?>
                                        <span class="badge rounded-pill text-bg-light border"><?= e((string) $skill) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <a class="btn btn-outline-primary btn-sm" href="<?= e(BASE_URL) ?>/index.php/staff">View Profile</a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/includes/public_footer.php'; ?>
