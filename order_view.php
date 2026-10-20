<?php
require 'includes/config.php'; require_login();
$title = 'Order'; $canManageOrders = in_array($_SESSION['accesslevel'], ['admin', 'staff'], true); $errors = [];
$id = (int)($_GET['id'] ?? 0);

try {
    $order = one("SELECT o.*, a.fullname, a.phone, a.address, a.email FROM orders o JOIN accounts a ON a.account_id=o.account_id WHERE o.order_id=?", 'i', [$id]);
    if (!$order || (!$canManageOrders && $order['account_id'] != $_SESSION['account_id'])) {
        flash('danger', 'Order not found.'); redirect($canManageOrders ? 'orders_admin.php' : 'my_orders.php');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do = $_POST['do'] ?? '';
        if ($do === 'progress' && $canManageOrders) {
            $nextStatus = null; $expectedStatus = null;
            if ($order['status'] === 'Pending') { $expectedStatus = 'Pending'; $nextStatus = 'Preparing'; }
            elseif ($order['status'] === 'Preparing') { $expectedStatus = 'Preparing'; $nextStatus = 'Completed'; }
            if ($nextStatus !== null) {
                $st = q("UPDATE orders SET status=? WHERE order_id=? AND status=?", 'sis', [$nextStatus, $id, $expectedStatus]);
                if (mysqli_stmt_affected_rows($st) === 1) flash('success', "Order marked $nextStatus.");
                else flash('warning', 'The order status has already changed. Review the latest status before continuing.');
            } else {
                flash('warning', 'This order cannot be moved to another status.');
            }
        } elseif ($do === 'void' && (($canManageOrders && in_array($order['status'], ['Pending', 'Preparing', 'Completed'], true))
            || (!$canManageOrders && $order['status'] === 'Pending'))) {
            $reason = trim($_POST['void_reason'] ?? '');
            if ($reason === '') {
                $errors[] = 'Enter a reason for rejecting, cancelling, or voiding this order.';
            } elseif (strlen($reason) > 2000) {
                $errors[] = 'The void reason must be 2,000 characters or fewer.';
            } else {
                $allowedStatuses = $canManageOrders ? ['Pending', 'Preparing', 'Completed'] : ['Pending'];
                $placeholders = implode(',', array_fill(0, count($allowedStatuses), '?'));
                $types = 's' . str_repeat('s', count($allowedStatuses)) . 'i';
                $params = array_merge([$reason], $allowedStatuses, [$id]);
                $st = q("UPDATE orders SET status='Voided', void_reason=? WHERE status IN ($placeholders) AND order_id=?", $types, $params);
                if (mysqli_stmt_affected_rows($st) === 1) {
                    $message = !$canManageOrders ? 'Order cancellation recorded.'
                        : ($order['status'] === 'Pending' ? 'Order rejected and reason recorded.' : 'Order voided and reason recorded.');
                    flash('success', $message);
                }
                else flash('warning', 'The order status has already changed.');
            }
        } else { flash('danger', 'That action is not allowed.'); }
        if (!$errors) redirect("order_view.php?id=$id");
    }
    $items = rows(q("SELECT oi.*, m.item_name FROM order_items oi JOIN menu_items m ON m.menu_id=oi.menu_id WHERE oi.order_id=?", 'i', [$id]));
} catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex); $items = []; }
include 'includes/header.php';
if (empty($order)) { include 'includes/footer.php'; exit; }
?>
<div class="receipt card"><div class="card-body p-4">
  <div class="d-flex justify-content-between"><div><h4 class="mb-0">Order #<?= $order['order_id'] ?></h4>
    <small class="text-muted"><?= e(date('F d, Y h:i A', strtotime($order['ordered_at']))) ?></small></div><?= status_badge($order['status']) ?></div>
  <hr>
  <div class="row mb-3"><div class="col-sm-6"><b>Customer</b><br><?= e($order['fullname']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></div>
    <div class="col-sm-6"><b><?= e($order['order_type']) ?></b><br><?= $order['order_type'] === 'Delivery' ? e($order['address']) : '' ?>
      <?= $order['notes'] ? '<br><i>Note: ' . e($order['notes']) . '</i>' : '' ?></div></div>
  <?php if ($order['status'] === 'Voided' && $order['void_reason']): ?>
    <div class="alert alert-secondary"><b>Void reason:</b> <?= e($order['void_reason']) ?></div>
  <?php endif; ?>
  <table class="table"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr></thead><tbody>
  <?php foreach ($items as $i): ?><tr><td><?= e($i['item_name']) ?></td><td class="text-end"><?= $i['qty'] ?></td><td class="text-end"><?= peso($i['unit_price']) ?></td><td class="text-end"><?= peso($i['unit_price'] * $i['qty']) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end price"><?= peso($order['total']) ?></th></tr></tfoot></table>
  <div class="d-flex flex-wrap gap-2 align-items-end">
    <a class="btn btn-outline-secondary" href="<?= $canManageOrders ? 'orders_admin.php' : 'my_orders.php' ?>">← Back</a>
    <?php if ($canManageOrders && $order['status'] === 'Pending'): ?>
      <form method="post" class="ms-auto"><input type="hidden" name="do" value="progress">
        <button class="btn btn-yo">Accept order</button></form>
    <?php elseif ($canManageOrders && $order['status'] === 'Preparing'): ?>
      <form method="post" class="ms-auto"><input type="hidden" name="do" value="progress">
        <button class="btn btn-success">Complete order</button></form>
    <?php elseif ($canManageOrders && $order['status'] === 'Completed'): ?>
      <span class="badge bg-success ms-auto align-self-center">Order completed</span>
    <?php endif; ?>
    <?php if (($canManageOrders && in_array($order['status'], ['Pending', 'Preparing', 'Completed'], true)) || (!$canManageOrders && $order['status'] === 'Pending')): ?>
      <form method="post" class="ms-auto d-flex flex-column flex-sm-row gap-2" onsubmit="return confirm('<?= $canManageOrders && $order['status'] === 'Pending' ? 'Reject' : ($canManageOrders ? 'Void' : 'Cancel') ?> this order? This cannot be undone.')">
        <input type="hidden" name="do" value="void">
        <div><label class="form-label mb-1" for="void_reason"><?= $canManageOrders && $order['status'] === 'Pending' ? 'Reason for rejection' : 'Reason for cancellation / void' ?></label>
          <textarea id="void_reason" name="void_reason" class="form-control" rows="2" maxlength="2000" required placeholder="Report the incident or reason"><?= e($_POST['void_reason'] ?? '') ?></textarea></div>
        <button class="btn btn-outline-danger align-self-sm-end"><?= $canManageOrders && $order['status'] === 'Pending' ? 'Reject order' : ($canManageOrders ? 'Void order' : 'Cancel order') ?></button>
      </form>
    <?php elseif (!$canManageOrders && $order['status'] === 'Preparing'): ?>
      <p class="text-muted ms-auto mb-0">This order is being prepared. Contact the admin to request cancellation.</p>
    <?php endif; ?>
  </div>
</div></div>
<?php include 'includes/footer.php'; ?>
