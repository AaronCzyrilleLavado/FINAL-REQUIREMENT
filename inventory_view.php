<?php
require 'includes/config.php';
require_staff_or_admin();
$title = 'Inventory Item';
$errors = [];
$id = (int) ($_GET['id'] ?? 0);

$item = one("SELECT i.*, s.supplier_name, s.contact_person, s.phone FROM inventory_items i JOIN suppliers s ON s.supplier_id=i.supplier_id WHERE i.inventory_id=?", 'i', [$id]);
if (!$item) {
  flash('danger', 'Inventory item not found.');
  redirect('inventory.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mv = $_POST['movement'] ?? '';
  $qty = $_POST['qty'] ?? '';
  $rem = trim($_POST['remarks'] ?? '');
  if (!in_array($mv, ['IN', 'OUT'], true))
    $errors[] = 'Choose Stock In or Stock Out.';
  if (!is_numeric($qty) || $qty <= 0 || $qty > 9999999.999)
    $errors[] = 'Quantity must be greater than 0.';
  if (!$errors) {
    mysqli_begin_transaction($conn);
    try {
      $lockedItem = one("SELECT quantity FROM inventory_items WHERE inventory_id=? FOR UPDATE", 'i', [$id]);
      if (!$lockedItem) throw new RuntimeException('Inventory item not found.');
      if ($mv === 'OUT' && (float)$qty > (float)$lockedItem['quantity']) {
        mysqli_rollback($conn);
        $errors[] = 'Cannot remove more than the current stock (' . ($lockedItem['quantity'] + 0) . ' ' . $item['unit'] . ').';
      }
      if ($errors) {
        $logs = [];
      } else {
        $sign = ($mv === 'IN') ? '+' : '-';
        $condition = ($mv === 'OUT') ? ' AND quantity>=?' : '';
        $types = ($mv === 'OUT') ? 'dii' : 'di';
        $params = ($mv === 'OUT') ? [$qty, $id, $qty] : [$qty, $id];
        q("UPDATE inventory_items SET quantity = quantity $sign ? WHERE inventory_id=?$condition", $types, $params);
        q("INSERT INTO inventory_logs (inventory_id, account_id, movement, qty, remarks) VALUES (?,?,?,?,?)", 'iisds', [$id, $_SESSION['account_id'], $mv, $qty, $rem]);
        mysqli_commit($conn);
        flash('success', "Stock $mv recorded.");
        redirect("inventory_view.php?id=$id");
      }
    } catch (mysqli_sql_exception $ex) {
      mysqli_rollback($conn);
      $errors[] = db_error_message($ex);
    } catch (RuntimeException $ex) {
      mysqli_rollback($conn);
      $errors[] = $ex->getMessage();
    }
  }
}
$logs = rows(q("SELECT l.*, a.fullname FROM inventory_logs l JOIN accounts a ON a.account_id=l.account_id WHERE l.inventory_id=? ORDER BY l.logged_at DESC, l.log_id DESC LIMIT 30", 'i', [$id]));
$pct = $item['reorder_level'] > 0 ? min(100, round($item['quantity'] / ($item['reorder_level'] * 3) * 100)) : 100;
$low = $item['quantity'] <= $item['reorder_level'];
include 'includes/header.php';
?>
<a class="btn btn-outline-secondary btn-sm mb-3" href="inventory.php">← Back to inventory</a>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-body">
        <h4><?= e($item['item_name']) ?> <?= $low ? "<span class='badge bg-danger fs-6'>Low stock</span>" : '' ?></h4>
        <span class="badge bg-<?= $item['item_type'] === 'ingredient' ? 'info text-dark' : 'secondary' ?>"><?= e(ucfirst($item['item_type'])) ?></span>
        <div class="display-6 text-center my-3"><?= e($item['quantity'] + 0) ?> <small
            class="fs-5 text-muted"><?= e($item['unit']) ?></small></div>
        <div class="progress mb-2">
          <div class="progress-bar <?= $low ? 'bg-danger' : 'bg-success' ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <small class="text-muted">Reorder level: <?= e($item['reorder_level'] + 0) ?> <?= e($item['unit']) ?></small>
        <hr><b>Supplier</b><br><?= e($item['supplier_name']) ?><br><small
          class="text-muted"><?= e($item['contact_person']) ?> · <?= e($item['phone']) ?></small>
      </div>
    </div>
    <div class="card">
      <div class="card-body">
        <h5>Record stock movement</h5>
        <form method="post">
          <div class="row g-2">
            <div class="col-6"><select name="movement" class="form-select" required>
                <option value="IN">Stock In</option>
                <option value="OUT">Stock Out</option>
              </select></div>
            <div class="col-6"><input type="number" name="qty" min="0.001" max="9999999.999" step="0.001" class="form-control" placeholder="Quantity" required></div>
            <div class="col-12"><input name="remarks" class="form-control"
                placeholder="Remarks (e.g. delivery, spoilage)" maxlength="150"></div>
            <div class="col-12"><button class="btn btn-yo w-100">Save movement</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <h5>Movement history</h5>
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Qty</th>
              <th>By</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
                    <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= e(date('M d, h:i A', strtotime($l['logged_at']))) ?></td>
                <td><span
                    class="badge bg-<?= $l['movement'] === 'IN' ? 'success' : 'danger' ?>"><?= $l['movement'] ?></span>
                </td>
                <td><?= e($l['qty'] + 0) ?></td>
                <td><?= e($l['fullname']) ?></td>
                <td><?= e($l['remarks']) ?></td>
              </tr>
                    <?php endforeach;
                    if (!$logs)
                      echo "<tr><td colspan='5' class='text-muted text-center'>No movements recorded yet.</td></tr>"; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>