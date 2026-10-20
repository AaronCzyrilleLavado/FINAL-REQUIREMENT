<?php
require 'includes/config.php'; require_login();
if ($_SESSION['accesslevel'] !== 'user') { redirect('orders_admin.php'); }
$title = 'My Orders'; $uid = (int)$_SESSION['account_id'];
$status = $_GET['status'] ?? '';
$sql = "SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.order_id) AS lines_count FROM orders o WHERE o.account_id=?";
$t = 'i'; $p = [$uid];
if (in_array($status, ['Pending', 'Preparing', 'Completed', 'Voided'], true)) { $sql .= " AND o.status=?"; $t .= 's'; $p[] = $status; }
$orders = rows(q($sql . " ORDER BY o.ordered_at DESC", $t, $p));
include 'includes/header.php';
?>
<div class="page-header">
  <h3 class="mb-0">My Orders</h3>
  <a class="btn btn-yo btn-sm" href="menu.php">+ Order Again</a>
</div>

<form class="filter-bar auto-filter" method="get">
  <div class="row g-2 align-items-center">
    <div class="col-md-6 col-lg-4">
      <select name="status" class="form-select" aria-label="Filter by order status">
        <option value="">All statuses</option>
        <?php foreach (['Pending', 'Preparing', 'Completed', 'Voided'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4 col-lg-3">
      <a class="reset-link" href="my_orders.php">↺ Reset Filter</a>
    </div>
  </div>
</form>

<div class="row g-3">
<?php foreach ($orders as $o): ?>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h5 class="mb-0">Order #<?= $o['order_id'] ?></h5>
          <?= status_badge($o['status']) ?>
        </div>
        <p class="text-muted small mb-3">
          <?= e($o['order_type']) ?> · <?= (int)$o['lines_count'] ?> item(s) · <?= e(date('M d, Y · h:i A', strtotime($o['ordered_at']))) ?>
        </p>
        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
          <span class="price"><?= peso($o['total']) ?></span>
          <a class="btn btn-sm btn-outline-primary" href="order_view.php?id=<?= $o['order_id'] ?>">View Order</a>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; if (!$orders): ?>
  <div class="col-12"><div class="card p-4 empty-state"><p>No orders found for this status.</p></div></div>
<?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
