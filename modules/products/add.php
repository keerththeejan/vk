<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once __DIR__ . '/create/ProductCreateController.php';

ProductCreateController::run($pdo);
