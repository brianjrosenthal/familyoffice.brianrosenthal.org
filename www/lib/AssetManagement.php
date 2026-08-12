<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Files.php';

class AssetManagement {
    // Suggested categories (free text — the UI offers these via a datalist)
    public const CATEGORY_SUGGESTIONS = [
        'House', 'Roof', 'Boiler', 'HVAC', 'Water Heater', 'Vehicle',
        'Jewelry', 'Appliance', 'Electronics', 'Furniture', 'Other',
    ];

    private static function pdo(): PDO {
        return pdo();
    }

    private static function log(string $action, ?int $assetId, array $details = []): void {
        try {
            $ctx = UserContext::getLoggedInUserContext();
            $meta = $details;
            if ($assetId !== null && !array_key_exists('asset_id', $meta)) {
                $meta['asset_id'] = $assetId;
            }
            ActivityLog::log($ctx, $action, $meta);
        } catch (\Throwable $e) {
            // Best-effort logging
        }
    }

    private static function assertLoggedIn(?UserContext $ctx): UserContext {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }
        return $ctx;
    }

    // Normalize/validate form fields shared by create and update.
    // Returns [name, category, description, purchase_date, purchase_price, warranty_info]
    private static function normalizeFields(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        $category = trim((string)($data['category'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $warranty = trim((string)($data['warranty_info'] ?? ''));

        $purchaseDate = trim((string)($data['purchase_date'] ?? ''));
        if ($purchaseDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            throw new InvalidArgumentException('Purchase date must be a valid date.');
        }

        $priceRaw = trim((string)($data['purchase_price'] ?? ''));
        $price = null;
        if ($priceRaw !== '') {
            $priceRaw = str_replace([',', '$'], '', $priceRaw);
            if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
                throw new InvalidArgumentException('Purchase price must be a non-negative number.');
            }
            $price = number_format((float)$priceRaw, 2, '.', '');
        }

        return [
            $name,
            $category !== '' ? $category : null,
            $description !== '' ? $description : null,
            $purchaseDate !== '' ? $purchaseDate : null,
            $price,
            $warranty !== '' ? $warranty : null,
        ];
    }

    public static function createAsset(?UserContext $ctx, array $data): int {
        $ctx = self::assertLoggedIn($ctx);
        [$name, $category, $description, $purchaseDate, $price, $warranty] = self::normalizeFields($data);

        $st = self::pdo()->prepare(
            "INSERT INTO assets (name, category, description, purchase_date, purchase_price, warranty_info, created_by_user_id)
             VALUES (?,?,?,?,?,?,?)"
        );
        $st->execute([$name, $category, $description, $purchaseDate, $price, $warranty, $ctx->id]);
        $id = (int)self::pdo()->lastInsertId();

        self::log('asset.create', $id, ['name' => $name]);
        return $id;
    }

    public static function updateAsset(?UserContext $ctx, int $id, array $data): bool {
        self::assertLoggedIn($ctx);
        [$name, $category, $description, $purchaseDate, $price, $warranty] = self::normalizeFields($data);

        $st = self::pdo()->prepare(
            "UPDATE assets SET name=?, category=?, description=?, purchase_date=?, purchase_price=?, warranty_info=? WHERE id=?"
        );
        $ok = $st->execute([$name, $category, $description, $purchaseDate, $price, $warranty, $id]);

        if ($ok) {
            self::log('asset.update', $id, ['name' => $name]);
        }
        return $ok;
    }

    public static function deleteAsset(?UserContext $ctx, int $id): bool {
        self::assertLoggedIn($ctx);
        $asset = self::getAsset($id);
        if (!$asset) return false;

        // Receipt images are private financial records — delete their bytes
        // rather than leaving them orphaned (vault documents work the same way)
        $receipts = self::listReceipts($id);

        // asset_photos / asset_receipts rows cascade; the public_files bytes stay (immutable, orphaned)
        $st = self::pdo()->prepare('DELETE FROM assets WHERE id = ?');
        $ok = $st->execute([$id]);

        if ($ok) {
            foreach ($receipts as $receipt) {
                if (!empty($receipt['private_file_id'])) {
                    Files::deletePrivateFile((int)$receipt['private_file_id']);
                }
            }
        }

        if ($ok) {
            self::log('asset.delete', $id, ['name' => $asset['name']]);
        }
        return $ok;
    }

    public static function getAsset(int $id): ?array {
        $st = self::pdo()->prepare('SELECT * FROM assets WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    // List assets with photo/receipt counts and one representative photo file id
    public static function listAssets(string $search = ''): array {
        $sql = "SELECT a.*,
                       COUNT(DISTINCT ap.id) AS photo_count,
                       COUNT(DISTINCT ar.id) AS receipt_count,
                       MIN(ap.public_file_id) AS first_photo_file_id
                FROM assets a
                LEFT JOIN asset_photos ap ON ap.asset_id = a.id
                LEFT JOIN asset_receipts ar ON ar.asset_id = a.id";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE a.name LIKE ? OR a.category LIKE ? OR a.description LIKE ?";
            $term = '%' . $search . '%';
            $params = [$term, $term, $term];
        }

        $sql .= " GROUP BY a.id ORDER BY a.name";
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    // ===== Photos =====

    public static function addPhoto(?UserContext $ctx, int $assetId, int $publicFileId): int {
        self::assertLoggedIn($ctx);
        if (!self::getAsset($assetId)) {
            throw new InvalidArgumentException('Asset not found.');
        }

        $st = self::pdo()->prepare('INSERT INTO asset_photos (asset_id, public_file_id) VALUES (?, ?)');
        $st->execute([$assetId, $publicFileId]);
        $id = (int)self::pdo()->lastInsertId();

        self::log('asset.photo_add', $assetId, ['public_file_id' => $publicFileId]);
        return $id;
    }

    public static function removePhoto(?UserContext $ctx, int $photoId): bool {
        self::assertLoggedIn($ctx);

        $st = self::pdo()->prepare('SELECT asset_id FROM asset_photos WHERE id = ? LIMIT 1');
        $st->execute([$photoId]);
        $row = $st->fetch();
        if (!$row) return false;

        $del = self::pdo()->prepare('DELETE FROM asset_photos WHERE id = ?');
        $ok = $del->execute([$photoId]);

        if ($ok) {
            self::log('asset.photo_remove', (int)$row['asset_id'], ['asset_photo_id' => $photoId]);
        }
        return $ok;
    }

    public static function listPhotos(int $assetId): array {
        $st = self::pdo()->prepare('SELECT id, public_file_id, created_at FROM asset_photos WHERE asset_id = ? ORDER BY id');
        $st->execute([$assetId]);
        return $st->fetchAll();
    }

    // ===== Receipts =====
    // Proof-of-purchase records kept for taxes. The image is a private file
    // (login-checked download, never disk-cached), like vault documents.

    public const RECEIPT_MAX_BYTES = 20 * 1024 * 1024; // 20 MB
    public const RECEIPT_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic',
        'application/pdf',
    ];

    // Validate and store an uploaded receipt image ($_FILES entry).
    // Returns the new private_files id.
    public static function storeUploadedReceiptImage(?UserContext $ctx, array $file): int {
        $ctx = self::assertLoggedIn($ctx);
        return Files::storeUploadedPrivateFile($ctx->id, $file, self::RECEIPT_MAX_BYTES, self::RECEIPT_MIME_TYPES);
    }

    // Normalize/validate receipt form fields. Returns [title, description]
    private static function normalizeReceiptFields(array $data): array {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Receipt title is required.');
        }

        $description = trim((string)($data['description'] ?? ''));

        return [$title, $description !== '' ? $description : null];
    }

    public static function addReceipt(?UserContext $ctx, int $assetId, array $data, ?int $privateFileId): int {
        $ctx = self::assertLoggedIn($ctx);
        if (!self::getAsset($assetId)) {
            throw new InvalidArgumentException('Asset not found.');
        }
        [$title, $description] = self::normalizeReceiptFields($data);

        $st = self::pdo()->prepare(
            'INSERT INTO asset_receipts (asset_id, title, description, private_file_id, created_by_user_id)
             VALUES (?,?,?,?,?)'
        );
        $st->execute([$assetId, $title, $description, $privateFileId, $ctx->id]);
        $id = (int)self::pdo()->lastInsertId();

        self::log('asset.receipt_add', $assetId, ['receipt_id' => $id, 'title' => $title]);
        return $id;
    }

    // Update a receipt's title/description; when $newPrivateFileId is provided
    // the image is replaced and the old file's bytes are deleted.
    public static function updateReceipt(?UserContext $ctx, int $receiptId, array $data, ?int $newPrivateFileId = null): bool {
        self::assertLoggedIn($ctx);
        $existing = self::getReceipt($receiptId);
        if (!$existing) return false;

        [$title, $description] = self::normalizeReceiptFields($data);

        if ($newPrivateFileId !== null) {
            $st = self::pdo()->prepare(
                'UPDATE asset_receipts SET title=?, description=?, private_file_id=? WHERE id=?'
            );
            $ok = $st->execute([$title, $description, $newPrivateFileId, $receiptId]);
            if ($ok && !empty($existing['private_file_id'])) {
                Files::deletePrivateFile((int)$existing['private_file_id']);
            }
        } else {
            $st = self::pdo()->prepare('UPDATE asset_receipts SET title=?, description=? WHERE id=?');
            $ok = $st->execute([$title, $description, $receiptId]);
        }

        if ($ok) {
            self::log('asset.receipt_update', (int)$existing['asset_id'], [
                'receipt_id' => $receiptId,
                'title' => $title,
                'image_replaced' => $newPrivateFileId !== null,
            ]);
        }
        return $ok;
    }

    public static function removeReceipt(?UserContext $ctx, int $receiptId): bool {
        self::assertLoggedIn($ctx);
        $receipt = self::getReceipt($receiptId);
        if (!$receipt) return false;

        $del = self::pdo()->prepare('DELETE FROM asset_receipts WHERE id = ?');
        $ok = $del->execute([$receiptId]);
        if (!$ok) return false;

        if (!empty($receipt['private_file_id'])) {
            Files::deletePrivateFile((int)$receipt['private_file_id']);
        }

        self::log('asset.receipt_remove', (int)$receipt['asset_id'], [
            'receipt_id' => $receiptId,
            'title' => $receipt['title'],
        ]);
        return true;
    }

    // One receipt with its image metadata (no blob)
    public static function getReceipt(int $receiptId): ?array {
        $st = self::pdo()->prepare(
            'SELECT ar.*, pf.original_filename, pf.content_type, pf.byte_length
             FROM asset_receipts ar
             LEFT JOIN private_files pf ON pf.id = ar.private_file_id
             WHERE ar.id = ? LIMIT 1'
        );
        $st->execute([$receiptId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    // Receipts for an asset, newest first, with image metadata (no blobs)
    public static function listReceipts(int $assetId): array {
        $st = self::pdo()->prepare(
            'SELECT ar.*, pf.original_filename, pf.content_type, pf.byte_length
             FROM asset_receipts ar
             LEFT JOIN private_files pf ON pf.id = ar.private_file_id
             WHERE ar.asset_id = ?
             ORDER BY ar.created_at DESC, ar.id DESC'
        );
        $st->execute([$assetId]);
        return $st->fetchAll();
    }
}
