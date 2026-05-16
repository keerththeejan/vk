<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/web_portfolio_schema.php';

$pageTitle = 'Our work';
$navActive = 'portfolio';
$seoCanonicalPath = BASE_URL . '/portfolio.php';
$seoDescription = 'Completed projects and recent work from VK Network — repairs, CCTV, and field services in Sri Lanka.';
$posts = [];
$pdo = db();
vk_ensure_web_portfolio_tables($pdo);
if (db_table_exists($pdo, 'web_portfolio_posts')) {
    $posts = $pdo->query(
        'SELECT p.* FROM web_portfolio_posts p WHERE p.published = 1 ORDER BY p.display_date DESC, p.id DESC LIMIT 60'
    )->fetchAll();
    foreach ($posts as &$p) {
        $im = $pdo->prepare('SELECT * FROM web_portfolio_images WHERE post_id = ? ORDER BY sort_order, id');
        $im->execute([(int) $p['id']]);
        $p['_images'] = $im->fetchAll();
    }
    unset($p);
}

require __DIR__ . '/includes/public_header.php';
?>
<div class="vk-pub-page py-4 py-md-5">
    <div class="container">
        <div data-aos="fade-up" data-aos-duration="650">
        <h1 class="h3 mb-2">Completed projects</h1>
        <p class="text-muted small mb-4">A selection of recent work from our team.</p>
        </div>

        <?php if (!db_table_exists($pdo, 'web_portfolio_posts')): ?>
            <div class="alert alert-warning" data-aos="fade-up">Portfolio is not available until the database is upgraded.</div>
        <?php elseif (!$posts): ?>
            <p class="text-muted" data-aos="fade-up">No published posts yet — check back soon.</p>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($posts as $pi => $post): ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-duration="600" data-aos-delay="<?= (int) min(200, $pi * 50) ?>">
                        <div class="card h-100 shadow-sm border-0 vk-service-card overflow-hidden">
                            <?php
                            $imgs = $post['_images'] ?? [];
                            $hero = $imgs[0] ?? null;
                            ?>
                            <?php if ($hero): ?>
                                <img src="<?= e(BASE_URL) ?>/<?= e($hero['image_path']) ?>" width="560" height="280" loading="lazy" decoding="async" class="card-img-top" alt="<?= e($post['title']) ?> preview" style="width:100%;height:200px;object-fit:cover;min-height:200px;">
                            <?php else: ?>
                                <div class="bg-secondary bg-opacity-10 text-center py-5 text-muted vk-portfolio-placeholder d-flex align-items-center justify-content-center"><i data-lucide="image"></i></div>
                            <?php endif; ?>
                            <div class="card-body">
                                <div class="small text-muted mb-1"><?= e($post['display_date']) ?></div>
                                <h2 class="h5 card-title"><?= e($post['title']) ?></h2>
                                <p class="card-text small text-muted"><?= nl2br(e($post['description'] ?? '')) ?></p>
                                <?php if (count($imgs) > 1): ?>
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        <?php foreach (array_slice($imgs, 1) as $im): ?>
                                            <a href="<?= e(BASE_URL) ?>/<?= e($im['image_path']) ?>" target="_blank" rel="noopener">
                                                <img src="<?= e(BASE_URL) ?>/<?= e($im['image_path']) ?>" width="64" height="64" loading="lazy" decoding="async" alt="<?= e($post['title']) ?> thumbnail" class="rounded border" style="width:64px;height:64px;object-fit:cover;">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
