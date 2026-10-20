<?php
require 'includes/config.php';
if (isset($_SESSION['account_id'])) { redirect('dashboard.php'); }
$errors = [];
$f = ['fullname' => '', 'username' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) { $f[$k] = trim($_POST[$k] ?? ''); }
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm'] ?? '';

    if ($f['fullname'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $f['username'])) {
        $errors[] = 'Username must be 4 to 30 characters (letters, numbers, or underscores).';
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($f['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $f['phone'])) {
        $errors[] = 'Phone number format is invalid.';
    }
    if (strlen($pw) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($pw !== $pw2) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            // Check existing username or email
            $dup = one("SELECT account_id FROM accounts WHERE username=? OR email=?", 'ss', [$f['username'], $f['email']]);
            if ($dup) {
                $errors[] = 'That username or email is already registered. Please log in instead.';
            } else {
                q("INSERT INTO accounts (fullname, username, email, phone, address, password, accesslevel) VALUES (?,?,?,?,?,?,'user')",
                  'ssssss', [$f['fullname'], $f['username'], $f['email'], $f['phone'], $f['address'], password_hash($pw, PASSWORD_DEFAULT)]);
                flash('success', 'Registration successful! You may now sign in.');
                redirect('index.php');
            }
        } catch (mysqli_sql_exception $ex) {
            error_log($ex->getMessage());
            $errors[] = ($ex->getCode() == 1062) ? 'That username or email is already registered.' : 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account | The Yo's</title>
<link href="bootstrap-5.3.2-dist/css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card" style="max-width:540px">
    <div class="card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="auth-brand-badge">🍽️</div>
        <h3 class="fw-bold mb-1">Create an Account</h3>
        <p class="text-muted small mb-0">Register with The Yo's for fast food ordering and delivery</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 mb-3">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="on" novalidate>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="reg_fullname">Full Name</label>
            <input id="reg_fullname" class="form-control" name="fullname" value="<?= e($f['fullname']) ?>" required placeholder="e.g. Maria Santos">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_username">Username</label>
            <input id="reg_username" class="form-control" name="username" value="<?= e($f['username']) ?>" required placeholder="4-30 characters">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_email">Email Address</label>
            <input id="reg_email" type="email" class="form-control" name="email" value="<?= e($f['email']) ?>" required placeholder="name@example.com">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_phone">Phone Number <small class="text-muted">(optional)</small></label>
            <input id="reg_phone" class="form-control" name="phone" value="<?= e($f['phone']) ?>" placeholder="e.g. 0912 345 6789">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_address">Delivery Address</label>
            <input id="reg_address" class="form-control" name="address" value="<?= e($f['address']) ?>" placeholder="Street, Barangay, City">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_pw">Password</label>
            <div class="input-password-wrap">
              <input id="reg_pw" type="password" class="form-control" name="password" required minlength="6" placeholder="Min 6 characters">
              <button type="button" class="btn-password-toggle" onclick="toggleAuthPw('reg_pw', this)">👁️</button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_conf">Confirm Password</label>
            <div class="input-password-wrap">
              <input id="reg_conf" type="password" class="form-control" name="confirm" required minlength="6" placeholder="Re-enter password">
              <button type="button" class="btn-password-toggle" onclick="toggleAuthPw('reg_conf', this)">👁️</button>
            </div>
          </div>
        </div>

        <button class="btn btn-yo w-100 py-2 fs-6 mt-4 mb-3">Complete Registration</button>
      </form>

      <div class="text-center pt-2 border-top">
        <p class="text-muted small mb-0">Already registered? <a class="fw-semibold text-decoration-none" style="color:var(--yo-brand);" href="index.php">Sign In</a></p>
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
  } else {
    el.type = 'password';
    btn.textContent = '👁️';
  }
}
</script>
</body>
</html>
