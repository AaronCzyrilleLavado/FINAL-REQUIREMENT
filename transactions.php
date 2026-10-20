<?php
require 'includes/config.php'; require_admin();
$title = 'Transaction History'; $errors = [];
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$pageNumber = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 50;
$dateIsValid = static function ($date) {
    if ($date === '') return true;
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
};
if (!$dateIsValid($from)) { $errors[] = 'Start date is invalid.'; $from = ''; }
if (!$dateIsValid($to)) { $errors[] = 'End date is invalid.'; $to = ''; }
if ($from !== '' && $to !== '' && $from > $to) { $errors[] = 'Start date must be before or equal to end date.'; }

$where = ['(a.fullname LIKE ? OR a.username LIKE ? OR a.email LIKE ? OR CAST(o.order_id AS CHAR) LIKE ? OR s.fullname LIKE ? OR s.username LIKE ?)'];
$types = 'ssssss';
$params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
if (in_array($status, ['Pending', 'Preparing', 'Completed', 'Voided'], true)) {
    $where[] = 'o.status=?'; $types .= 's'; $params[] = $status;
}
if ($from !== '') { $where[] = 'o.ordered_at>=?'; $types .= 's'; $params[] = "$from 00:00:00"; }
if ($to !== '') { $where[] = 'o.ordered_at<=?'; $types .= 's'; $params[] = "$to 23:59:59"; }
$whereSql = implode(' AND ', $where);

try {
    $countSql = "SELECT COUNT(*) AS total FROM orders o 
                 JOIN accounts a ON a.account_id=o.account_id 
                 LEFT JOIN accounts s ON s.account_id=o.handled_by 
                 WHERE $whereSql";
    $totalRows = (int)(one($countSql, $types, $params)['total'] ?? 0);
    $totalPages = max(1, (int)ceil($totalRows / $pageSize));
    $pageNumber = min($pageNumber, $totalPages);
    $offset = ($pageNumber - 1) * $pageSize;
    $transactions = rows(q(
        "SELECT o.*, a.fullname, a.username, a.email, a.phone, a.address,
                s.fullname AS staff_name, s.username AS staff_username, s.accesslevel AS staff_role,
         (SELECT GROUP_CONCAT(CONCAT(m.item_name, ' × ', oi.qty) ORDER BY oi.order_item_id SEPARATOR ', ')
          FROM order_items oi JOIN menu_items m ON m.menu_id=oi.menu_id WHERE oi.order_id=o.order_id) AS items_summary
         FROM orders o 
         JOIN accounts a ON a.account_id=o.account_id
         LEFT JOIN accounts s ON s.account_id=o.handled_by
         WHERE $whereSql ORDER BY o.ordered_at DESC, o.order_id DESC LIMIT $pageSize OFFSET $offset",
        $types, $params
    ));
    $todayCount = (int)val("SELECT COUNT(*) FROM orders WHERE DATE(ordered_at)=CURDATE()");
    $todaySales = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(ordered_at)=CURDATE() AND status<>'Voided'");
    $todayVoided = (int)val("SELECT COUNT(*) FROM orders WHERE DATE(ordered_at)=CURDATE() AND status='Voided'");
} catch (mysqli_sql_exception $ex) {
    $errors[] = db_error_message($ex);
    $transactions = []; $totalRows = 0; $totalPages = 1;
    $todayCount = 0; $todaySales = 0; $todayVoided = 0;
}
include 'includes/header.php';
?>
<div class="transaction-toolbar mb-4">
  <div>
    <h3 class="mb-1">Transaction History</h3>
    <p class="text-muted mb-0">Complete order ledger, handling staff, customer details, totals, and void records.</p>
  </div>
  <div class="small text-muted">As of <?= e(date('l, F j, Y')) ?></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-sm-4"><div class="card stat-card transaction-summary"><div class="card-body"><div class="small text-muted">Today's transactions</div><div class="display-6 fw-bold"><?= number_format($todayCount) ?></div></div></div></div>
  <div class="col-sm-4"><div class="card stat-card transaction-summary"><div class="card-body"><div class="small text-muted">Today's sales (excluding voids)</div><div class="display-6 fw-bold text-success"><?= peso($todaySales) ?></div></div></div></div>
  <div class="col-sm-4"><div class="card stat-card transaction-summary"><div class="card-body"><div class="small text-muted">Voided today</div><div class="display-6 fw-bold text-secondary"><?= number_format($todayVoided) ?></div></div></div></div>
