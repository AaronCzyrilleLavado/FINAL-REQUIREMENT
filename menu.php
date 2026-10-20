<?php
require 'includes/config.php'; require_login();
if ($_SESSION['accesslevel'] === 'staff') { flash('warning', 'The menu is available to customer accounts.'); redirect('dashboard.php'); }
$title = 'Menu'; $isAdmin = $_SESSION['accesslevel'] === 'admin';
$search = trim($_GET['search'] ?? ''); $cat = (int)($_GET['cat'] ?? 0);
$sql = "SELECT m.*, c.category_name FROM menu_items m JOIN categories c ON c.category_id=m.category_id WHERE 1=1";
$t = ''; $p = [];
if (!$isAdmin) { $sql .= " AND m.is_available=1"; }
if ($search !== '') { $sql .= " AND (m.item_name LIKE ? OR m.description LIKE ?)"; $t .= 'ss'; $p[] = "%$search%"; $p[] = "%$search%"; }
if ($cat) { $sql .= " AND m.category_id=?"; $t .= 'i'; $p[] = $cat; }
$sql .= " ORDER BY c.category_name, m.item_name";
$items = rows(q($sql, $t, $p));
$cats  = rows(q("SELECT * FROM categories ORDER BY category_name"));
include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Our Menu</h3>
  <?php if ($isAdmin): ?><a class="btn btn-yo btn-sm" href="menu_manage.php">Manage menu</a><?php endif; ?>
</div>
<form class="row g-2 mb-4" method="get">
  <div class="col-md-5"><input class="form-control" name="search" placeholder="Search dishes..." value="<?= e($search) ?>"></div>
  <div class="col-md-4"><select class="form-select" name="cat"><option value="0">All categories</option>
    <?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>" <?= $cat == $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><button class="btn btn-yo">Filter</button> <a class="btn btn-outline-secondary" href="menu.php">Reset</a></div>
</form>
<div class="row g-3">
<?php foreach ($items as $m): ?>
  <div class="col-sm-6 col-lg-4"><div class="card menu-card h-100">
    <div class="card-header d-flex justify-content-between"><span><?= e($m['category_name']) ?></span>
      <?php if (!$m['is_available']): ?><span class="badge bg-dark">Unavailable</span><?php endif; ?></div>
    <div class="card-body d-flex flex-column">
      <h5><?= e($m['item_name']) ?></h5>
      <p class="text-muted flex-grow-1"><?= e($m['description']) ?></p>
      <div class="d-flex justify-content-between align-items-center">
        <span class="price"><?= peso($m['price']) ?></span>
        <?php if (!$isAdmin && $m['is_available']): ?>
        <form method="post" action="cart.php" class="d-flex gap-1">
          <input type="hidden" name="do" value="add"><input type="hidden" name="menu_id" value="<?= $m['menu_id'] ?>">
          <input type="number" name="qty" value="1" min="1" max="50" class="form-control form-control-sm" style="width:70px">
          <button class="btn btn-yo btn-sm">Add</button></form>
        <?php endif; ?>
      </div>
    </div></div></div>
<?php endforeach; if (!$items) echo "<p class='text-muted'>No dishes match your search.</p>"; ?>
</div>
<?php include 'includes/footer.php'; ?>
