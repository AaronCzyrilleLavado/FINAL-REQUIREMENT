<?php
require 'includes/config.php'; require_login();
$title = 'Order'; $isAdmin = $_SESSION['accesslevel'] === 'admin'; $errors = [];
$id = (int)($_GET['id'] ?? 0);

try {
    $order = one("SELECT o.*, a.fullname, a.phone, a.address, a.email FROM orders o JOIN accounts a ON a.account_id=o.account_id WHERE o.order_id=?", 'i', [$id]);
    if (!$order || (!$isAdmin && $order['account_id'] != $_SESSION['account_id'])) {
        flash('danger', 'Order not found.'); redirect($isAdmin ? 'orders_admin.php' : 'my_orders.php');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do = $_POST['do'] ?? '';
        if ($do === 'status' && $isAdmin && in_array($_POST['status'] ?? '', ['Pending', 'Preparing', 'Completed', 'Cancelled'], true)) {
            q("UPDATE orders SET status=? WHERE order_id=?", 'si', [$_POST['status'], $id]);
            flash('success', 'Order status updated.');
        } elseif ($do === 'cancel' && !$isAdmin && $order['status'] === 'Pending') {
            q("UPDATE orders SET status='Cancelled' WHERE order_id=?", 'i', [$id]);
            flash('success', 'Order cancelled.');
        } else { flash('danger', 'That action is not allowed.'); }
        redirect("order_view.php?id=$id");
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
  <table class="table"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr></thead><tbody>
  <?php foreach ($items as $i): ?><tr><td><?= e($i['item_name']) ?></td><td class="text-end"><?= $i['qty'] ?></td><td class="text-end"><?= peso($i['unit_price']) ?></td><td class="text-end"><?= peso($i['unit_price'] * $i['qty']) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end price"><?= peso($order['total']) ?></th></tr></tfoot></table>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= $isAdmin ? 'orders_admin.php' : 'my_orders.php' ?>">← Back</a>
    <?php if ($isAdmin): ?>
      <form method="post" class="d-flex gap-2 ms-auto"><input type="hidden" name="do" value="status">
        <select name="status" class="form-select"><?php foreach (['Pending', 'Preparing', 'Completed', 'Cancelled'] as $s): ?><option <?= $order['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
        <button class="btn btn-yo">Update</button></form>
    <?php elseif ($order['status'] === 'Pending'): ?>
      <form method="post" class="ms-auto" onsubmit="return confirm('Cancel this order?')"><input type="hidden" name="do" value="cancel"><button class="btn btn-outline-danger">Cancel order</button></form>
    <?php endif; ?>
  </div>
</div></div>
<?php include 'includes/footer.php'; ?>
