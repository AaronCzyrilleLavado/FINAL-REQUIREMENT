<?php
require 'includes/config.php'; require_login();
if ($_SESSION['accesslevel'] === 'admin') { flash('warning', 'Administrators cannot place orders.'); redirect('dashboard.php'); }
$title = 'Cart'; $errors = [];
$cart = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'add') {
            $id = (int)$_POST['menu_id']; $qty = (int)$_POST['qty'];
            $m = one("SELECT menu_id FROM menu_items WHERE menu_id=? AND is_available=1", 'i', [$id]);
            if (!$m)                       { flash('danger', 'That item is not available.'); }
            elseif ($qty < 1 || $qty > 50) { flash('danger', 'Quantity must be between 1 and 50.'); }
            else { $cart[$id] = min(50, ($cart[$id] ?? 0) + $qty); $_SESSION['cart'] = $cart; flash('success', 'Added to cart.'); }
            redirect($_SERVER['HTTP_REFERER'] ?? 'menu.php');
        }
        if ($do === 'update') {
            foreach (($_POST['qty'] ?? []) as $id => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) unset($cart[(int)$id]); elseif ($qty <= 50) $cart[(int)$id] = $qty;
            }
            $_SESSION['cart'] = $cart; flash('success', 'Cart updated.'); redirect('cart.php');
        }
        if ($do === 'remove') { unset($cart[(int)$_POST['menu_id']]); $_SESSION['cart'] = $cart; redirect('cart.php'); }
        if ($do === 'clear')  { unset($_SESSION['cart']); redirect('cart.php'); }

        if ($do === 'checkout') {
            $type = $_POST['order_type'] ?? '';
            if (!in_array($type, ['Dine-in', 'Take-out', 'Delivery'], true)) $errors[] = 'Choose an order type.';
            if (!$cart) $errors[] = 'Your cart is empty.';
            if (!$errors) {
                $ids = array_keys($cart);
                $ph  = implode(',', array_fill(0, count($ids), '?'));
                $found = rows(q("SELECT menu_id, price FROM menu_items WHERE is_available=1 AND menu_id IN ($ph)", str_repeat('i', count($ids)), $ids));
                if (count($found) !== count($ids)) { $errors[] = 'Some items are no longer available. Please review your cart.'; }
                else {
                    mysqli_begin_transaction($conn);
                    try {
                        $total = 0; foreach ($found as $f) { $total += $f['price'] * $cart[$f['menu_id']]; }   // prices come from the DB, not the browser
                        $st = q("INSERT INTO orders (account_id, order_type, total, notes) VALUES (?,?,?,?)", 'isds',
                                [$_SESSION['account_id'], $type, $total, trim($_POST['notes'] ?? '')]);
                        $oid = mysqli_stmt_insert_id($st);
                        foreach ($found as $f) { q("INSERT INTO order_items (order_id, menu_id, qty, unit_price) VALUES (?,?,?,?)", 'iiid', [$oid, $f['menu_id'], $cart[$f['menu_id']], $f['price']]); }
                        mysqli_commit($conn);
                        unset($_SESSION['cart']);
                        flash('success', "Order #$oid placed! We will start preparing it soon.");
                        redirect("order_view.php?id=$oid");
                    } catch (mysqli_sql_exception $ex) { mysqli_rollback($conn); throw $ex; }
                }
            }
        }
    } catch (mysqli_sql_exception $ex) { $errors[] = db_error_message($ex); }
}

$lines = []; $total = 0;
if ($cart) {
    $ids = array_keys($cart); $ph = implode(',', array_fill(0, count($ids), '?'));
    $lines = rows(q("SELECT m.*, c.category_name FROM menu_items m JOIN categories c ON c.category_id=m.category_id WHERE m.menu_id IN ($ph)", str_repeat('i', count($ids)), $ids));
    foreach ($lines as $l) { $total += $l['price'] * $cart[$l['menu_id']]; }
}
include 'includes/header.php';
?>
<h3 class="mb-3">Your Cart</h3>
<?php if (!$lines): ?>
  <div class="card p-4 text-center"><p class="mb-2">Your cart is empty.</p><a class="btn btn-yo mx-auto" href="menu.php">Browse the menu</a></div>
<?php else: ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-body">
    <form method="post"><input type="hidden" name="do" value="update">
    <table class="table align-middle"><thead><tr><th>Item</th><th>Price</th><th width="110">Qty</th><th>Subtotal</th><th></th></tr></thead><tbody>
    <?php foreach ($lines as $l): ?>
      <tr><td><b><?= e($l['item_name']) ?></b><br><small class="text-muted"><?= e($l['category_name']) ?></small></td>
        <td><?= peso($l['price']) ?></td>
        <td><input type="number" class="form-control form-control-sm" min="0" max="50" name="qty[<?= $l['menu_id'] ?>]" value="<?= $cart[$l['menu_id']] ?>"></td>
        <td><?= peso($l['price'] * $cart[$l['menu_id']]) ?></td>
        <td><button class="btn btn-sm btn-outline-danger" form="rm<?= $l['menu_id'] ?>">✕</button></td></tr>
    <?php endforeach; ?></tbody></table>
    <button class="btn btn-outline-secondary btn-sm">Update quantities</button>
    </form>
    <?php foreach ($lines as $l): ?><form id="rm<?= $l['menu_id'] ?>" method="post"><input type="hidden" name="do" value="remove"><input type="hidden" name="menu_id" value="<?= $l['menu_id'] ?>"></form><?php endforeach; ?>
  </div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <h5>Checkout</h5>
    <form method="post"><input type="hidden" name="do" value="checkout">
      <label class="form-label">Order type</label>
      <select name="order_type" class="form-select mb-2"><option value="">Select...</option><option>Dine-in</option><option>Take-out</option><option>Delivery</option></select>
      <label class="form-label">Notes (optional)</label>
      <textarea name="notes" class="form-control mb-3" rows="2" maxlength="255"></textarea>
      <div class="d-flex justify-content-between mb-3"><b>Total</b><span class="price"><?= peso($total) ?></span></div>
      <button class="btn btn-yo w-100">Place order</button>
    </form>
  </div></div></div>
</div>
<?php endif; include 'includes/footer.php'; ?>
