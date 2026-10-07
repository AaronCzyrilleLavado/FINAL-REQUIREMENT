<?php
require 'includes/config.php'; require_admin();
$title = 'Suppliers'; $errors = []; $action = $_GET['action'] ?? 'list';
$row = ['supplier_id' => '', 'supplier_name' => '', 'contact_person' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['do'] ?? '') === 'delete') {
            q("DELETE FROM suppliers WHERE supplier_id=?", 'i', [(int)$_POST['id']]);
            flash('success', 'Supplier deleted.'); redirect('suppliers.php');
        }
        $row = ['supplier_id' => (int)($_POST['supplier_id'] ?? 0), 'supplier_name' => trim($_POST['supplier_name'] ?? ''),
                'contact_person' => trim($_POST['contact_person'] ?? ''), 'phone' => trim($_POST['phone'] ?? '')];
        if ($row['supplier_name'] === '') $errors[] = 'Supplier name is required.';
        if ($row['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $row['phone'])) $errors[] = 'Phone number format is invalid.';
        if (!$errors) {
            if ($row['supplier_id']) q("UPDATE suppliers SET supplier_name=?, contact_person=?, phone=? WHERE supplier_id=?", 'sssi', [$row['supplier_name'], $row['contact_person'], $row['phone'], $row['supplier_id']]);
            else                     q("INSERT INTO suppliers (supplier_name, contact_person, phone) VALUES (?,?,?)", 'sss', [$row['supplier_name'], $row['contact_person'], $row['phone']]);
            flash('success', 'Supplier saved.'); redirect('suppliers.php');
        }
        $action = $row['supplier_id'] ? 'edit' : 'add';
    } catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex, 'Cannot delete: this supplier still has inventory items.'); }
}
if ($action === 'edit' && !$errors) {
    $row = one("SELECT * FROM suppliers WHERE supplier_id=?", 'i', [(int)($_GET['id'] ?? 0)]);
    if (!$row) { flash('danger', 'Supplier not found.'); redirect('suppliers.php'); }
}
$search = trim($_GET['search'] ?? '');
$list = rows(q("SELECT s.*, (SELECT COUNT(*) FROM inventory_items i WHERE i.supplier_id=s.supplier_id) AS items FROM suppliers s WHERE s.supplier_name LIKE ? OR s.contact_person LIKE ? ORDER BY s.supplier_name", 'ss', ["%$search%", "%$search%"]));
include 'includes/header.php';
?>
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:600px"><div class="card-body">
  <h4><?= $action === 'add' ? 'Add' : 'Edit' ?> supplier</h4>
  <form method="post"><input type="hidden" name="supplier_id" value="<?= e($row['supplier_id']) ?>">
    <label class="form-label">Supplier name</label><input class="form-control mb-2" name="supplier_name" value="<?= e($row['supplier_name']) ?>">
    <label class="form-label">Contact person</label><input class="form-control mb-2" name="contact_person" value="<?= e($row['contact_person']) ?>">
    <label class="form-label">Phone</label><input class="form-control mb-3" name="phone" value="<?= e($row['phone']) ?>">
    <button class="btn btn-yo">Save</button> <a class="btn btn-outline-secondary" href="suppliers.php">Cancel</a></form>
</div></div>
<?php else: ?>
<div class="d-flex justify-content-between mb-3"><h3>Suppliers</h3><a class="btn btn-yo" href="?action=add">+ Add supplier</a></div>
<form class="d-flex gap-2 mb-3" method="get"><input class="form-control" name="search" placeholder="Search suppliers..." value="<?= e($search) ?>"><button class="btn btn-yo">Search</button></form>
<div class="row g-3">
<?php foreach ($list as $s): ?>
  <div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body">
    <h5><?= e($s['supplier_name']) ?></h5>
    <p class="mb-1">👤 <?= e($s['contact_person'] ?: '—') ?></p><p class="mb-2">📞 <?= e($s['phone'] ?: '—') ?></p>
    <span class="badge bg-secondary"><?= $s['items'] ?> inventory item(s)</span>
    <div class="mt-3"><a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?= $s['supplier_id'] ?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Delete this supplier?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $s['supplier_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div>
  </div></div></div>
<?php endforeach; if (!$list) echo "<p class='text-muted'>No suppliers found.</p>"; ?>
</div>
<?php endif; include 'includes/footer.php'; ?>
