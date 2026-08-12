<?php
// Household Assets — list and search.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
require_once __DIR__ . '/../lib/Files.php';
Application::init();
require_login();

$search = trim($_GET['q'] ?? '');
$assets = AssetManagement::listAssets($search);

// One-shot flash from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

header_html('Household Assets');
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Household Assets</h2>
  <a class="button primary" href="/assets/add.php">Add Asset</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="get" data-auto-submit>
    <label>Search
      <input type="text" name="q" value="<?=h($search)?>" placeholder="Name, category, or description">
    </label>
  </form>
</div>

<?php if (empty($assets)): ?>
  <p class="small"><?= $search !== '' ? 'No assets match your search.' : 'No assets yet. Add your house, vehicles, appliances, and other property worth tracking.' ?></p>
<?php else: ?>
  <div class="card">
    <table class="list">
      <thead>
        <tr>
          <th></th>
          <th>Name</th>
          <th>Category</th>
          <th>Purchased</th>
          <th>Price</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assets as $asset): ?>
          <tr>
            <td style="width:56px;">
              <?php if (!empty($asset['first_photo_file_id'])): ?>
                <img src="<?=h(Files::publicFileImageUrl((int)$asset['first_photo_file_id'], 48))?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
              <?php endif; ?>
            </td>
            <td>
              <a href="/assets/edit.php?id=<?= (int)$asset['id'] ?>"><?=h($asset['name'])?></a>
              <?php if (!empty($asset['description'])): ?>
                <div class="small"><?=h(mb_strimwidth($asset['description'], 0, 90, '…'))?></div>
              <?php endif; ?>
              <?php if (!empty($asset['receipt_count'])): ?>
                <div class="small">🧾 <?= (int)$asset['receipt_count'] ?> receipt<?= (int)$asset['receipt_count'] === 1 ? '' : 's' ?></div>
              <?php endif; ?>
            </td>
            <td><?=h($asset['category'] ?? '')?></td>
            <td><?= $asset['purchase_date'] ? h(date('M j, Y', strtotime($asset['purchase_date']))) : '' ?></td>
            <td><?= $asset['purchase_price'] !== null ? '$' . h(number_format((float)$asset['purchase_price'], 2)) : '' ?></td>
            <td class="small" style="text-align:right;">
              <a class="button small" href="/assets/edit.php?id=<?= (int)$asset['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php footer_html(); ?>
