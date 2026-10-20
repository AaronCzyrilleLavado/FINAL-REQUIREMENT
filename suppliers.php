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
<div class="card" style="max-width:600px"><div class="card-body p-4">
  <h4 class="mb-1"><?= $action === 'add' ? 'Add Supplier' : 'Edit Supplier' ?></h4>
  <p class="text-muted mb-3"><?= $action === 'add' ? 'Create a new ingredient or item supplier record.' : 'Update supplier contact information.' ?></p>
  <form method="post"><input type="hidden" name="supplier_id" value="<?= e($row['supplier_id']) ?>">
    <div class="row g-3">
      <div class="col-12"><label class="form-label">Supplier name</label><input class="form-control" name="supplier_name" value="<?= e($row['supplier_name']) ?>" required placeholder="Company or supplier name"></div>
      <div class="col-md-6"><label class="form-label">Contact person</label><input class="form-control" name="contact_person" value="<?= e($row['contact_person']) ?>" placeholder="Name of contact"></div>
      <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($row['phone']) ?>" placeholder="e.g. 0912 345 6789"></div>
      <div class="col-12 d-flex gap-2 pt-2">
        <button class="btn btn-yo">Save Supplier</button>
        <a class="btn btn-outline-secondary" href="suppliers.php">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<?php else: ?>
<div class="page-header"><h3>Suppliers</h3><a class="btn btn-yo" href="?action=add">+ Add Supplier</a></div>
<form class="filter-bar auto-filter" method="get">
  <div class="row g-2 align-items-center">
    <div class="col-md-8 col-lg-6">
      <input type="search" class="form-control" name="search" placeholder="Search by supplier or contact person..." value="<?= e($search) ?>">
    </div>
    <div class="col-md-4 col-lg-2">
      <a class="reset-link" href="suppliers.php">↺ Reset Filter</a>
    </div>
  </div>
</form>
<div class="row g-3">
<?php foreach ($list as $s): ?>
  <div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body d-flex flex-column">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <h5 class="mb-0"><?= e($s['supplier_name']) ?></h5>
      <span class="badge bg-secondary"><?= $s['items'] ?> item(s)</span>
    </div>
    <p class="mb-1 text-muted">👤 <?= e($s['contact_person'] ?: 'No contact specified') ?></p>
    <p class="mb-3 text-muted">📞 <?= e($s['phone'] ?: 'No phone provided') ?></p>
    <div class="mt-auto action-btns pt-2 border-top">
      <a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?= $s['supplier_id'] ?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Delete this supplier?')">
        <input type="hidden" name="do" value="delete">
        <input type="hidden" name="id" value="<?= $s['supplier_id'] ?>">
        <button class="btn btn-sm btn-outline-danger">Delete</button>
      </form>
    </div>
  </div></div></div>
<?php endforeach; if (!$list) echo "<div class='col-12'><div class='card p-4 empty-state'><p>No suppliers found.</p></div></div>"; ?>
</div>
<?php endif; include 'includes/footer.php'; ?>
