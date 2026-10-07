<?php
require_once __DIR__ . '/config.php';
require_login();
$isAdmin   = $_SESSION['accesslevel'] === 'admin';
$cartCount = array_sum($_SESSION['cart'] ?? []);
$page      = basename($_SERVER['PHP_SELF']);
function nav($file, $label, $page) { $a = ($page === $file) ? ' active' : ''; echo "<li class='nav-item'><a class='nav-link$a' href='$file'>$label</a></li>"; }
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
<nav class="navbar navbar-expand-lg navbar-dark yo-nav sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">🍽️ The Yo's</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nv"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nv">
      <ul class="navbar-nav me-auto">
        <?php nav('dashboard.php', 'Dashboard', $page); nav('menu.php', 'Menu', $page); ?>
        <?php if ($isAdmin): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Manage</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="orders_admin.php">Orders</a></li>
              <li><a class="dropdown-item" href="menu_manage.php">Menu Items</a></li>
              <li><a class="dropdown-item" href="categories.php">Categories</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="inventory.php">Inventory</a></li>
              <li><a class="dropdown-item" href="suppliers.php">Suppliers</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="users.php">Users</a></li>
            </ul>
          </li>
        <?php else: nav('cart.php', "Cart <span class='badge bg-warning text-dark'>$cartCount</span>", $page); nav('my_orders.php', 'My Orders', $page); endif; ?>
      </ul>
      <span class="navbar-text me-3 text-white-50">
        <?= e($_SESSION['fullname']) ?> <span class="badge bg-light text-dark"><?= e(strtoupper($_SESSION['accesslevel'])) ?></span>
      </span>
      <a class="btn btn-outline-light btn-sm" href="logout.php">Log out</a>
    </div>
  </div>
</nav>
<main class="container py-4">
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['msg']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>
