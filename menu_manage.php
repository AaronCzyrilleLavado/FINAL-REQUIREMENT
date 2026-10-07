<?php
require 'includes/config.php'; require_admin();
$title = 'Manage Menu'; $errors = []; $action = $_GET['action'] ?? 'list';
$row = ['menu_id' => '', 'category_id' => '', 'item_name' => '', 'description' => '', 'price' => '', 'is_available' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['do'] ?? '') === 'delete') {
            q("DELETE FROM menu_items WHERE menu_id=?", 'i', [(int)$_POST['id']]);
            flash('success', 'Menu item deleted.'); redirect('menu_manage.php');
        }
        $row = ['menu_id' => (int)($_POST['menu_id'] ?? 0), 'category_id' => (int)($_POST['category_id'] ?? 0), 'item_name' => trim($_POST['item_name'] ?? ''),
                'description' => trim($_POST['description'] ?? ''), 'price' => trim($_POST['price'] ?? ''), 'is_available' => isset($_POST['is_available']) ? 1 : 0];
        if ($row['item_name'] === '')  $errors[] = 'Item name is required.';
        if ($row['category_id'] <= 0)  $errors[] = 'Please choose a category.';
        if (!is_numeric($row['price']) || $row['price'] <= 0 || $row['price'] > 99999) $errors[] = 'Price must be a number greater than 0.';
        if (!$errors) {
            if ($row['menu_id']) q("UPDATE menu_items SET category_id=?, item_name=?, description=?, price=?, is_available=? WHERE menu_id=?", 'issdii',
                                   [$row['category_id'], $row['item_name'], $row['description'], $row['price'], $row['is_available'], $row['menu_id']]);
            else                 q("INSERT INTO menu_items (category_id, item_name, description, price, is_available) VALUES (?,?,?,?,?)", 'issdi',
                                   [$row['category_id'], $row['item_name'], $row['description'], $row['price'], $row['is_available']]);
            flash('success', 'Menu item saved.'); redirect('menu_manage.php');
        }
        $action = $row['menu_id'] ? 'edit' : 'add';
    } catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex, 'Cannot delete: this item appears in past orders. Mark it unavailable instead.'); }
}
if ($action === 'edit' && !$errors) {
    $row = one("SELECT * FROM menu_items WHERE menu_id=?", 'i', [(int)($_GET['id'] ?? 0)]);
    if (!$row) { flash('danger', 'Menu item not found.'); redirect('menu_manage.php'); }
}
$cats = rows(q("SELECT * FROM categories ORDER BY category_name"));
$search = trim($_GET['search'] ?? ''); $cat = (int)($_GET['cat'] ?? 0); $avail = $_GET['avail'] ?? '';
$sql = "SELECT m.*, c.category_name FROM menu_items m JOIN categories c ON c.category_id=m.category_id WHERE (m.item_name LIKE ? OR m.description LIKE ?)";
$t = 'ss'; $p = ["%$search%", "%$search%"];
if ($cat) { $sql .= " AND m.category_id=?"; $t .= 'i'; $p[] = $cat; }
if ($avail === '0' || $avail === '1') { $sql .= " AND m.is_available=?"; $t .= 'i'; $p[] = (int)$avail; }
$list = rows(q($sql . " ORDER BY c.category_name, m.item_name", $t, $p));
include 'includes/header.php';
?>
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:640px"><div class="card-body">
  <h4><?= $action === 'add' ? 'Add' : 'Edit' ?> menu item</h4>
  <form method="post"><input type="hidden" name="menu_id" value="<?= e($row['menu_id']) ?>">
    <label class="form-label">Item name</label><input class="form-control mb-2" name="item_name" value="<?= e($row['item_name']) ?>">
    <label class="form-label">Category</label><select class="form-select mb-2" name="category_id"><option value="">Select...</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>" <?= $row['category_id'] == $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select>
    <label class="form-label">Description</label><input class="form-control mb-2" name="description" value="<?= e($row['description']) ?>">
    <label class="form-label">Price (₱)</label><input class="form-control mb-2" name="price" value="<?= e($row['price']) ?>">
    <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_available" id="av" <?= $row['is_available'] ? 'checked' : '' ?>><label for="av" class="form-check-label">Available for ordering</label></div>
    <button class="btn btn-yo">Save</button> <a class="btn btn-outline-secondary" href="menu_manage.php">Cancel</a></form>
</div></div>
<?php else: ?>
<div class="d-flex justify-content-between mb-3"><h3>Menu Items</h3><a class="btn btn-yo" href="?action=add">+ Add item</a></div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input class="form-control" name="search" placeholder="Search..." value="<?= e($search) ?>"></div>
  <div class="col-md-3"><select class="form-select" name="cat"><option value="0">All categories</option><?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>" <?= $cat == $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select" name="avail"><option value="">Any status</option><option value="1" <?= $avail === '1' ? 'selected' : '' ?>>Available</option><option value="0" <?= $avail === '0' ? 'selected' : '' ?>>Unavailable</option></select></div>
  <div class="col-md-3"><button class="btn btn-yo">Filter</button> <a class="btn btn-outline-secondary" href="menu_manage.php">Reset</a></div>
</form>
<div class="card"><table class="table table-hover align-middle mb-0"><thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th><th width="160">Actions</th></tr></thead><tbody>
<?php foreach ($list as $m): ?><tr><td><b><?= e($m['item_name']) ?></b><br><small class="text-muted"><?= e($m['description']) ?></small></td><td><?= e($m['category_name']) ?></td><td><?= peso($m['price']) ?></td>
  <td><?= $m['is_available'] ? "<span class='badge bg-success'>Available</span>" : "<span class='badge bg-secondary'>Unavailable</span>" ?></td>
  <td><a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?= $m['menu_id'] ?>">Edit</a>
  <form method="post" class="d-inline" onsubmit="return confirm('Delete this item?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $m['menu_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
<?php endforeach; if (!$list) echo "<tr><td colspan='5' class='text-center text-muted'>No menu items found.</td></tr>"; ?>
</tbody></table></div>
<?php endif; include 'includes/footer.php'; ?>
