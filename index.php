<?php
require 'includes/config.php';
if (isset($_SESSION['account_id'])) { redirect('dashboard.php'); }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both username and password.';
    } else {
        try {
            $acc = one("SELECT * FROM accounts WHERE username=?", 's', [$username]);
            if ($acc && password_verify($password, $acc['password'])) {
                session_regenerate_id(true);
                $_SESSION['account_id']  = $acc['account_id'];
                $_SESSION['username']    = $acc['username'];
                $_SESSION['fullname']    = $acc['fullname'];
                $_SESSION['accesslevel'] = $acc['accesslevel'];   // 'admin' or 'user'
                redirect('dashboard.php');
            }
            $errors[] = 'Invalid username or password.';
        } catch (mysqli_sql_exception $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Login is unavailable right now. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | The Yo's</title>
<link href="bootstrap-5.3.2-dist/css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
</head><body>
<div class="auth-wrap"><div class="auth-card p-3">
  <div class="card p-4">
    <h3 class="text-center mb-1">🍽️ The Yo's</h3>
    <p class="text-center text-muted">Log in to order and manage your account</p>
    <?php if ($errors): ?><div class="alert alert-danger py-2"><?= e($errors[0]) ?></div><?php endif; ?>
    <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['msg']) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <div class="mb-3"><label class="form-label">Username</label>
        <input class="form-control" name="username" value="<?= e($_POST['username'] ?? '') ?>" autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input type="password" class="form-control" name="password"></div>
      <button class="btn btn-yo w-100">Log in</button>
    </form>
    <p class="text-center mt-3 mb-0">No account yet? <a href="register.php">Register</a></p>
  </div>
</div></div>
</body></html>
