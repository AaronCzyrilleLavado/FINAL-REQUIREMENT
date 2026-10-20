<?php
require 'includes/config.php'; require_admin();
$title = 'Categories'; $errors = []; $action = $_GET['action'] ?? 'list';
$row = ['category_id' => '', 'category_name' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['do'] ?? '') === 'delete') {
            q("DELETE FROM categories WHERE category_id=?", 'i', [(int)$_POST['id']]);
            flash('success', 'Category deleted.'); redirect('categories.php');
        }
        $row = ['category_id' => (int)($_POST['category_id'] ?? 0), 'category_name' => trim($_POST['category_name'] ?? ''), 'description' => trim($_POST['description'] ?? '')];
        if ($row['category_name'] === '') $errors[] = 'Category name is required.';
        if (!$errors) {
            if ($row['category_id']) q("UPDATE categories SET category_name=?, description=? WHERE category_id=?", 'ssi', [$row['category_name'], $row['description'], $row['category_id']]);
            else                     q("INSERT INTO categories (category_name, description) VALUES (?,?)", 'ss', [$row['category_name'], $row['description']]);
            flash('success', 'Category saved.'); redirect('categories.php');
        }
        $action = $row['category_id'] ? 'edit' : 'add';
    } catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex, 'Cannot delete: this category still has menu items.'); }
}
if ($action === 'edit' && !$errors) {
    $row = one("SELECT * FROM categories WHERE category_id=?", 'i', [(int)($_GET['id'] ?? 0)]);
    if (!$row) { flash('danger', 'Category not found.'); redirect('categories.php'); }
}
$search = trim($_GET['search'] ?? '');
$list = rows(q("SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id=c.category_id) AS items FROM categories c WHERE c.category_name LIKE ? ORDER BY c.category_name", 's', ["%$search%"]));
include 'includes/header.php';
?>
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:600px"><div class="card-body p-4">
  <h4><?= $action === 'add' ? 'Add Category' : 'Edit Category' ?></h4>
  <p class="text-muted mb-3"><?= $action === 'add' ? 'Create a new menu category.' : 'Update category details.' ?></p>
  <form method="post"><input type="hidden" name="category_id" value="<?= e($row['category_id']) ?>">
    <div class="row g-3">
      <div class="col-12"><label class="form-label">Name</label><input class="form-control" name="category_name" value="<?= e($row['category_name']) ?>" required placeholder="Category name"></div>
      <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($row['description']) ?>" placeholder="Brief description (optional)"></div>
      <div class="col-12 d-flex gap-2 pt-2">
        <button class="btn btn-yo">Save</button>
        <a class="btn btn-outline-secondary" href="categories.php">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<?php else: ?>
<div class="page-header"><h3>Categories</h3><a class="btn btn-yo" href="?action=add">+ Add Category</a></div>
<form class="filter-bar auto-filter" method="get">
  <div class="row g-2 align-items-center">
    <div class="col-md-6 col-lg-5"><input type="search" class="form-control" name="search" placeholder="Search categories..." value="<?= e($search) ?>"></div>
    <div class="col-md-3 col-lg-2"><a class="reset-link" href="categories.php">↺ Reset Filter</a></div>
  </div>
</form>
<div class="card"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Description</th><th>Menu Items</th><th width="160">Actions</th></tr></thead><tbody>
<?php foreach ($list as $c): ?><tr><td><b><?= e($c['category_name']) ?></b></td><td><?= e($c['description']) ?></td><td><span class="badge bg-secondary"><?= $c['items'] ?></span></td>
  <td>
    <div class="action-btns">
      <a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?= $c['category_id'] ?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Delete this category?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $c['category_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
    </div>
  </td></tr>
<?php endforeach; if (!$list) echo "<tr><td colspan='4' class='empty-state'><p>No categories found.</p></td></tr>"; ?>
</tbody></table></div>
<?php endif; include 'includes/footer.php'; ?>
