<?php
// Evaluates an asset receipt edit (POST from assets/receipt_edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /assets/');
    exit;
}

require_csrf();

$receiptId = (int)($_POST['id'] ?? 0);
$receipt = $receiptId > 0 ? AssetManagement::getReceipt($receiptId) : null;
if (!$receipt) {
    $_SESSION['error'] = 'Receipt not found.';
    header('Location: /assets/');
    exit;
}

$assetId = (int)$receipt['asset_id'];
$data = [
    'title' => $_POST['title'] ?? '',
    'description' => $_POST['description'] ?? '',
];

try {
    $ctx = UserContext::getLoggedInUserContext();

    // A new image is optional; without one only the metadata changes.
    $newFileId = null;
    if (isset($_FILES['image']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $newFileId = AssetManagement::storeUploadedReceiptImage($ctx, $_FILES['image']);
    }

    AssetManagement::updateReceipt($ctx, $receiptId, $data, $newFileId);
    $_SESSION['success'] = 'Receipt saved.';
    header('Location: /assets/edit.php?id=' . $assetId);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to save receipt: ' . $e->getMessage();
    $_SESSION['form_data'] = $data;
    header('Location: /assets/receipt_edit.php?id=' . $receiptId);
    exit;
}
