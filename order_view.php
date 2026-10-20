<?php
require 'includes/config.php'; require_login();
$title = 'Order'; $canManageOrders = in_array($_SESSION['accesslevel'], ['admin', 'staff'], true); $errors = [];
$id = (int)($_GET['id'] ?? 0);

try {
    $order = one("SELECT o.*, a.fullname, a.phone, a.address, a.email,
                         s.fullname AS staff_name, s.username AS staff_username, s.accesslevel AS staff_role
                  FROM orders o 
                  JOIN accounts a ON a.account_id=o.account_id 
                  LEFT JOIN accounts s ON s.account_id=o.handled_by 
                  WHERE o.order_id=?", 'i', [$id]);

    if (!$order || (!$canManageOrders && $order['account_id'] != $_SESSION['account_id'])) {
        flash('danger', 'Order not found.'); redirect($canManageOrders ? 'orders_admin.php' : 'my_orders.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do = $_POST['do'] ?? '';
        if ($do === 'progress' && $canManageOrders) {
            if ($order['status'] === 'Pending') {
                $st = q("UPDATE orders SET status='Preparing', handled_by=? WHERE order_id=? AND status='Pending'", 'ii', [(int)$_SESSION['account_id'], $id]);
                if (mysqli_stmt_affected_rows($st) === 1) flash('success', 'Order accepted and marked Preparing.');
                else flash('warning', 'The order status has already changed. Review the latest status before continuing.');
            } elseif ($order['status'] === 'Preparing') {
                mysqli_begin_transaction($conn);
                try {
                    $lockedOrder = one("SELECT status FROM orders WHERE order_id=? FOR UPDATE", 'i', [$id]);
                    if (!$lockedOrder || $lockedOrder['status'] !== 'Preparing') {
                        mysqli_rollback($conn);
                        flash('warning', 'This order is no longer preparing. Review its latest status.');
                        redirect("order_view.php?id=$id");
                    }
                    $recipeRows = rows(q(
                        "SELECT oi.menu_id, m.item_name AS menu_name, oi.qty AS order_quantity,
                                mi.inventory_id, mi.quantity AS recipe_quantity, i.item_name AS ingredient_name,
                                i.unit, i.quantity AS stock
                         FROM order_items oi
                         JOIN menu_items m ON m.menu_id=oi.menu_id
                         LEFT JOIN menu_ingredients mi ON mi.menu_id=oi.menu_id
                         LEFT JOIN inventory_items i ON i.inventory_id=mi.inventory_id
                         WHERE oi.order_id=? ORDER BY mi.inventory_id FOR UPDATE",
                        'i', [$id]
                    ));
                    if (!$recipeRows) {
                        $errors[] = 'This order has no items to complete.';
                    }
                    $missingRecipes = []; $deductions = [];
                    foreach ($recipeRows as $recipe) {
                        if ($recipe['inventory_id'] === null) {
                            $missingRecipes[$recipe['menu_id']] = $recipe['menu_name'];
                            continue;
                        }
                        $inventoryId = (int)$recipe['inventory_id'];
                        if (!isset($deductions[$inventoryId])) {
                            $deductions[$inventoryId] = [
                                'name' => $recipe['ingredient_name'],
                                'unit' => $recipe['unit'],
                                'stock' => (float)$recipe['stock'],
                                'required' => 0.0,
                            ];
                        }
                        $deductions[$inventoryId]['required'] += (float)$recipe['order_quantity'] * (float)$recipe['recipe_quantity'];
                    }
                    if ($missingRecipes) {
                        $errors[] = 'Add an ingredient recipe in Menu Items before completing: ' . implode(', ', $missingRecipes) . '.';
                    }
                    $shortages = [];
                    foreach ($deductions as $ingredient) {
                        $ingredient['required'] = round($ingredient['required'], 3);
                        if ($ingredient['stock'] + 0.0000001 < $ingredient['required']) {
                            $shortages[] = $ingredient['name'] . ': need ' . number_format($ingredient['required'], 3) . ' ' .
                                $ingredient['unit'] . ', available ' . number_format($ingredient['stock'], 3) . ' ' . $ingredient['unit'];
                        }
                    }
                    if ($shortages) $errors[] = 'Insufficient ingredient stock. ' . implode('; ', $shortages) . '. Stock in ingredients before completing this order.';
                    if (!$errors) {
                        foreach ($deductions as $inventoryId => $ingredient) {
                            $required = round($ingredient['required'], 3);
                            $update = q("UPDATE inventory_items SET quantity=quantity-? WHERE inventory_id=? AND quantity>=?", 'did',
                                [$required, $inventoryId, $required]);
                            if (mysqli_stmt_affected_rows($update) !== 1) {
                                throw new RuntimeException('Ingredient stock changed while completing the order. Please review availability and try again.');
                            }
                            q("INSERT INTO inventory_logs (inventory_id, account_id, movement, qty, remarks) VALUES (?,?,'OUT',?,?)",
                                'iids', [$inventoryId, (int)$_SESSION['account_id'], $required, "Order #$id auto ingredient deduction"]);
                        }
                        $update = q("UPDATE orders SET status='Completed', handled_by=? WHERE order_id=? AND status='Preparing'", 'ii', [(int)$_SESSION['account_id'], $id]);
                        if (mysqli_stmt_affected_rows($update) !== 1) {
                            throw new RuntimeException('The order status changed while completing the order. Review the current status.');
                        }
                        mysqli_commit($conn);
                        flash('success', 'Order completed and recipe ingredients deducted from inventory.');
                        redirect("order_view.php?id=$id");
                    }
                    mysqli_rollback($conn);
                } catch (mysqli_sql_exception $ex) {
                    mysqli_rollback($conn);
                    $errors[] = db_error_message($ex);
                } catch (RuntimeException $ex) {
                    mysqli_rollback($conn);
                    $errors[] = $ex->getMessage();
                }
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
                $handledParam = $canManageOrders ? (int)$_SESSION['account_id'] : null;
                $types = 'si' . str_repeat('s', count($allowedStatuses)) . 'i';
                $params = array_merge([$reason, $handledParam], $allowedStatuses, [$id]);
                $st = q("UPDATE orders SET status='Voided', void_reason=?, handled_by=? WHERE status IN ($placeholders) AND order_id=?", $types, $params);
                if (mysqli_stmt_affected_rows($st) === 1) {
                    $message = !$canManageOrders ? 'Order cancellation recorded.'
                        : ($order['status'] === 'Pending' ? 'Order rejected and reason recorded.' : 'Order voided and reason recorded.');
                    flash('success', $message);
                } else {
                    flash('warning', 'The order status has already changed.');
                }
            }
        } else {
            flash('danger', 'That action is not allowed.');
        }
        if (!$errors) redirect("order_view.php?id=$id");
    }
    $items = rows(q("SELECT oi.*, m.item_name FROM order_items oi JOIN menu_items m ON m.menu_id=oi.menu_id WHERE oi.order_id=?", 'i', [$id]));
} catch (mysqli_sql_exception $ex) {
    if (($_POST['do'] ?? '') === 'void' && in_array($ex->getCode(), [1054, 1265], true)) {
        error_log($ex->getMessage());
        $errors[] = 'The database schema needs updating. Please reload or check database.';
    } else {
        $errors[] = db_error_message($ex);
    }
    $items = [];
}
include 'includes/header.php';
if (empty($order)) { include 'includes/footer.php'; exit; }
?>
<div class="receipt card"><div class="card-body p-4">
  <div class="d-flex justify-content-between align-items-start mb-2">
    <div>
      <h4 class="mb-0">Order #<?= $order['order_id'] ?></h4>
      <small class="text-muted"><?= e(date('F d, Y · h:i A', strtotime($order['ordered_at']))) ?></small>
      <?php if (!empty($order['staff_name'])): ?>
        <div class="mt-1 small text-muted">
          👤 <b>Handled by:</b> <?= e($order['staff_name']) ?> <span class="badge bg-light text-dark border"><?= e(strtoupper($order['staff_role'] ?? 'staff')) ?></span>
        </div>
      <?php endif; ?>
    </div>
    <?= status_badge($order['status']) ?>
  </div>
  <hr>
  <div class="row mb-3">
    <div class="col-sm-6">
      <div class="small text-muted text-uppercase fw-semibold mb-1">Customer Information</div>
      <b><?= e($order['fullname']) ?></b><br>
      <?= e($order['email']) ?><br>
      <?= e($order['phone'] ?: 'No phone provided') ?>
    </div>
    <div class="col-sm-6">
      <div class="small text-muted text-uppercase fw-semibold mb-1">Order Details</div>
      <b><?= e($order['order_type']) ?></b>
      <?= $order['order_type'] === 'Delivery' && $order['address'] ? '<br><small class="text-muted">' . e($order['address']) . '</small>' : '' ?>
      <?= $order['notes'] ? '<br><small class="text-muted"><i>Note: ' . e($order['notes']) . '</i></small>' : '' ?>
    </div>
  </div>
  <?php if ($order['status'] === 'Voided' && $order['void_reason']): ?>
    <div class="alert alert-secondary py-2"><b>Void Reason / Incident:</b> <?= e($order['void_reason']) ?></div>
  <?php endif; ?>
  <table class="table align-middle">
    <thead>
      <tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i): ?>
        <tr>
          <td><?= e($i['item_name']) ?></td>
          <td class="text-end"><?= $i['qty'] ?></td>
          <td class="text-end"><?= peso($i['unit_price']) ?></td>
          <td class="text-end"><?= peso($i['unit_price'] * $i['qty']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="3" class="text-end">Total</th><th class="text-end price"><?= peso($order['total']) ?></th></tr>
    </tfoot>
  </table>
  <div class="d-flex flex-wrap gap-2 align-items-end pt-2">
    <a class="btn btn-outline-secondary" href="<?= $canManageOrders ? 'orders_admin.php' : 'my_orders.php' ?>">← Back</a>
    <?php if ($canManageOrders && $order['status'] === 'Pending'): ?>
      <form method="post" class="ms-auto"><input type="hidden" name="do" value="progress">
        <button class="btn btn-yo">Accept order</button></form>
    <?php elseif ($canManageOrders && $order['status'] === 'Preparing'): ?>
      <form method="post" class="ms-auto"><input type="hidden" name="do" value="progress">
        <button class="btn btn-success">Complete order</button></form>
      <small class="text-muted w-100 text-end">Recipe ingredients are deducted automatically at completion.</small>
    <?php elseif ($canManageOrders && $order['status'] === 'Completed'): ?>
      <span class="badge bg-success ms-auto align-self-center py-2 px-3">Order Completed</span>
    <?php endif; ?>
    <?php if (($canManageOrders && in_array($order['status'], ['Pending', 'Preparing', 'Completed'], true)) || (!$canManageOrders && $order['status'] === 'Pending')): ?>
      <form method="post" class="ms-auto d-flex flex-column flex-sm-row gap-2" onsubmit="return confirm('<?= $canManageOrders && $order['status'] === 'Pending' ? 'Reject' : ($canManageOrders ? 'Void' : 'Cancel') ?> this order? This cannot be undone.')">
        <input type="hidden" name="do" value="void">
        <div>
          <label class="form-label mb-1 small" for="void_reason"><?= $canManageOrders && $order['status'] === 'Pending' ? 'Reason for rejection' : 'Reason for cancellation / void' ?></label>
          <textarea id="void_reason" name="void_reason" class="form-control form-control-sm" rows="2" maxlength="2000" required placeholder="State reason or incident details"><?= e($_POST['void_reason'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-outline-danger btn-sm align-self-sm-end"><?= $canManageOrders && $order['status'] === 'Pending' ? 'Reject Order' : ($canManageOrders ? 'Void Order' : 'Cancel Order') ?></button>
      </form>
    <?php elseif (!$canManageOrders && $order['status'] === 'Preparing'): ?>
      <p class="text-muted ms-auto mb-0 small">This order is currently being prepared. Contact the restaurant for cancellations.</p>
    <?php endif; ?>
  </div>
</div></div>
<?php include 'includes/footer.php'; ?>
