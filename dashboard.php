<?php
require 'includes/config.php'; require_login();
$title = 'Dashboard'; $isAdmin = $_SESSION['accesslevel'] === 'admin'; $isStaff = $_SESSION['accesslevel'] === 'staff'; $uid = (int)$_SESSION['account_id'];
include 'includes/header.php';
?>
<?php if ($isAdmin || $isStaff):
  $stats = [
    ["Today's sales",  peso(val("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(ordered_at)=CURDATE() AND status<>'Voided'"))],
    ['Low-stock items', val("SELECT COUNT(*) FROM inventory_items WHERE quantity<=reorder_level")],
    ['Menu items', val("SELECT COUNT(*) FROM menu_items")],
    ['Customers ordered today', val("SELECT COUNT(DISTINCT o.account_id) FROM orders o JOIN accounts a ON a.account_id=o.account_id WHERE DATE(o.ordered_at)=CURDATE() AND a.accesslevel='user'")],
    ['Live orders', val("SELECT COUNT(*) FROM orders WHERE status IN ('Pending','Preparing')")],
  ];
  $liveOrders = rows(q("SELECT o.*, a.fullname FROM orders o JOIN accounts a ON a.account_id=o.account_id WHERE o.status IN ('Pending','Preparing') ORDER BY o.ordered_at ASC"));
  $low    = rows(q("SELECT i.*, s.supplier_name FROM inventory_items i JOIN suppliers s ON s.supplier_id=i.supplier_id WHERE i.quantity<=i.reorder_level ORDER BY i.quantity"));
?>
<h3 class="mb-3"><?= $isAdmin ? 'Admin' : 'Staff' ?> Dashboard</h3>
<div class="row g-3 mb-4">
  <?php foreach ($stats as $s): ?>
  <div class="col-6 col-md-4 col-lg"><div class="card stat-card text-center p-3 h-100"><div class="display-6"><?= e($s[1]) ?></div><div class="text-muted small"><?= e($s[0]) ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Live orders</h5><a href="orders_admin.php">All orders</a></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($liveOrders as $o): ?>
      <tr><td>#<?= (int)$o['order_id'] ?><br><small class="text-muted"><?= e(date('M d, h:i A', strtotime($o['ordered_at']))) ?></small></td>
        <td><?= e($o['fullname']) ?></td><td><?= e($o['order_type']) ?></td><td><?= peso($o['total']) ?></td><td><?= status_badge($o['status']) ?></td>
        <td><a class="btn btn-sm btn-outline-primary" href="order_view.php?id=<?= (int)$o['order_id'] ?>">View order</a></td></tr>
    <?php endforeach; if (!$liveOrders) echo "<tr><td colspan='6' class='text-center text-muted'>No pending or preparing orders.</td></tr>"; ?>
    </tbody></table></div>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <h5 class="text-danger">Low stock</h5>
    <?php foreach ($low as $i): ?>
      <div class="border-bottom py-2"><a href="inventory_view.php?id=<?= $i['inventory_id'] ?>"><?= e($i['item_name']) ?></a>
        <div class="small text-muted"><?= e($i['quantity'] + 0) ?> <?= e($i['unit']) ?> left · <?= e($i['supplier_name']) ?></div></div>
    <?php endforeach; if (!$low) echo "<p class='text-muted'>All stocked up.</p>"; ?>
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
