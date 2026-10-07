<?php
require 'includes/config.php'; require_login();
if ($_SESSION['accesslevel'] === 'admin') { redirect('orders_admin.php'); }
$title = 'My Orders'; $uid = (int)$_SESSION['account_id'];
$status = $_GET['status'] ?? '';
$sql = "SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.order_id) AS lines_count FROM orders o WHERE o.account_id=?";
$t = 'i'; $p = [$uid];
if (in_array($status, ['Pending', 'Preparing', 'Completed', 'Cancelled'], true)) { $sql .= " AND o.status=?"; $t .= 's'; $p[] = $status; }
$orders = rows(q($sql . " ORDER BY o.ordered_at DESC", $t, $p));
include 'includes/header.php';
?>
<div class="d-flex justify-content-between mb-3"><h3>My Orders</h3>
  <form method="get"><select name="status" class="form-select" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <?php foreach (['Pending', 'Preparing', 'Completed', 'Cancelled'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></form></div>
<div class="row g-3">
<?php foreach ($orders as $o): ?>
  <div class="col-md-6"><div class="card"><div class="card-body">
    <div class="d-flex justify-content-between"><h5>Order #<?= $o['order_id'] ?></h5><?= status_badge($o['status']) ?></div>
    <p class="text-muted mb-1"><?= e($o['order_type']) ?> · <?= (int)$o['lines_count'] ?> item(s) · <?= e(date('M d, Y h:i A', strtotime($o['ordered_at']))) ?></p>
    <div class="d-flex justify-content-between align-items-center"><span class="price"><?= peso($o['total']) ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="order_view.php?id=<?= $o['order_id'] ?>">View receipt</a></div>
  </div></div></div>
<?php endforeach; if (!$orders) echo "<p class='text-muted'>No orders found.</p>"; ?>
</div>
<?php include 'includes/footer.php'; ?>
