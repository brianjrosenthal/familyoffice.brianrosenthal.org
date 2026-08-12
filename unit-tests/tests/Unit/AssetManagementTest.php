<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/AssetManagement.php';
require_once __DIR__ . '/../../../www/lib/Files.php';

final class AssetManagementTest extends TestCase
{
    private UserContext $ctx;

    protected function setUp(): void
    {
        test_reset_users();
        pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
        pdo()->exec('TRUNCATE TABLE asset_photos');
        pdo()->exec('TRUNCATE TABLE asset_receipts');
        pdo()->exec('TRUNCATE TABLE assets');
        pdo()->exec('TRUNCATE TABLE public_files');
        pdo()->exec('TRUNCATE TABLE private_files');
        pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, email_verified_at)
                     VALUES ('Test', 'User', 'test@example.com', 'hash', NOW())");
        $this->ctx = new UserContext((int)pdo()->lastInsertId(), false);
        UserContext::set($this->ctx);
    }

    public function testCreateAndGetAsset(): void
    {
        $id = AssetManagement::createAsset($this->ctx, [
            'name' => 'Water Heater',
            'category' => 'Appliance',
            'description' => 'Basement, installed 2020',
            'purchase_date' => '2020-06-15',
            'purchase_price' => '1,250.50',
            'warranty_info' => '10 year parts',
        ]);

        $asset = AssetManagement::getAsset($id);
        $this->assertSame('Water Heater', $asset['name']);
        $this->assertSame('Appliance', $asset['category']);
        $this->assertSame('2020-06-15', $asset['purchase_date']);
        $this->assertSame('1250.50', $asset['purchase_price']);
    }

    public function testCreateAssetRequiresName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AssetManagement::createAsset($this->ctx, ['name' => '  ']);
    }

    public function testCreateAssetRejectsNegativePrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AssetManagement::createAsset($this->ctx, ['name' => 'X', 'purchase_price' => '-5']);
    }

    public function testCreateAssetRequiresLogin(): void
    {
        $this->expectException(RuntimeException::class);
        AssetManagement::createAsset(null, ['name' => 'X']);
    }

    public function testUpdateAsset(): void
    {
        $id = AssetManagement::createAsset($this->ctx, ['name' => 'Old Name']);
        AssetManagement::updateAsset($this->ctx, $id, ['name' => 'New Name', 'category' => 'Vehicle']);

        $asset = AssetManagement::getAsset($id);
        $this->assertSame('New Name', $asset['name']);
        $this->assertSame('Vehicle', $asset['category']);
    }

    public function testListAssetsSearchAndPhotoCount(): void
    {
        $a = AssetManagement::createAsset($this->ctx, ['name' => 'Honda Odyssey', 'category' => 'Vehicle']);
        AssetManagement::createAsset($this->ctx, ['name' => 'Roof']);

        $fileId = Files::insertPublicFile('fakebytes', 'image/jpeg', 'car.jpg', $this->ctx->id);
        AssetManagement::addPhoto($this->ctx, $a, $fileId);

        $all = AssetManagement::listAssets();
        $this->assertCount(2, $all);

        $matches = AssetManagement::listAssets('honda');
        $this->assertCount(1, $matches);
        $this->assertSame(1, (int)$matches[0]['photo_count']);
        $this->assertSame($fileId, (int)$matches[0]['first_photo_file_id']);
    }

    public function testDeleteAssetCascadesPhotos(): void
    {
        $id = AssetManagement::createAsset($this->ctx, ['name' => 'Boiler']);
        $fileId = Files::insertPublicFile('fakebytes', 'image/jpeg', 'boiler.jpg', $this->ctx->id);
        AssetManagement::addPhoto($this->ctx, $id, $fileId);

        $this->assertTrue(AssetManagement::deleteAsset($this->ctx, $id));
        $this->assertNull(AssetManagement::getAsset($id));
        $this->assertSame([], AssetManagement::listPhotos($id));
    }

    public function testRemovePhoto(): void
    {
        $id = AssetManagement::createAsset($this->ctx, ['name' => 'HVAC']);
        $fileId = Files::insertPublicFile('fakebytes', 'image/png', 'unit.png', $this->ctx->id);
        $photoId = AssetManagement::addPhoto($this->ctx, $id, $fileId);

        $this->assertTrue(AssetManagement::removePhoto($this->ctx, $photoId));
        $this->assertSame([], AssetManagement::listPhotos($id));
        $this->assertFalse(AssetManagement::removePhoto($this->ctx, $photoId));
    }

    // ===== Receipts =====

    private function receiptFileId(string $name = 'receipt.jpg'): int
    {
        return Files::insertPrivateFile('fakebytes', 'image/jpeg', $name, $this->ctx->id);
    }

    public function testAddAndListReceipts(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Water Heater']);
        $fileId = $this->receiptFileId('home-depot.jpg');

        $receiptId = AssetManagement::addReceipt($this->ctx, $assetId, [
            'title' => 'Home Depot — water heater',
            'description' => 'Paid by check, includes install labor',
        ], $fileId);

        $receipts = AssetManagement::listReceipts($assetId);
        $this->assertCount(1, $receipts);
        $this->assertSame($receiptId, (int)$receipts[0]['id']);
        $this->assertSame('Home Depot — water heater', $receipts[0]['title']);
        $this->assertSame('Paid by check, includes install labor', $receipts[0]['description']);
        $this->assertSame($fileId, (int)$receipts[0]['private_file_id']);
        // Image metadata comes along for display without loading the blob
        $this->assertSame('home-depot.jpg', $receipts[0]['original_filename']);
        $this->assertSame('image/jpeg', $receipts[0]['content_type']);
    }

    public function testAddReceiptRequiresTitle(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Roof']);
        $this->expectException(InvalidArgumentException::class);
        AssetManagement::addReceipt($this->ctx, $assetId, ['title' => '  '], $this->receiptFileId());
    }

    public function testAddReceiptRequiresExistingAsset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AssetManagement::addReceipt($this->ctx, 999999, ['title' => 'Orphan'], $this->receiptFileId());
    }

    public function testAddReceiptRequiresLogin(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Boiler']);
        $this->expectException(RuntimeException::class);
        AssetManagement::addReceipt(null, $assetId, ['title' => 'X'], null);
    }

    public function testUpdateReceiptMetadataKeepsImage(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Furnace']);
        $fileId = $this->receiptFileId();
        $receiptId = AssetManagement::addReceipt($this->ctx, $assetId, ['title' => 'Old title'], $fileId);

        $this->assertTrue(AssetManagement::updateReceipt($this->ctx, $receiptId, [
            'title' => 'New title',
            'description' => 'Now with detail',
        ]));

        $receipt = AssetManagement::getReceipt($receiptId);
        $this->assertSame('New title', $receipt['title']);
        $this->assertSame('Now with detail', $receipt['description']);
        $this->assertSame($fileId, (int)$receipt['private_file_id']);
        $this->assertNotNull(Files::getPrivateFileMeta($fileId));
    }

    public function testUpdateReceiptReplacesImageAndDeletesOldBytes(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Dishwasher']);
        $oldFileId = $this->receiptFileId('old.jpg');
        $newFileId = $this->receiptFileId('new.jpg');
        $receiptId = AssetManagement::addReceipt($this->ctx, $assetId, ['title' => 'Receipt'], $oldFileId);

        $this->assertTrue(AssetManagement::updateReceipt($this->ctx, $receiptId, ['title' => 'Receipt'], $newFileId));

        $receipt = AssetManagement::getReceipt($receiptId);
        $this->assertSame($newFileId, (int)$receipt['private_file_id']);
        $this->assertNull(Files::getPrivateFileMeta($oldFileId));
    }

    public function testUpdateReceiptReturnsFalseWhenMissing(): void
    {
        $this->assertFalse(AssetManagement::updateReceipt($this->ctx, 999999, ['title' => 'Nope']));
    }

    public function testRemoveReceiptDeletesImageBytes(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Deck']);
        $fileId = $this->receiptFileId();
        $receiptId = AssetManagement::addReceipt($this->ctx, $assetId, ['title' => 'Lumber'], $fileId);

        $this->assertTrue(AssetManagement::removeReceipt($this->ctx, $receiptId));
        $this->assertNull(AssetManagement::getReceipt($receiptId));
        $this->assertSame([], AssetManagement::listReceipts($assetId));
        $this->assertNull(Files::getPrivateFileMeta($fileId));
        $this->assertFalse(AssetManagement::removeReceipt($this->ctx, $receiptId));
    }

    public function testDeleteAssetRemovesReceiptsAndTheirImageBytes(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Minivan']);
        $fileId = $this->receiptFileId();
        AssetManagement::addReceipt($this->ctx, $assetId, ['title' => 'Dealer invoice'], $fileId);

        $this->assertTrue(AssetManagement::deleteAsset($this->ctx, $assetId));
        $this->assertSame([], AssetManagement::listReceipts($assetId));
        $this->assertNull(Files::getPrivateFileMeta($fileId));
    }

    public function testListAssetsCountsPhotosAndReceiptsIndependently(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Sailboat']);
        AssetManagement::addPhoto($this->ctx, $assetId, Files::insertPublicFile('a', 'image/jpeg', 'a.jpg', $this->ctx->id));
        AssetManagement::addPhoto($this->ctx, $assetId, Files::insertPublicFile('b', 'image/jpeg', 'b.jpg', $this->ctx->id));
        AssetManagement::addReceipt($this->ctx, $assetId, ['title' => 'Purchase'], $this->receiptFileId());
        AssetManagement::addReceipt($this->ctx, $assetId, ['title' => 'Mooring fee'], $this->receiptFileId());
        AssetManagement::addReceipt($this->ctx, $assetId, ['title' => 'Winter storage'], $this->receiptFileId());

        $row = AssetManagement::listAssets('Sailboat')[0];
        $this->assertSame(2, (int)$row['photo_count']);
        $this->assertSame(3, (int)$row['receipt_count']);
    }
}
