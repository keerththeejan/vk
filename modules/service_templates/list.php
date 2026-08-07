<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/service_templates_service.php';

$pageTitle = 'Service Templates';
$perms = vk_st_templates_require($pdo);
$csrf = csrf_token();
$apiUrl = base_url('api/service_templates.php');
$permsJson = htmlspecialchars(json_encode($perms, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/service-templates.css');
$listCssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/service-templates-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/service-templates-admin.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/service-templates.css')) . '?v=' . e($cssV) . '" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/service-templates-list.css')) . '?v=' . e($listCssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkStApp" class="vk-st-admin<?= $perms['can_bulk'] ? ' vk-st-has-bulk' : ' vk-st-no-bulk' ?>"
     data-api-url="<?= e($apiUrl) ?>"
     data-csrf="<?= e($csrf) ?>"
     data-permissions="<?= $permsJson ?>"
     data-base-url="<?= e(BASE_URL) ?>"
     data-can-create="<?= $perms['can_create'] ? '1' : '0' ?>">

<header class="vk-st-header vk-st-header-compact">
    <div class="vk-st-header-inner">
        <div class="vk-st-header-copy">
            <h1 class="vk-st-title">Service Templates</h1>
            <p class="vk-st-subtitle d-none d-md-block">Pricing templates for repair jobs</p>
        </div>
        <?php if ($perms['can_create']): ?>
        <a class="btn vk-st-btn vk-st-btn-primary vk-st-header-cta" href="<?= e(BASE_URL) ?>/modules/service_templates/add.php">
            <i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add template</span>
        </a>
        <?php endif; ?>
    </div>
</header>

<div class="vk-st-kpi-grid" id="vkStStats" role="region" aria-label="Template statistics">
    <div class="vk-st-kpi vk-st-kpi-blue">
        <div class="vk-st-kpi-icon" aria-hidden="true"><i class="bi bi-layers"></i></div>
        <div class="vk-st-kpi-body">
            <span class="vk-st-kpi-label">Total Templates</span>
            <span class="vk-st-kpi-value" id="vkStStatTotal">—</span>
            <span class="vk-st-kpi-trend" id="vkStStatTotalTrend"></span>
        </div>
    </div>
    <div class="vk-st-kpi vk-st-kpi-green">
        <div class="vk-st-kpi-icon" aria-hidden="true"><i class="bi bi-check-circle"></i></div>
        <div class="vk-st-kpi-body">
            <span class="vk-st-kpi-label">Active</span>
            <span class="vk-st-kpi-value" id="vkStStatActive">—</span>
            <span class="vk-st-kpi-trend" id="vkStStatActiveTrend"></span>
        </div>
    </div>
    <div class="vk-st-kpi vk-st-kpi-gray">
        <div class="vk-st-kpi-icon" aria-hidden="true"><i class="bi bi-pause-circle"></i></div>
        <div class="vk-st-kpi-body">
            <span class="vk-st-kpi-label">Inactive</span>
            <span class="vk-st-kpi-value" id="vkStStatInactive">—</span>
            <span class="vk-st-kpi-trend" id="vkStStatInactiveTrend"></span>
        </div>
    </div>
    <div class="vk-st-kpi vk-st-kpi-purple">
        <div class="vk-st-kpi-icon" aria-hidden="true"><i class="bi bi-grid"></i></div>
        <div class="vk-st-kpi-body">
            <span class="vk-st-kpi-label">Categories</span>
            <span class="vk-st-kpi-value" id="vkStStatCategories">—</span>
            <span class="vk-st-kpi-trend" id="vkStStatCategoriesTrend"></span>
        </div>
    </div>
    <div class="vk-st-kpi vk-st-kpi-teal">
        <div class="vk-st-kpi-icon" aria-hidden="true"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-st-kpi-body">
            <span class="vk-st-kpi-label">Total Value</span>
            <span class="vk-st-kpi-value vk-st-kpi-value-sm" id="vkStStatValue">—</span>
            <span class="vk-st-kpi-trend" id="vkStStatValueTrend"></span>
        </div>
    </div>
    <div class="vk-st-kpi vk-st-kpi-orange">
        <div class="vk-st-kpi-icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="vk-st-kpi-body">
            <span class="vk-st-kpi-label">Most Used</span>
            <span class="vk-st-kpi-value vk-st-kpi-value-sm" id="vkStStatMostUsed" title="">—</span>
            <span class="vk-st-kpi-trend" id="vkStStatMostUsedTrend"></span>
        </div>
    </div>
</div>

<div class="vk-st-toolbar" role="search">
    <div class="vk-st-toolbar-mobile-head d-lg-none">
        <span class="vk-st-toolbar-mobile-label"><i class="bi bi-sliders"></i> Filters</span>
        <button type="button" class="vk-st-btn vk-st-btn-icon" data-bs-toggle="collapse" data-bs-target="#vkStToolbarCollapse" aria-expanded="false" aria-controls="vkStToolbarCollapse" aria-label="Toggle filters">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse vk-st-toolbar-collapse" id="vkStToolbarCollapse">
        <div class="vk-st-toolbar-inner">
            <div class="vk-st-search-wrap">
                <i class="bi bi-search vk-st-search-ico" aria-hidden="true"></i>
                <input type="search" class="form-control vk-st-ctl" id="vkStSearch" placeholder="Search templates…" autocomplete="off" aria-label="Search templates">
            </div>
            <select class="form-select vk-st-ctl vk-st-ctl-sm" id="vkStFilterCategory" aria-label="Category">
                <option value="">Category</option>
                <?php foreach (VK_ST_CATEGORIES as $cat): ?>
                <option value="<?= e($cat) ?>"><?= e(ucfirst($cat)) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select vk-st-ctl vk-st-ctl-sm" id="vkStFilterStatus" aria-label="Status">
                <option value="">Status</option>
                <?php foreach (VK_ST_STATUSES as $st): ?>
                <option value="<?= e($st) ?>"><?= e(ucfirst($st)) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select vk-st-ctl vk-st-ctl-xs" id="vkStFilterDefault" aria-label="Default">
                <option value="">Default</option>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <input type="date" class="form-control vk-st-ctl vk-st-ctl-date" id="vkStDateFrom" aria-label="From date" title="From">
            <input type="date" class="form-control vk-st-ctl vk-st-ctl-date" id="vkStDateTo" aria-label="To date" title="To">
            <select class="form-select vk-st-ctl vk-st-ctl-xs" id="vkStPerPage" aria-label="Rows per page">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="all">All</option>
            </select>
            <div class="vk-st-toolbar-btns">
                <button type="button" class="vk-st-btn vk-st-btn-primary" id="vkStApply" aria-label="Apply filters"><i class="bi bi-funnel"></i><span class="d-none d-xl-inline ms-1">Apply</span></button>
                <button type="button" class="vk-st-btn vk-st-btn-ghost" id="vkStReset">Reset</button>
                <?php if ($perms['can_export']): ?>
                <button type="button" class="vk-st-btn vk-st-btn-ghost" id="vkStExportCsv" title="Export CSV" aria-label="Export CSV"><i class="bi bi-download"></i></button>
                <button type="button" class="vk-st-btn vk-st-btn-ghost" id="vkStExportJson" title="Export JSON" aria-label="Export JSON"><i class="bi bi-braces"></i></button>
                <?php endif; ?>
                <?php if ($perms['can_bulk']): ?>
                <button type="button" class="vk-st-btn vk-st-btn-ghost" id="vkStBulkToggle" title="Bulk select" aria-label="Bulk select"><i class="bi bi-check2-square"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($perms['can_bulk']): ?>
        <div class="vk-st-bulk d-none" id="vkStBulkBar">
            <select class="form-select vk-st-ctl" id="vkStBulkAction" aria-label="Bulk action">
                <option value="">Bulk action…</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="archive">Archive</option>
                <option value="draft">Mark draft</option>
                <option value="duplicate">Duplicate</option>
                <?php if ($perms['can_delete']): ?><option value="delete">Delete</option><?php endif; ?>
            </select>
            <button type="button" class="vk-st-btn vk-st-btn-primary" id="vkStBulkRun">Run</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="vk-st-panel">
    <div class="vk-st-panel-scroll vk-st-desktop-only" id="vkStTableWrap">
        <table class="table table-sm table-borderless vk-st-table vk-st-grid mb-0" id="vkStTable">
            <colgroup>
                <?php if ($perms['can_bulk']): ?><col class="vk-st-w-check"><?php endif; ?>
                <col class="vk-st-w-thumb">
                <col class="vk-st-w-name">
                <col class="vk-st-w-code">
                <col class="vk-st-w-cat">
                <col class="vk-st-w-amt">
                <col class="vk-st-w-status">
                <col class="vk-st-w-ver">
                <col class="vk-st-w-usage">
                <col class="vk-st-w-date">
                <col class="vk-st-w-act">
            </colgroup>
            <thead>
                <tr>
                    <?php if ($perms['can_bulk']): ?>
                    <th class="vk-st-th-check vk-st-sticky-col" scope="col"><input type="checkbox" class="form-check-input" id="vkStSelectAll" aria-label="Select all rows"></th>
                    <?php endif; ?>
                    <th class="vk-st-th-thumb vk-st-sticky-col" scope="col"><span class="visually-hidden">Image</span></th>
                    <th data-sort="name" class="vk-st-sortable vk-st-sticky-col vk-st-sticky-name" scope="col">Template Name</th>
                    <th data-sort="template_code" class="vk-st-sortable" scope="col">Code</th>
                    <th data-sort="category" class="vk-st-sortable d-none d-md-table-cell" scope="col">Category</th>
                    <th data-sort="default_amount" class="vk-st-sortable text-end" scope="col">Amount</th>
                    <th data-sort="status" class="vk-st-sortable" scope="col">Status</th>
                    <th data-sort="version" class="vk-st-sortable d-none d-lg-table-cell text-center" scope="col">Ver</th>
                    <th data-sort="usage_count" class="vk-st-sortable d-none d-lg-table-cell text-center" scope="col">Usage</th>
                    <th data-sort="created_at" class="vk-st-sortable d-none d-xl-table-cell" scope="col">Created</th>
                    <th class="vk-st-th-act text-end" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody id="vkStBody" aria-live="polite"></tbody>
        </table>
    </div>

    <div class="vk-st-mobile-only" id="vkStMobileList" aria-live="polite"></div>

    <footer class="vk-st-panel-footer">
        <div class="vk-st-range" id="vkStMeta">Showing —</div>
        <nav class="vk-st-pagination" id="vkStPager" aria-label="Template pagination">
            <button type="button" class="vk-st-btn vk-st-btn-icon vk-st-page-btn" id="vkStPagePrev" aria-label="Previous page" disabled><i class="bi bi-chevron-left"></i></button>
            <div class="vk-st-page-nums" id="vkStPageNums" role="group" aria-label="Page numbers"></div>
            <button type="button" class="vk-st-btn vk-st-btn-icon vk-st-page-btn" id="vkStPageNext" aria-label="Next page" disabled><i class="bi bi-chevron-right"></i></button>
        </nav>
    </footer>
</div>
</div>

<div class="modal fade" id="vkStViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title" id="vkStViewTitle">Template</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="vkStViewBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary d-none" id="vkStViewEdit">Edit template</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="vkStPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title">Live preview</h2>
                <div class="btn-group btn-group-sm ms-auto me-2">
                    <button type="button" class="btn btn-outline-secondary active" data-preview="desktop">Desktop</button>
                    <button type="button" class="btn btn-outline-secondary" data-preview="tablet">Tablet</button>
                    <button type="button" class="btn btn-outline-secondary" data-preview="mobile">Mobile</button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body vk-st-preview-wrap p-3">
                <iframe id="vkStPreviewFrame" class="vk-st-preview vk-st-preview-desktop" title="Template preview"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="vkStDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="h5 modal-title">Delete template?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p class="mb-0" id="vkStDeleteMsg">This will soft-delete the template. Templates in use cannot be deleted.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="vkStDeleteConfirm">Delete</button>
            </div>
            <input type="hidden" id="vkStDeleteId" value="">
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/service-templates-admin.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