</div>
<form class="filter-bar auto-filter" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-lg-4"><label class="form-label" for="search">Search</label><input id="search" type="search" class="form-control" name="search" value="<?= e($search) ?>" placeholder="Order #, customer, staff, email..."></div>
    <div class="col-sm-4 col-lg-2"><label class="form-label" for="status">Status</label><select id="status" class="form-select" name="status">
      <option value="">All statuses</option><?php foreach (['Pending', 'Preparing', 'Completed', 'Voided'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-sm-4 col-lg-2"><label class="form-label" for="from">From</label><input id="from" type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div class="col-sm-4 col-lg-2"><label class="form-label" for="to">To</label><input id="to" type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
    <div class="col-lg-2"><a class="reset-link" href="transactions.php">↺ Reset Filter</a></div>
  </div>
</form>
<div class="d-flex justify-content-between align-items-center mb-2">
  <span class="text-muted small"><?= number_format($totalRows) ?> transaction(s) found</span>
  <span class="text-muted small">Page <?= $pageNumber ?> of <?= $totalPages ?></span>
</div>
<div class="card"><div class="table-responsive">
  <table class="table table-hover transaction-table align-middle mb-0">
    <thead>
      <tr>
        <th>Transaction</th>
        <th>Customer</th>
        <th>Processed By (Staff)</th>
        <th>Order Type</th>
        <th>Items Ordered</th>
        <th>Total</th>
        <th>Status</th>
        <th>Void Reason</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($transactions as $transaction): ?>
      <tr>
        <td>
          <span class="transaction-id">#<?= (int)$transaction['order_id'] ?></span><br>
          <small class="text-muted"><?= e(date('M d, Y · h:i A', strtotime($transaction['ordered_at']))) ?></small>
        </td>
        <td>
          <b><?= e($transaction['fullname']) ?></b><br>
          <small><?= e($transaction['email']) ?></small><br>
          <small class="text-muted"><?= e($transaction['phone'] ?: 'No phone') ?></small>
        </td>
        <td>
          <?php if (!empty($transaction['staff_name'])): ?>
            <b><?= e($transaction['staff_name']) ?></b><br>
            <span class="badge bg-light text-dark border"><?= e(strtoupper($transaction['staff_role'] ?? 'staff')) ?></span>
          <?php else: ?>
            <span class="text-muted small">Online / Pending</span>
          <?php endif; ?>
        </td>
        <td><?= e($transaction['order_type']) ?><br><small class="text-muted"><?= e($transaction['username']) ?></small></td>
        <td><?= e($transaction['items_summary'] ?: 'No items recorded') ?></td>
        <td class="fw-semibold text-nowrap"><?= peso($transaction['total']) ?></td>
        <td><?= status_badge($transaction['status']) ?></td>
        <td class="transaction-reason"><?= $transaction['status'] === 'Voided' ? e($transaction['void_reason'] ?: 'No reason recorded') : '<span class="text-muted">—</span>' ?></td>
        <td><a class="btn btn-sm btn-outline-primary" href="order_view.php?id=<?= (int)$transaction['order_id'] ?>">View</a></td>
      </tr>
    <?php endforeach; if (!$transactions): ?><tr><td colspan="9" class="empty-state"><p>No transactions match these filters.</p></td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div>
<?php if ($totalPages > 1): ?>
<nav class="mt-3" aria-label="Transaction pages"><ul class="pagination justify-content-end">
  <?php $previousQuery = http_build_query(array_merge($_GET, ['page' => max(1, $pageNumber - 1)])); ?>
  <li class="page-item <?= $pageNumber === 1 ? 'disabled' : '' ?>"><a class="page-link" href="transactions.php?<?= e($previousQuery) ?>" aria-label="Previous page">Previous</a></li>
  <?php
    $startPage = max(1, $pageNumber - 2); $endPage = min($totalPages, $pageNumber + 2);
    if ($startPage > 1) {
        $firstQuery = http_build_query(array_merge($_GET, ['page' => 1]));
        echo '<li class="page-item"><a class="page-link" href="transactions.php?' . e($firstQuery) . '">1</a></li>';
        if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    for ($page = $startPage; $page <= $endPage; $page++):
      $query = http_build_query(array_merge($_GET, ['page' => $page]));
  ?>
    <li class="page-item <?= $page === $pageNumber ? 'active' : '' ?>"><a class="page-link" href="transactions.php?<?= e($query) ?>"><?= $page ?></a></li>
  <?php endfor; ?>
  <?php
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $lastQuery = http_build_query(array_merge($_GET, ['page' => $totalPages]));
        echo '<li class="page-item"><a class="page-link" href="transactions.php?' . e($lastQuery) . '">' . $totalPages . '</a></li>';
    }
    $nextQuery = http_build_query(array_merge($_GET, ['page' => min($totalPages, $pageNumber + 1)]));
  ?>
  <li class="page-item <?= $pageNumber === $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="transactions.php?<?= e($nextQuery) ?>" aria-label="Next page">Next</a></li>
</ul></nav>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
