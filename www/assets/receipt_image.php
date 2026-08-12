<?php
// Serve an asset receipt's image. LOGIN REQUIRED — receipts are tax records
// stored as private files and are never served from the public disk cache.
// Renders inline by default (so the receipt can be previewed in the page);
// pass download=1 to force a download.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
require_once __DIR__ . '/../lib/Files.php';
Application::init();
require_login();

$receiptId = (int)($_GET['receipt_id'] ?? 0);
$receipt = $receiptId > 0 ? AssetManagement::getReceipt($receiptId) : null;
if (!$receipt || empty($receipt['private_file_id'])) {
    http_response_code(404);
    exit('Receipt image not found');
}

$file = Files::getPrivateFileForDownload((int)$receipt['private_file_id']);
if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$filename = (string)($file['original_filename'] ?? 'receipt');
// Sanitize for the Content-Disposition header
$safeName = preg_replace('/[^\w.\- ]+/u', '_', $filename) ?: 'receipt';

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: ' . ($file['content_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . strlen((string)$file['data']));
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $file['data'];
exit;
