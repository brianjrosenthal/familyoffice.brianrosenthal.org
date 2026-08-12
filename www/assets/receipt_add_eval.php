<?php
// Evaluates a new asset receipt (POST from assets/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /assets/');
    exit;
}

require_csrf();

$assetId = (int)($_POST['asset_id'] ?? 0);
if ($assetId <= 0 || !AssetManagement::getAsset($assetId)) {
    $_SESSION['error'] = 'Asset not found.';
    header('Location: /assets/');
    exit;
}

$data = [
    'title' => $_POST['title'] ?? '',
    'description' => $_POST['description'] ?? '',
];

try {
    $ctx = UserContext::getLoggedInUserContext();

    if (!isset($_FILES['image']) || (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Please choose a receipt image to upload.');
    }

    $fileId = AssetManagement::storeUploadedReceiptImage($ctx, $_FILES['image']);
    AssetManagement::addReceipt($ctx, $assetId, $data, $fileId);
    $_SESSION['success'] = 'Receipt added.';
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to add receipt: ' . $e->getMessage();
    // Repopulate the receipt form (kept separate from the asset form's data)
    $_SESSION['receipt_form_data'] = $data;
}

header('Location: /assets/edit.php?id=' . $assetId);
exit;
