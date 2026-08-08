<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('warranty_service');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'POST required']);
    exit;
}

$token = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!csrf_verify($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$idsRaw = $_POST['ids'] ?? [];
if (!is_array($idsRaw)) {
    $idsRaw = [$idsRaw];
}
$ids = [];
foreach ($idsRaw as $raw) {
    $id = (int) $raw;
    if ($id > 0) {
        $ids[] = $id;
    }
}
$ids = array_values(array_unique($ids));

if ($ids === [] || $action === '') {
    echo json_encode(['ok' => false, 'message' => 'No items selected.']);
    exit;
}

$okCount = 0;
$messages = [];

switch ($action) {
    case 'delete':
        $st = $pdo->prepare('DELETE FROM warranty_records WHERE id = ?');
        foreach ($ids as $id) {
            if ($st->execute([$id])) {
                $okCount++;
            }
        }
        echo json_encode(['ok' => true, 'message' => "Deleted {$okCount} warranty record(s)."]);
        break;

    case 'deactivate':
        foreach ($ids as $id) {
            if (vk_warranty_mark_cancelled($pdo, $id)) {
                $okCount++;
            }
        }
        echo json_encode(['ok' => true, 'message' => "Deactivated {$okCount} warranty record(s)."]);
        break;

    case 'renew':
        foreach ($ids as $id) {
            if (vk_warranty_renew($pdo, $id)) {
                $okCount++;
            }
        }
        echo json_encode(['ok' => true, 'message' => "Renewed {$okCount} warranty record(s)."]);
        break;

    case 'email':
        foreach ($ids as $id) {
            $res = vk_warranty_email($pdo, $id);
            if (!empty($res['ok'])) {
                $okCount++;
            } else {
                $messages[] = vk_warranty_number($id) . ': ' . ($res['message'] ?? 'failed');
            }
        }
        $msg = "Emailed {$okCount} warranty certificate(s).";
        if ($messages) {
            $msg .= ' ' . implode(' ', array_slice($messages, 0, 3));
        }
        echo json_encode(['ok' => $okCount > 0, 'message' => $msg]);
        break;

    case 'export':
        $qs = http_build_query(['ids' => implode(',', $ids)]);
        echo json_encode([
            'ok' => true,
            'download' => rtrim(BASE_URL, '/') . '/modules/warranties/export.php?' . $qs,
            'message' => 'Preparing export…',
        ]);
        break;

    case 'print':
        if (count($ids) === 1) {
            echo json_encode([
                'ok' => true,
                'redirect' => rtrim(BASE_URL, '/') . '/modules/warranties/print.php?id=' . $ids[0],
            ]);
            break;
        }
        $qs = http_build_query(['ids' => implode(',', $ids)]);
        echo json_encode([
            'ok' => true,
            'redirect' => rtrim(BASE_URL, '/') . '/modules/warranties/print.php?' . $qs,
        ]);
        break;

    default:
        echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
}
