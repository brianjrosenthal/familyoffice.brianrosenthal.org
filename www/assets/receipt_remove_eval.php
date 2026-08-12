<?php
// Evaluates asset receipt removal (POST from assets/edit.php or
// assets/receipt_edit.php). Deletes the receipt and its image bytes.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /assets/');
    exit;
}

require_csrf();

$receiptId = (int)($_POST['receipt_id'] ?? 0);
$assetId = (int)($_POST['asset_id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (AssetManagement::removeReceipt($ctx, $receiptId)) {
        $_SESSION['success'] = 'Receipt removed.';
    } else {
        $_SESSION['error'] = 'Receipt not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to remove receipt: ' . $e->getMessage();
}

header('Location: ' . ($assetId > 0 ? '/assets/edit.php?id=' . $assetId : '/assets/'));
exit;
