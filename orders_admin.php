<?php
require 'includes/config.php'; require_staff_or_admin();
$title = 'Orders'; $errors = [];
$search = trim($_GET['search'] ?? ''); $status = $_GET['status'] ?? ''; $type = $_GET['type'] ?? ''; $date = $_GET['date'] ?? '';
$sql = "SELECT o.*, a.fullname, s.fullname AS staff_name 
        FROM orders o 
        JOIN accounts a ON a.account_id=o.account_id 
        LEFT JOIN accounts s ON s.account_id=o.handled_by 
        WHERE (a.fullname LIKE ? OR o.order_id LIKE ? OR s.fullname LIKE ?)";
$t = 'sss'; $p = ["%$search%", "%$search%", "%$search%"];
if (in_array($status, ['Pending', 'Preparing', 'Completed', 'Voided'], true)) { $sql .= " AND o.status=?"; $t .= 's'; $p[] = $status; }
if (in_array($type, ['Dine-in', 'Take-out', 'Delivery'], true))                  { $sql .= " AND o.order_type=?"; $t .= 's'; $p[] = $type; }
if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))                  { $sql .= " AND DATE(o.ordered_at)=?"; $t .= 's'; $p[] = $date; }
try { $list = rows(q($sql . " ORDER BY o.ordered_at DESC LIMIT 200", $t, $p)); }
catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex); $list = []; }
include 'includes/header.php';
?>
<div class="page-header"><h3>Orders</h3></div>
<form class="filter-bar auto-filter" method="get">
  <div class="row g-2 align-items-center">
    <div class="col-md-3"><input type="search" class="form-control" name="search" placeholder="Customer, order #, staff..." value="<?= e($search) ?>"></div>
    <div class="col-md-2"><select class="form-select" name="status"><option value="">Any status</option><?php foreach (['Pending', 'Preparing', 'Completed', 'Voided'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="type"><option value="">Any type</option><?php foreach (['Dine-in', 'Take-out', 'Delivery'] as $s): ?><option <?= $type === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input type="date" class="form-control" name="date" value="<?= e($date) ?>"></div>
    <div class="col-md-3"><a class="reset-link" href="orders_admin.php">↺ Reset Filter</a></div>
  </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>Customer</th><th>Handled By</th><th>Type</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr></thead><tbody>
<?php foreach ($list as $o): ?><tr><td><b>#<?= $o['order_id'] ?></b></td><td><?= e($o['fullname']) ?></td>
  <td><?= $o['staff_name'] ? ('<span class="badge bg-light text-dark border">' . e($o['staff_name']) . '</span>') : '<span class="text-muted small">Not assigned</span>' ?></td>
  <td><?= e($o['order_type']) ?></td><td><?= peso($o['total']) ?></td><td><?= status_badge($o['status']) ?></td>
  <td><small class="text-muted"><?= e(date('M d, h:i A', strtotime($o['ordered_at']))) ?></small></td><td><a class="btn btn-sm btn-outline-primary" href="order_view.php?id=<?= $o['order_id'] ?>">View</a></td></tr>
<?php endforeach; if (!$list) echo "<tr><td colspan='8' class='empty-state'><p>No orders found.</p></td></tr>"; ?>
</tbody></table></div></div>
<?php include 'includes/footer.php'; ?>
