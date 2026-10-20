<?php
require 'includes/config.php';
if (isset($_SESSION['account_id'])) { redirect('dashboard.php'); }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both your username and password.';
    } else {
        try {
            $acc = one("SELECT * FROM accounts WHERE username=?", 's', [$username]);
            if ($acc && password_verify($password, $acc['password'])) {
                session_regenerate_id(true);
                $_SESSION['account_id']  = $acc['account_id'];
                $_SESSION['username']    = $acc['username'];
                $_SESSION['fullname']    = $acc['fullname'];
                $_SESSION['accesslevel'] = $acc['accesslevel'];
                redirect('dashboard.php');
            }
            $errors[] = 'Incorrect username or password. Please try again.';
        } catch (mysqli_sql_exception $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Login service is temporarily unavailable. Please try again shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In | The Yo's Restaurant</title>
<link href="bootstrap-5.3.2-dist/css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="auth-brand-badge">🍽️</div>
        <h3 class="fw-bold mb-1">Welcome to The Yo's</h3>
        <p class="text-muted small mb-0">Sign in to your account to place orders or manage services</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 mb-3">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= e($f['type']) ?> py-2 mb-3"><?= e($f['msg']) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on" novalidate>
        <div class="mb-3">
          <label class="form-label" for="login_user">Username</label>
          <input id="login_user" class="form-control" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus placeholder="Enter your username" autocomplete="username">
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0" for="login_pw">Password</label>
          </div>
          <div class="input-password-wrap">
            <input id="login_pw" type="password" class="form-control" name="password" required placeholder="Enter your password" autocomplete="current-password">
            <button type="button" class="btn-password-toggle" onclick="toggleAuthPw('login_pw', this)" aria-label="Toggle password visibility" title="Show/Hide password">
              👁️
            </button>
          </div>
        </div>

        <button class="btn btn-yo w-100 py-2 fs-6 mb-3">Sign In</button>
      </form>

      <div class="text-center pt-2 border-top">
        <p class="text-muted small mb-0">Don't have an account yet? <a class="fw-semibold text-decoration-none" style="color:var(--yo-brand);" href="register.php">Create Account</a></p>
      </div>
    </div>
  </div>
</div>

<script>
function toggleAuthPw(id, btn) {
  const el = document.getElementById(id);
  if (!el) return;
  if (el.type === 'password') {
    el.type = 'text';
    btn.textContent = '🔒';
    btn.setAttribute('title', 'Hide password');
  } else {
    el.type = 'password';
    btn.textContent = '👁️';
    btn.setAttribute('title', 'Show password');
  }
}
</script>
</body>
</html>
