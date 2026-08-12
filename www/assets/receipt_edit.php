<?php
// Edit an asset receipt — title, description, and image replacement.
// Evaluates to assets/receipt_edit_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

$receiptId = (int)($_GET['id'] ?? 0);
$receipt = $receiptId > 0 ? AssetManagement::getReceipt($receiptId) : null;
if (!$receipt) {
    $_SESSION['error'] = 'Receipt not found.';
    header('Location: /assets/');
    exit;
}

$assetId = (int)$receipt['asset_id'];
$asset = AssetManagement::getAsset($assetId);

// One-shot flash + form repopulation from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

$val = function (string $key) use ($form, $receipt) {
    return $form[$key] ?? ($receipt[$key] ?? '');
};

$isImage = str_starts_with((string)($receipt['content_type'] ?? ''), 'image/');

header_html('Edit Receipt');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Edit Receipt</h2>
  <a class="button" href="/assets/edit.php?id=<?= (int)$assetId ?>">Back to <?=h($asset['name'] ?? 'Asset')?></a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/assets/receipt_edit_eval.php" enctype="multipart/form-data" class="stack" id="receiptForm" data-warn-unsaved>
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$receiptId ?>">
    <label>Title
      <input type="text" name="title" value="<?=h($val('title'))?>" required>
    </label>
    <label>Description
      <textarea name="description" rows="3" placeholder="What was purchased, from whom, and anything a tax preparer would want to know"><?=h($val('description'))?></textarea>
    </label>

    <div>
      <strong>Current image:</strong>
      <?php if (!empty($receipt['private_file_id'])): ?>
        <div style="margin-top:6px;">
          <?php if ($isImage): ?>
            <a href="/assets/receipt_image.php?receipt_id=<?= (int)$receiptId ?>" target="_blank">
              <img src="/assets/receipt_image.php?receipt_id=<?= (int)$receiptId ?>" alt="<?=h($receipt['title'])?>" style="max-width:320px;max-height:320px;border-radius:10px;display:block;">
            </a>
          <?php endif; ?>
          <div class="small">
            <a href="/assets/receipt_image.php?receipt_id=<?= (int)$receiptId ?>&download=1"><?=h($receipt['original_filename'] ?? 'Download')?></a>
          </div>
        </div>
      <?php else: ?>
        <span class="small">None</span>
      <?php endif; ?>
    </div>
    <label>Replace image (optional)
      <input type="file" name="image" accept="image/*,application/pdf">
      <small class="small">Uploading a new image permanently replaces the current one.</small>
    </label>

    <div class="actions">
      <button class="primary" type="submit" id="receiptBtn">Save Receipt</button>
      <a class="button" href="/assets/edit.php?id=<?= (int)$assetId ?>">Cancel</a>
    </div>
  </form>
</div>

<div class="card">
  <h3>Danger Zone</h3>
  <form method="post" action="/assets/receipt_remove_eval.php" onsubmit="return confirm('Delete this receipt and its image? This cannot be undone.');" data-skip-unsaved-warning>
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="receipt_id" value="<?= (int)$receiptId ?>">
    <input type="hidden" name="asset_id" value="<?= (int)$assetId ?>">
    <button class="danger" type="submit">Delete Receipt</button>
  </form>
</div>

<script>
  (function(){
    // Double-click protection for the save button
    var form = document.getElementById('receiptForm');
    var btn = document.getElementById('receiptBtn');
    if (form && btn) {
      form.addEventListener('submit', function(e) {
        if (btn.disabled) { e.preventDefault(); return; }
        btn.disabled = true;
        btn.textContent = 'Saving...';
      });
    }
  })();
</script>

<?php footer_html(); ?>
