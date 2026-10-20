<?php
require_once __DIR__ . '/config.php';
require_login();
$isAdmin   = $_SESSION['accesslevel'] === 'admin';
$isStaff   = $_SESSION['accesslevel'] === 'staff';
$cartCount = array_sum($_SESSION['cart'] ?? []);
$page      = basename($_SERVER['PHP_SELF']);
function side_link($file, $label, $page, $icon = '') {
    $active = $page === $file ? ' active' : '';
    echo "<a class='side-link$active' href='$file'><span class='side-icon' aria-hidden='true'>$icon</span><span class='side-label'>$label</span></a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? "The Yo's") ?> | The Yo's</title>
<link href="bootstrap-5.3.2-dist/css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
      <a class="brand-link" href="dashboard.php"><span class="brand-mark">🍽️</span><span class="side-label">THE-YO'S</span></a>
      <button class="sidebar-toggle desktop-toggle" type="button" aria-label="Collapse navigation" aria-expanded="true" aria-controls="appSidebar">
        <span></span><span></span><span></span>
      </button>
    </div>
    <div class="sidebar-user">
      <span class="user-avatar"><?= e(strtoupper(substr($_SESSION['fullname'], 0, 1))) ?></span>
      <span class="side-label user-details"><b><?= e($_SESSION['fullname']) ?></b><small><?= e(ucfirst($_SESSION['accesslevel'])) ?></small></span>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">
        <div class="nav-section-title side-label">Overview</div>
        <?php side_link('dashboard.php', 'Dashboard', $page, '⌂'); ?>
      </div>
      <?php if ($isAdmin || $isStaff): ?>
        <div class="nav-section">
          <div class="nav-section-title side-label">Sales &amp; Service</div>
          <?php side_link('orders_admin.php', 'Orders', $page, '▤'); ?>
          <?php if ($isAdmin) side_link('transactions.php', 'Transactions', $page, '▧'); ?>
        </div>
        <div class="nav-section">
          <div class="nav-section-title side-label">Inventory</div>
          <?php side_link('inventory.php', 'Inventory', $page, '◇'); ?>
          <?php if ($isAdmin): ?>
            <?php side_link('menu_manage.php', 'Menu Items', $page, '☷'); ?>
            <?php side_link('categories.php', 'Categories', $page, '▦'); ?>
            <?php side_link('suppliers.php', 'Suppliers', $page, '▣'); ?>
          <?php endif; ?>
        </div>
        <?php if ($isAdmin): ?>
          <div class="nav-section">
            <div class="nav-section-title side-label">Administration</div>
            <?php side_link('users.php', 'Accounts', $page, '♙'); ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="nav-section">
          <div class="nav-section-title side-label">Dining</div>
          <?php side_link('menu.php', 'Menu', $page, '☷'); ?>
          <?php side_link('cart.php', "Cart ($cartCount)", $page, '🛒'); ?>
          <?php side_link('my_orders.php', 'My Orders', $page, '▤'); ?>
        </div>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer"><a class="side-link" href="logout.php"><span class="side-icon" aria-hidden="true">↪</span><span class="side-label">Log out</span></a></div>
  </aside>
  <button class="sidebar-backdrop" type="button" aria-label="Close navigation"></button>
  <div class="app-content">
    <div class="mobile-toolbar">
      <button class="sidebar-toggle mobile-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="appSidebar">
        <span></span><span></span><span></span>
      </button>
      <span class="fw-bold">THE-YO'S</span>
      <span class="small text-muted"><?= e(ucfirst($_SESSION['accesslevel'])) ?></span>
    </div>
    <main class="page-content container-fluid py-4">
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['msg']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>
