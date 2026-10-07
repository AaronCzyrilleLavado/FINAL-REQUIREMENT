<?php
require 'includes/config.php'; require_admin();
$title = 'Orders'; $errors = [];
$search = trim($_GET['search'] ?? ''); $status = $_GET['status'] ?? ''; $type = $_GET['type'] ?? ''; $date = $_GET['date'] ?? '';
$sql = "SELECT o.*, a.fullname FROM orders o JOIN accounts a ON a.account_id=o.account_id WHERE (a.fullname LIKE ? OR o.order_id LIKE ?)";
$t = 'ss'; $p = ["%$search%", "%$search%"];
if (in_array($status, ['Pending', 'Preparing', 'Completed', 'Cancelled'], true)) { $sql .= " AND o.status=?"; $t .= 's'; $p[] = $status; }
if (in_array($type, ['Dine-in', 'Take-out', 'Delivery'], true))                  { $sql .= " AND o.order_type=?"; $t .= 's'; $p[] = $type; }
if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))                  { $sql .= " AND DATE(o.ordered_at)=?"; $t .= 's'; $p[] = $date; }
try { $list = rows(q($sql . " ORDER BY o.ordered_at DESC LIMIT 200", $t, $p)); }
catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex); $list = []; }
include 'includes/header.php';
?>
<h3 class="mb-3">Orders</h3>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><input class="form-control" name="search" placeholder="Customer or order #" value="<?= e($search) ?>"></div>
  <div class="col-md-2"><select class="form-select" name="status"><option value="">Any status</option><?php foreach (['Pending', 'Preparing', 'Completed', 'Cancelled'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select" name="type"><option value="">Any type</option><?php foreach (['Dine-in', 'Take-out', 'Delivery'] as $s): ?><option <?= $type === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><input type="date" class="form-control" name="date" value="<?= e($date) ?>"></div>
  <div class="col-md-3"><button class="btn btn-yo">Filter</button> <a class="btn btn-outline-secondary" href="orders_admin.php">Reset</a></div>
</form>
<div class="card"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr></thead><tbody>
<?php foreach ($list as $o): ?><tr><td><?= $o['order_id'] ?></td><td><?= e($o['fullname']) ?></td><td><?= e($o['order_type']) ?></td><td><?= peso($o['total']) ?></td><td><?= status_badge($o['status']) ?></td>
  <td><?= e(date('M d, h:i A', strtotime($o['ordered_at']))) ?></td><td><a class="btn btn-sm btn-outline-primary" href="order_view.php?id=<?= $o['order_id'] ?>">Open</a></td></tr>
<?php endforeach; if (!$list) echo "<tr><td colspan='7' class='text-center text-muted'>No orders found.</td></tr>"; ?>
</tbody></table></div>
<?php include 'includes/footer.php'; ?>
