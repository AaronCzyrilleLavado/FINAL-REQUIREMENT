<?php
require 'includes/config.php'; require_login();
$title = 'Dashboard'; $isAdmin = $_SESSION['accesslevel'] === 'admin'; $uid = (int)$_SESSION['account_id'];
include 'includes/header.php';
?>
<?php if ($isAdmin):
  $stats = [
    ['Orders today',   val("SELECT COUNT(*) FROM orders WHERE DATE(ordered_at)=CURDATE()")],
    ['Pending orders', val("SELECT COUNT(*) FROM orders WHERE status='Pending'")],
    ["Today's sales",  peso(val("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(ordered_at)=CURDATE() AND status<>'Cancelled'"))],
    ['Low-stock items',val("SELECT COUNT(*) FROM inventory_items WHERE quantity<=reorder_level")],
    ['Menu items',     val("SELECT COUNT(*) FROM menu_items")],
    ['Customers',      val("SELECT COUNT(*) FROM accounts WHERE accesslevel='user'")],
  ];
  $recent = rows(q("SELECT o.*, a.fullname FROM orders o JOIN accounts a ON a.account_id=o.account_id ORDER BY o.ordered_at DESC LIMIT 6"));
  $low    = rows(q("SELECT i.*, s.supplier_name FROM inventory_items i JOIN suppliers s ON s.supplier_id=i.supplier_id WHERE i.quantity<=i.reorder_level ORDER BY i.quantity"));
  $top    = rows(q("SELECT m.item_name, SUM(oi.qty) sold FROM order_items oi JOIN menu_items m ON m.menu_id=oi.menu_id JOIN orders o ON o.order_id=oi.order_id WHERE o.status<>'Cancelled' GROUP BY m.menu_id ORDER BY sold DESC LIMIT 5"));
?>
<h3 class="mb-3">Admin Dashboard</h3>
<div class="row g-3 mb-4">
  <?php foreach ($stats as $s): ?>
  <div class="col-6 col-md-4 col-lg-2"><div class="card stat-card text-center p-3"><div class="display-6"><?= e($s[1]) ?></div><div class="text-muted small"><?= e($s[0]) ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-body">
    <h5>Recent orders</h5>
    <?php foreach ($recent as $o): ?>
      <a class="d-flex justify-content-between text-decoration-none text-dark border-bottom py-2" href="order_view.php?id=<?= $o['order_id'] ?>">
        <span>#<?= $o['order_id'] ?> · <?= e($o['fullname']) ?> <small class="text-muted">(<?= e($o['order_type']) ?>)</small></span>
        <span><?= peso($o['total']) ?> <?= status_badge($o['status']) ?></span></a>
    <?php endforeach; if (!$recent) echo "<p class='text-muted'>No orders yet.</p>"; ?>
  </div></div></div>
  <div class="col-lg-3"><div class="card h-100"><div class="card-body">
    <h5 class="text-danger">Low stock</h5>
    <?php foreach ($low as $i): ?>
      <div class="border-bottom py-2"><a href="inventory_view.php?id=<?= $i['inventory_id'] ?>"><?= e($i['item_name']) ?></a>
        <div class="small text-muted"><?= e($i['quantity'] + 0) ?> <?= e($i['unit']) ?> left · <?= e($i['supplier_name']) ?></div></div>
    <?php endforeach; if (!$low) echo "<p class='text-muted'>All stocked up.</p>"; ?>
  </div></div></div>
  <div class="col-lg-3"><div class="card h-100"><div class="card-body">
    <h5>Best sellers</h5>
    <?php foreach ($top as $t): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e($t['item_name']) ?></span><span class="badge bg-secondary"><?= (int)$t['sold'] ?> sold</span></div>
    <?php endforeach; if (!$top) echo "<p class='text-muted'>No sales yet.</p>"; ?>
  </div></div></div>
</div>

<?php else:
  $cnt = rows(q("SELECT status, COUNT(*) c FROM orders WHERE account_id=? GROUP BY status", 'i', [$uid]));
  $by = []; foreach ($cnt as $c) { $by[$c['status']] = $c['c']; }
  $recent = rows(q("SELECT * FROM orders WHERE account_id=? ORDER BY ordered_at DESC LIMIT 5", 'i', [$uid]));
?>
<h3 class="mb-3">Welcome, <?= e($_SESSION['fullname']) ?>!</h3>
<div class="row g-3 mb-4">
  <?php foreach (['Pending', 'Preparing', 'Completed'] as $s): ?>
    <div class="col-4"><div class="card stat-card text-center p-3"><div class="display-6"><?= (int)($by[$s] ?? 0) ?></div><div class="text-muted small"><?= $s ?> orders</div></div></div>
  <?php endforeach; ?>
</div>
<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between"><h5>Your recent orders</h5><a class="btn btn-yo btn-sm" href="menu.php">Order now</a></div>
  <?php foreach ($recent as $o): ?>
    <a class="d-flex justify-content-between text-decoration-none text-dark border-bottom py-2" href="order_view.php?id=<?= $o['order_id'] ?>">
      <span>#<?= $o['order_id'] ?> · <?= e($o['order_type']) ?> · <small class="text-muted"><?= e(date('M d, h:i A', strtotime($o['ordered_at']))) ?></small></span>
      <span><?= peso($o['total']) ?> <?= status_badge($o['status']) ?></span></a>
  <?php endforeach; if (!$recent) echo "<p class='text-muted mt-2'>You have not ordered yet. Browse the menu to get started!</p>"; ?>
</div></div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
