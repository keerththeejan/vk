<?php
declare(strict_types=1);
$pageTitle = 'Page SEO Settings';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/seo/pages.php');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $row = [
        'meta_title' => trim((string) ($_POST['meta_title'] ?? '')),
        'meta_description' => trim((string) ($_POST['meta_description'] ?? '')),
        'meta_keywords' => trim((string) ($_POST['meta_keywords'] ?? '')),
        'canonical_url' => trim((string) ($_POST['canonical_url'] ?? '')),
        'og_title' => trim((string) ($_POST['og_title'] ?? '')),
        'og_description' => trim((string) ($_POST['og_description'] ?? '')),
        'og_image' => trim((string) ($_POST['og_image'] ?? '')),
        'twitter_card' => trim((string) ($_POST['twitter_card'] ?? 'summary_large_image')),
        'schema_markup' => trim((string) ($_POST['schema_markup'] ?? '')),
        'robots_directive' => trim((string) ($_POST['robots_directive'] ?? 'index,follow')),
        'indexing_status' => trim((string) ($_POST['indexing_status'] ?? 'unknown')),
    ];
    $pageKey = trim((string) ($_POST['page_key'] ?? ''));
    $pageUrl = trim((string) ($_POST['page_url'] ?? ''));
    $schemaJson = $row['schema_markup'] !== '' ? $row['schema_markup'] : '{}';
    json_decode($schemaJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        flash_set('error', 'Schema markup must be valid JSON.');
        redirect('/modules/seo/pages.php' . ($id > 0 ? '?id=' . $id : ''));
    }
    $score = vk_seo_score_from_row($row);
    if ($id > 0) {
        $st = $pdo->prepare(
            'UPDATE seo_settings SET page_key=?, page_url=?, meta_title=?, meta_description=?, meta_keywords=?, canonical_url=?, og_title=?, og_description=?, og_image=?, twitter_card=?, schema_markup=?, robots_directive=?, seo_score=?, indexing_status=? WHERE id=?'
        );
        $st->execute([$pageKey, $pageUrl, $row['meta_title'], $row['meta_description'], $row['meta_keywords'], $row['canonical_url'], $row['og_title'], $row['og_description'], $row['og_image'], $row['twitter_card'], $schemaJson, $row['robots_directive'], $score, $row['indexing_status'], $id]);
    } else {
        $st = $pdo->prepare(
            'INSERT INTO seo_settings (page_key, page_url, meta_title, meta_description, meta_keywords, canonical_url, og_title, og_description, og_image, twitter_card, schema_markup, robots_directive, seo_score, indexing_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([$pageKey, $pageUrl, $row['meta_title'], $row['meta_description'], $row['meta_keywords'], $row['canonical_url'], $row['og_title'], $row['og_description'], $row['og_image'], $row['twitter_card'], $schemaJson, $row['robots_directive'], $score, $row['indexing_status']]);
    }
    flash_set('success', 'SEO settings saved.');
    redirect('/modules/seo/pages.php');
}

$editId = (int) ($_GET['id'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM seo_settings WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$rows = $pdo->query('SELECT * FROM seo_settings ORDER BY updated_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
$defaultSchema = json_encode(['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => vk_app_setting('company_name', 'VK IT Network')], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<div class="vk-suite-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <span class="vk-suite-kicker"><i class="bi bi-pencil-square"></i> Metadata Studio</span>
            <h1 class="h3 mb-0">Page SEO Settings</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/seo/index.php">SEO Dashboard</a>
    </div>
    <div class="row g-3">
        <div class="col-xl-5">
            <form class="card vk-card h-100" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="card-header bg-transparent fw-semibold"><?= $edit ? 'Edit page metadata' : 'Create page metadata' ?></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Page key</label><input class="form-control" name="page_key" required value="<?= e((string) ($edit['page_key'] ?? '')) ?>" placeholder="home"></div>
                        <div class="col-md-6"><label class="form-label">Page URL</label><input class="form-control" name="page_url" required value="<?= e((string) ($edit['page_url'] ?? '')) ?>" placeholder="/vk/index.php"></div>
                        <div class="col-12"><label class="form-label">Meta title</label><input class="form-control" name="meta_title" maxlength="255" required value="<?= e((string) ($edit['meta_title'] ?? '')) ?>"></div>
                        <div class="col-12"><label class="form-label">Meta description</label><textarea class="form-control" name="meta_description" rows="3"><?= e((string) ($edit['meta_description'] ?? '')) ?></textarea></div>
                        <div class="col-12"><label class="form-label">Dynamic keywords</label><input class="form-control" name="meta_keywords" value="<?= e((string) ($edit['meta_keywords'] ?? '')) ?>"></div>
                        <div class="col-12"><label class="form-label">Canonical URL</label><input class="form-control" name="canonical_url" value="<?= e((string) ($edit['canonical_url'] ?? '')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Open Graph title</label><input class="form-control" name="og_title" value="<?= e((string) ($edit['og_title'] ?? '')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Open Graph image</label><input class="form-control" name="og_image" value="<?= e((string) ($edit['og_image'] ?? '')) ?>"></div>
                        <div class="col-12"><label class="form-label">Open Graph description</label><textarea class="form-control" name="og_description" rows="2"><?= e((string) ($edit['og_description'] ?? '')) ?></textarea></div>
                        <div class="col-md-6"><label class="form-label">Twitter/X card</label><select class="form-select" name="twitter_card"><option>summary_large_image</option><option>summary</option><option>app</option></select></div>
                        <div class="col-md-6"><label class="form-label">Indexing status</label><select class="form-select" name="indexing_status"><option>unknown</option><option>ready</option><option>submitted</option><option>indexed</option><option>blocked</option></select></div>
                        <div class="col-12"><label class="form-label">Robots directive</label><input class="form-control" name="robots_directive" value="<?= e((string) ($edit['robots_directive'] ?? 'index,follow')) ?>"></div>
                        <div class="col-12"><label class="form-label">Structured schema JSON</label><textarea class="form-control font-monospace" name="schema_markup" rows="7"><?= e((string) ($edit['schema_markup'] ?? $defaultSchema)) ?></textarea></div>
                    </div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Save SEO settings</button></div>
            </form>
        </div>
        <div class="col-xl-7">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Managed pages</div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-stack">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Page</th><th>Score</th><th>Social</th><th>Robots</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e((string) $row['page_key']) ?></strong><div class="small text-muted text-break"><?= e((string) $row['meta_title']) ?></div></td>
                                    <td><span class="badge text-bg-<?= (int) $row['seo_score'] >= 80 ? 'success' : 'warning' ?>"><?= (int) $row['seo_score'] ?>%</span></td>
                                    <td><span class="badge text-bg-info"><?= e((string) $row['twitter_card']) ?></span></td>
                                    <td class="small"><?= e((string) $row['robots_directive']) ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?id=<?= (int) $row['id'] ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
