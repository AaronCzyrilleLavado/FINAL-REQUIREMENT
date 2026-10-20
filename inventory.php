<?php
require 'includes/config.php'; require_staff_or_admin();
$title = 'Inventory'; $errors = []; $action = $_GET['action'] ?? 'list';
$row = ['inventory_id' => '', 'supplier_id' => '', 'item_name' => '', 'unit' => '', 'quantity' => '0', 'reorder_level' => '10'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['do'] ?? '') === 'delete') {
            q("DELETE FROM inventory_items WHERE inventory_id=?", 'i', [(int)$_POST['id']]);
            flash('success', 'Inventory item deleted.'); redirect('inventory.php');
        }
        $row = ['inventory_id' => (int)($_POST['inventory_id'] ?? 0), 'supplier_id' => (int)($_POST['supplier_id'] ?? 0), 'item_name' => trim($_POST['item_name'] ?? ''),
                'unit' => trim($_POST['unit'] ?? ''), 'quantity' => trim($_POST['quantity'] ?? ''), 'reorder_level' => trim($_POST['reorder_level'] ?? '')];
        if ($row['item_name'] === '') $errors[] = 'Item name is required.';
        if ($row['unit'] === '')      $errors[] = 'Unit is required (kg, pcs, liter...).';
        if ($row['supplier_id'] <= 0) $errors[] = 'Please choose a supplier.';
        if (!is_numeric($row['quantity']) || $row['quantity'] < 0)           $errors[] = 'Quantity must be 0 or more.';
        if (!is_numeric($row['reorder_level']) || $row['reorder_level'] < 0) $errors[] = 'Reorder level must be 0 or more.';
        if (!$errors) {
            if ($row['inventory_id']) q("UPDATE inventory_items SET supplier_id=?, item_name=?, unit=?, quantity=?, reorder_level=? WHERE inventory_id=?", 'issddi',
                                        [$row['supplier_id'], $row['item_name'], $row['unit'], $row['quantity'], $row['reorder_level'], $row['inventory_id']]);
            else                       q("INSERT INTO inventory_items (supplier_id, item_name, unit, quantity, reorder_level) VALUES (?,?,?,?,?)", 'issdd',
                                        [$row['supplier_id'], $row['item_name'], $row['unit'], $row['quantity'], $row['reorder_level']]);
            flash('success', 'Inventory item saved.'); redirect('inventory.php');
        }
        $action = $row['inventory_id'] ? 'edit' : 'add';
    } catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex); }
}
if ($action === 'edit' && !$errors) {
    $row = one("SELECT * FROM inventory_items WHERE inventory_id=?", 'i', [(int)($_GET['id'] ?? 0)]);
    if (!$row) { flash('danger', 'Inventory item not found.'); redirect('inventory.php'); }
}
$sups = rows(q("SELECT * FROM suppliers ORDER BY supplier_name"));
$search = trim($_GET['search'] ?? ''); $sup = (int)($_GET['sup'] ?? 0); $stock = $_GET['stock'] ?? '';
$sql = "SELECT i.*, s.supplier_name FROM inventory_items i JOIN suppliers s ON s.supplier_id=i.supplier_id WHERE i.item_name LIKE ?";
$t = 's'; $p = ["%$search%"];
if ($sup) { $sql .= " AND i.supplier_id=?"; $t .= 'i'; $p[] = $sup; }
if ($stock === 'low') $sql .= " AND i.quantity<=i.reorder_level"; elseif ($stock === 'ok') $sql .= " AND i.quantity>i.reorder_level";
$list = rows(q($sql . " ORDER BY i.item_name", $t, $p));
include 'includes/header.php';
?>
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:640px"><div class="card-body">
  <h4><?= $action === 'add' ? 'Add' : 'Edit' ?> inventory item</h4>
  <form method="post"><input type="hidden" name="inventory_id" value="<?= e($row['inventory_id']) ?>">
    <label class="form-label">Item name</label><input class="form-control mb-2" name="item_name" value="<?= e($row['item_name']) ?>">
    <label class="form-label">Supplier</label><select class="form-select mb-2" name="supplier_id"><option value="">Select...</option>
      <?php foreach ($sups as $s): ?><option value="<?= $s['supplier_id'] ?>" <?= $row['supplier_id'] == $s['supplier_id'] ? 'selected' : '' ?>><?= e($s['supplier_name']) ?></option><?php endforeach; ?></select>
    <div class="row g-2 mb-3"><div class="col"><label class="form-label">Unit</label><input class="form-control" name="unit" value="<?= e($row['unit']) ?>"></div>
      <div class="col"><label class="form-label">Quantity</label><input class="form-control" name="quantity" value="<?= e($row['quantity']) ?>"></div>
      <div class="col"><label class="form-label">Reorder level</label><input class="form-control" name="reorder_level" value="<?= e($row['reorder_level']) ?>"></div></div>
    <button class="btn btn-yo">Save</button> <a class="btn btn-outline-secondary" href="inventory.php">Cancel</a></form>
</div></div>
<?php else: ?>
<div class="d-flex justify-content-between mb-3"><h3>Inventory</h3><a class="btn btn-yo" href="?action=add">+ Add item</a></div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input class="form-control" name="search" placeholder="Search ingredients..." value="<?= e($search) ?>"></div>
  <div class="col-md-3"><select class="form-select" name="sup"><option value="0">All suppliers</option><?php foreach ($sups as $s): ?><option value="<?= $s['supplier_id'] ?>" <?= $sup == $s['supplier_id'] ? 'selected' : '' ?>><?= e($s['supplier_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select" name="stock"><option value="">Any stock</option><option value="low" <?= $stock === 'low' ? 'selected' : '' ?>>Low stock</option><option value="ok" <?= $stock === 'ok' ? 'selected' : '' ?>>Sufficient</option></select></div>
  <div class="col-md-3"><button class="btn btn-yo">Filter</button> <a class="btn btn-outline-secondary" href="inventory.php">Reset</a></div>
</form>
<div class="card"><table class="table table-hover align-middle mb-0"><thead><tr><th>Item</th><th>Supplier</th><th>On hand</th><th>Status</th><th width="230">Actions</th></tr></thead><tbody>
<?php foreach ($list as $i): $low = $i['quantity'] <= $i['reorder_level']; ?>
<tr><td><b><?= e($i['item_name']) ?></b></td><td><?= e($i['supplier_name']) ?></td><td><?= e($i['quantity'] + 0) ?> <?= e($i['unit']) ?> <small class="text-muted">(reorder at <?= e($i['reorder_level'] + 0) ?>)</small></td>
  <td><?= $low ? "<span class='badge bg-danger'>Low stock</span>" : "<span class='badge bg-success'>OK</span>" ?></td>
  <td><a class="btn btn-sm btn-outline-secondary" href="inventory_view.php?id=<?= $i['inventory_id'] ?>">View / Stock</a>
  <a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?= $i['inventory_id'] ?>">Edit</a>
  <form method="post" class="d-inline" onsubmit="return confirm('Delete this item and its history?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $i['inventory_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
<?php endforeach; if (!$list) echo "<tr><td colspan='5' class='text-center text-muted'>No inventory items found.</td></tr>"; ?>
</tbody></table></div>
<?php endif; include 'includes/footer.php'; ?>
