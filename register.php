<?php
require 'includes/config.php';
if (isset($_SESSION['account_id'])) { redirect('dashboard.php'); }
$errors = [];
$f = ['fullname' => '', 'username' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) { $f[$k] = trim($_POST[$k] ?? ''); }
    $pw = $_POST['password'] ?? ''; $pw2 = $_POST['confirm'] ?? '';

    if ($f['fullname'] === '')                                   $errors[] = 'Full name is required.';
    if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $f['username']))   $errors[] = 'Username must be 4-30 letters, numbers or underscores.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL))         $errors[] = 'Enter a valid email address.';
    if ($f['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $f['phone'])) $errors[] = 'Phone number format is invalid.';
    if (strlen($pw) < 6)                                         $errors[] = 'Password must be at least 6 characters.';
    if ($pw !== $pw2)                                            $errors[] = 'Passwords do not match.';

    if (!$errors) {
        try {
            // accesslevel is ALWAYS 'user' here; the single admin is created in the database script
            q("INSERT INTO accounts (fullname, username, email, phone, address, password, accesslevel) VALUES (?,?,?,?,?,?,'user')",
              'ssssss', [$f['fullname'], $f['username'], $f['email'], $f['phone'], $f['address'], password_hash($pw, PASSWORD_DEFAULT)]);
            flash('success', 'Registration successful! You can now log in.');
            redirect('index.php');
        } catch (mysqli_sql_exception $ex) {
            error_log($ex->getMessage());
            $errors[] = ($ex->getCode() == 1062) ? 'That username or email is already registered.' : 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register | The Yo's</title>
<link href="bootstrap-5.3.2-dist/css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
</head><body>
<div class="auth-wrap"><div class="auth-card p-3" style="max-width:560px">
  <div class="card p-4">
    <h4 class="text-center">Create your account</h4>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
      <div class="row g-2">
        <div class="col-12"><label class="form-label">Full name</label><input class="form-control" name="fullname" value="<?= e($f['fullname']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($f['username']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($f['email']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($f['phone']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Delivery address</label><input class="form-control" name="address" value="<?= e($f['address']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Password</label><input type="password" class="form-control" name="password"></div>
        <div class="col-md-6"><label class="form-label">Confirm password</label><input type="password" class="form-control" name="confirm"></div>
      </div>
      <button class="btn btn-yo w-100 mt-3">Register</button>
    </form>
    <p class="text-center mt-3 mb-0">Already registered? <a href="index.php">Log in</a></p>
  </div>
</div></div>
</body></html>
