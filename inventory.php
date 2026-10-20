<?php
require 'includes/config.php'; require_staff_or_admin();
$title = 'Inventory'; $errors = []; $action = $_GET['action'] ?? 'list';
$row = ['inventory_id' => '', 'supplier_id' => '', 'item_name' => '', 'item_type' => 'ingredient', 'unit' => '', 'quantity' => '0', 'reorder_level' => '10'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inTransaction = false;
    try {
        if (($_POST['do'] ?? '') === 'delete') {
            q("DELETE FROM inventory_items WHERE inventory_id=?", 'i', [(int)$_POST['id']]);
            flash('success', 'Inventory item deleted.'); redirect('inventory.php');
        }
        $row = ['inventory_id' => (int)($_POST['inventory_id'] ?? 0), 'supplier_id' => (int)($_POST['supplier_id'] ?? 0), 'item_name' => trim($_POST['item_name'] ?? ''),
                'item_type' => $_POST['item_type'] ?? '',
                'unit' => trim($_POST['unit'] ?? ''), 'quantity' => trim($_POST['quantity'] ?? ''), 'reorder_level' => trim($_POST['reorder_level'] ?? '')];
        if ($row['item_name'] === '') $errors[] = 'Item name is required.';
        if (!in_array($row['item_type'], ['ingredient', 'item'], true)) $errors[] = 'Choose Ingredient or Item.';
        if ($row['unit'] === '')      $errors[] = 'Unit is required (kg, pcs, liter...).';
        if ($row['supplier_id'] <= 0) $errors[] = 'Please choose a supplier.';
        if (!$row['inventory_id'] && (!is_numeric($row['quantity']) || $row['quantity'] < 0 || $row['quantity'] > 9999999.999)) $errors[] = 'Initial quantity must be between 0 and 9,999,999.999.';
        if (!is_numeric($row['reorder_level']) || $row['reorder_level'] < 0 || $row['reorder_level'] > 9999999.999) $errors[] = 'Reorder level must be between 0 and 9,999,999.999.';
        if (!$errors) {
            mysqli_begin_transaction($conn); $inTransaction = true;
            if ($row['inventory_id']) {
                $existing = one("SELECT item_type, quantity FROM inventory_items WHERE inventory_id=? FOR UPDATE", 'i', [$row['inventory_id']]);
                if (!$existing) throw new RuntimeException('Inventory item not found.');
                $row['quantity'] = $existing['quantity'];
                if ($existing['item_type'] === 'ingredient' && $row['item_type'] === 'item'
                    && (int)val("SELECT COUNT(*) FROM menu_ingredients WHERE inventory_id=" . (int)$row['inventory_id']) > 0) {
                    throw new RuntimeException('This ingredient is used in menu recipes. Remove it from those recipes before changing it to an item.');
                }
                q("UPDATE inventory_items SET supplier_id=?, item_name=?, item_type=?, unit=?, reorder_level=? WHERE inventory_id=?", 'isssdi',
                  [$row['supplier_id'], $row['item_name'], $row['item_type'], $row['unit'], $row['reorder_level'], $row['inventory_id']]);
                $message = 'Inventory details updated. Use View / Stock to record stock changes.';
            } else {
                q("INSERT INTO inventory_items (supplier_id, item_name, item_type, unit, quantity, reorder_level) VALUES (?,?,?,?,?,?)", 'isssdd',
                  [$row['supplier_id'], $row['item_name'], $row['item_type'], $row['unit'], $row['quantity'], $row['reorder_level']]);
                $newId = mysqli_insert_id($conn);
                if ((float)$row['quantity'] > 0) {
                    q("INSERT INTO inventory_logs (inventory_id, account_id, movement, qty, remarks) VALUES (?,?, 'IN', ?, ?)", 'iids',
                      [$newId, (int)$_SESSION['account_id'], $row['quantity'], 'Initial stock']);
                }
                $message = 'Inventory ingredient/item created.';
            }
            mysqli_commit($conn); $inTransaction = false;
            flash('success', $message); redirect('inventory.php');
        }
        $action = $row['inventory_id'] ? 'edit' : 'add';
    } catch (mysqli_sql_exception $ex) {
        if ($inTransaction) mysqli_rollback($conn);
        $errors[] = db_error_message($ex);
        $action = $row['inventory_id'] ? 'edit' : 'add';
    } catch (RuntimeException $ex) {
        if ($inTransaction) mysqli_rollback($conn);
        $errors[] = $ex->getMessage();
        $action = $row['inventory_id'] ? 'edit' : 'add';
    }
}
if ($action === 'edit' && !$errors) {
    $row = one("SELECT * FROM inventory_items WHERE inventory_id=?", 'i', [(int)($_GET['id'] ?? 0)]);
    if (!$row) { flash('danger', 'Inventory item not found.'); redirect('inventory.php'); }
    $row['quantity'] = (string)$row['quantity'];
}
$sups = rows(q("SELECT * FROM suppliers ORDER BY supplier_name"));
$search = trim($_GET['search'] ?? ''); $sup = (int)($_GET['sup'] ?? 0); $stock = $_GET['stock'] ?? '';
$sql = "SELECT i.*, s.supplier_name FROM inventory_items i JOIN suppliers s ON s.supplier_id=i.supplier_id WHERE i.item_name LIKE ?";
$t = 's'; $p = ["%$search%"]; $type = $_GET['type'] ?? '';
if ($sup) { $sql .= " AND i.supplier_id=?"; $t .= 'i'; $p[] = $sup; }
if (in_array($type, ['ingredient', 'item'], true)) { $sql .= " AND i.item_type=?"; $t .= 's'; $p[] = $type; }
if ($stock === 'low') $sql .= " AND i.quantity<=i.reorder_level"; elseif ($stock === 'ok') $sql .= " AND i.quantity>i.reorder_level";
$list = rows(q($sql . " ORDER BY i.item_name", $t, $p));
include 'includes/header.php';
?>
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:640px"><div class="card-body p-4">
  <h4><?= $action === 'add' ? 'Add Inventory Item' : 'Edit Inventory Record' ?></h4>
  <p class="text-muted mb-3"><?= $action === 'add' ? 'Add a new ingredient or item to track in inventory.' : 'Update inventory item details.' ?></p>
  <form method="post"><input type="hidden" name="inventory_id" value="<?= e($row['inventory_id']) ?>">
    <div class="row g-3">
      <div class="col-12"><label class="form-label">Item name</label><input class="form-control" name="item_name" value="<?= e($row['item_name']) ?>" required placeholder="Enter item name"></div>
      <div class="col-md-6"><label class="form-label">Stock type</label><select class="form-select" name="item_type" required>
        <option value="ingredient" <?= $row['item_type'] === 'ingredient' ? 'selected' : '' ?>>Ingredient (menu recipes)</option>
        <option value="item" <?= $row['item_type'] === 'item' ? 'selected' : '' ?>>Item (tracked only)</option>
      </select></div>
      <div class="col-md-6"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="">Select supplier...</option>
        <?php foreach ($sups as $s): ?><option value="<?= $s['supplier_id'] ?>" <?= $row['supplier_id'] == $s['supplier_id'] ? 'selected' : '' ?>><?= e($s['supplier_name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label">Unit</label><input class="form-control" name="unit" value="<?= e($row['unit']) ?>" placeholder="kg, pcs, liter..."></div>
      <?php if ($action === 'add'): ?>
        <div class="col-md-4"><label class="form-label">Initial stock</label><input type="number" min="0" step="0.001" class="form-control" name="quantity" value="<?= e($row['quantity']) ?>"></div>
      <?php else: ?>
        <div class="col-md-4"><label class="form-label">On hand</label><input class="form-control" value="<?= e($row['quantity']) ?>" readonly><small class="text-muted">Use View / Stock to adjust.</small></div>
      <?php endif; ?>
      <div class="col-md-4"><label class="form-label">Reorder level</label><input type="number" min="0" step="0.001" class="form-control" name="reorder_level" value="<?= e($row['reorder_level']) ?>"></div>
      <div class="col-12 d-flex gap-2 pt-2">
        <button class="btn btn-yo">Save</button>
        <a class="btn btn-outline-secondary" href="inventory.php">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<?php else: ?>
<div class="page-header"><h3>Inventory</h3><a class="btn btn-yo" href="?action=add">+ Add Item</a></div>
<form class="filter-bar auto-filter" method="get">
  <div class="row g-2 align-items-center">
    <div class="col-md-3"><input type="search" class="form-control" name="search" placeholder="Search inventory..." value="<?= e($search) ?>" aria-label="Search inventory"></div>
    <div class="col-md-2"><select class="form-select" name="type" aria-label="Stock type"><option value="">All types</option><option value="ingredient" <?= $type === 'ingredient' ? 'selected' : '' ?>>Ingredients</option><option value="item" <?= $type === 'item' ? 'selected' : '' ?>>Items</option></select></div>
    <div class="col-md-2"><select class="form-select" name="sup" aria-label="Supplier"><option value="0">All suppliers</option><?php foreach ($sups as $s): ?><option value="<?= $s['supplier_id'] ?>" <?= $sup == $s['supplier_id'] ? 'selected' : '' ?>><?= e($s['supplier_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="stock" aria-label="Stock level"><option value="">Any stock</option><option value="low" <?= $stock === 'low' ? 'selected' : '' ?>>Low stock</option><option value="ok" <?= $stock === 'ok' ? 'selected' : '' ?>>Sufficient</option></select></div>
    <div class="col-md-3"><a class="reset-link" href="inventory.php">↺ Reset Filters</a></div>
  </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Ingredient / Item</th><th>Type</th><th>Supplier</th><th>On Hand</th><th>Status</th><th width="230">Actions</th></tr></thead><tbody>
<?php foreach ($list as $i): $low = $i['quantity'] <= $i['reorder_level']; ?>
<tr><td><b><?= e($i['item_name']) ?></b></td><td><span class="badge bg-<?= $i['item_type'] === 'ingredient' ? 'info text-dark' : 'secondary' ?>"><?= e(ucfirst($i['item_type'])) ?></span></td><td><?= e($i['supplier_name']) ?></td><td><?= e($i['quantity'] + 0) ?> <?= e($i['unit']) ?> <small class="text-muted">(reorder at <?= e($i['reorder_level'] + 0) ?>)</small></td>
  <td><?= $low ? "<span class='badge bg-danger'>Low stock</span>" : "<span class='badge bg-success'>OK</span>" ?></td>
  <td>
    <div class="action-btns">
      <a class="btn btn-sm btn-outline-secondary" href="inventory_view.php?id=<?= $i['inventory_id'] ?>">View / Stock</a>
      <a class="btn btn-sm btn-outline-primary" href="?action=edit&amp;id=<?= $i['inventory_id'] ?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Delete this item and its history?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $i['inventory_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
    </div>
  </td></tr>
<?php endforeach; if (!$list) echo "<tr><td colspan='6' class='empty-state'><p>No inventory items found.</p></td></tr>"; ?>
</tbody></table></div></div>
<?php endif; include 'includes/footer.php'; ?>
