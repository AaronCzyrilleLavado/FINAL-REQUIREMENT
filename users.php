<?php
require 'includes/config.php'; require_admin();
$title = 'Accounts'; $errors = []; $action = $_GET['action'] ?? 'list';
$row = [];
$staff = ['fullname' => '', 'username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['do'] ?? '') === 'create_staff') {
            $staff = ['fullname' => trim($_POST['fullname'] ?? ''), 'username' => trim($_POST['username'] ?? ''),
                      'email' => trim($_POST['email'] ?? ''), 'phone' => trim($_POST['phone'] ?? '')];
            $password = $_POST['password'] ?? ''; $confirm = $_POST['confirm'] ?? '';
            if ($staff['fullname'] === '') $errors[] = 'Full name is required.';
            if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $staff['username'])) $errors[] = 'Username must be 4-30 letters, numbers or underscores.';
            if (!filter_var($staff['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if ($staff['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $staff['phone'])) $errors[] = 'Phone number format is invalid.';
            if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
            if ($password !== $confirm) $errors[] = 'Passwords do not match.';
            if (!$errors) {
                q("INSERT INTO accounts (fullname, username, email, phone, password, accesslevel) VALUES (?,?,?,?,?,'staff')",
                  'sssss', [$staff['fullname'], $staff['username'], $staff['email'], $staff['phone'], password_hash($password, PASSWORD_DEFAULT)]);
                flash('success', 'Staff account created. Share the login credentials securely.'); redirect('users.php');
            }
            $action = 'add';
        } elseif (($_POST['do'] ?? '') === 'delete') {
            $acc = one("SELECT accesslevel FROM accounts WHERE account_id=?", 'i', [(int)$_POST['id']]);
            if (!$acc)                           $errors[] = 'User not found.';
            elseif ($acc['accesslevel'] === 'admin') $errors[] = 'The administrator account cannot be deleted.';
            else { q("DELETE FROM accounts WHERE account_id=?", 'i', [(int)$_POST['id']]); flash('success', 'Account deleted.'); redirect('users.php'); }
        } else {   // edit contact info only: roles are never changed, so there is always exactly one admin
            $row = ['account_id' => (int)$_POST['account_id'], 'fullname' => trim($_POST['fullname'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? ''), 'address' => trim($_POST['address'] ?? '')];
            if ($row['fullname'] === '') $errors[] = 'Full name is required.';
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if (!$errors) {
                q("UPDATE accounts SET fullname=?, email=?, phone=?, address=? WHERE account_id=?", 'ssssi', [$row['fullname'], $row['email'], $row['phone'], $row['address'], $row['account_id']]);
                flash('success', 'Account updated.'); redirect('users.php');
            }
            $action = 'edit';
        }
    } catch (mysqli_sql_exception $ex) {
        $errors[] = (($_POST['do'] ?? '') === 'create_staff' && $ex->getCode() == 1062)
            ? 'That username or email is already registered.' : db_error_message($ex);
        if (($_POST['do'] ?? '') === 'create_staff') $action = 'add';
    }
}
if ($action === 'edit' && !$errors) {
    $row = one("SELECT * FROM accounts WHERE account_id=?", 'i', [(int)($_GET['id'] ?? 0)]);
    if (!$row) { flash('danger', 'User not found.'); redirect('users.php'); }
}
$search = trim($_GET['search'] ?? ''); $role = $_GET['role'] ?? '';
$sql = "SELECT a.*, (SELECT COUNT(*) FROM orders o WHERE o.account_id=a.account_id) AS order_count,
        (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.account_id=a.account_id AND o.status<>'Voided') AS spent
        FROM accounts a WHERE (a.fullname LIKE ? OR a.username LIKE ? OR a.email LIKE ?)";
$t = 'sss'; $p = ["%$search%", "%$search%", "%$search%"];
if (in_array($role, ['admin', 'staff', 'user'], true)) { $sql .= " AND a.accesslevel=?"; $t .= 's'; $p[] = $role; }
$list = rows(q($sql . " ORDER BY a.accesslevel, a.fullname", $t, $p));
include 'includes/header.php';
?>
<?php if ($action === 'add'): ?>
<div class="card" style="max-width:600px"><div class="card-body"><h4>Create staff account</h4>
  <form method="post"><input type="hidden" name="do" value="create_staff">
    <label class="form-label">Full name</label><input class="form-control mb-2" name="fullname" value="<?= e($staff['fullname']) ?>" required>
    <label class="form-label">Username</label><input class="form-control mb-2" name="username" value="<?= e($staff['username']) ?>" required>
    <label class="form-label">Email</label><input type="email" class="form-control mb-2" name="email" value="<?= e($staff['email']) ?>" required>
    <label class="form-label">Phone (optional)</label><input class="form-control mb-2" name="phone" value="<?= e($staff['phone']) ?>">
    <label class="form-label">Temporary password</label><input type="password" class="form-control mb-2" name="password" required>
    <label class="form-label">Confirm password</label><input type="password" class="form-control mb-3" name="confirm" required>
    <button class="btn btn-yo">Create staff account</button> <a class="btn btn-outline-secondary" href="users.php">Cancel</a></form></div></div>
<?php elseif ($action === 'edit'): ?>
<div class="card" style="max-width:600px"><div class="card-body"><h4>Edit user: <?= e($row['username'] ?? '') ?></h4>
  <form method="post"><input type="hidden" name="account_id" value="<?= e($row['account_id']) ?>">
    <label class="form-label">Full name</label><input class="form-control mb-2" name="fullname" value="<?= e($row['fullname']) ?>">
    <label class="form-label">Email</label><input class="form-control mb-2" name="email" value="<?= e($row['email']) ?>">
    <label class="form-label">Phone</label><input class="form-control mb-2" name="phone" value="<?= e($row['phone']) ?>">
    <label class="form-label">Address</label><input class="form-control mb-3" name="address" value="<?= e($row['address']) ?>">
    <button class="btn btn-yo">Save</button> <a class="btn btn-outline-secondary" href="users.php">Cancel</a></form></div></div>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">Accounts</h3><a class="btn btn-yo" href="?action=add">+ Add staff account</a></div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input class="form-control" name="search" placeholder="Name, username or email" value="<?= e($search) ?>"></div>
  <div class="col-md-3"><select class="form-select" name="role"><option value="">All roles</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option><option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option><option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Customer</option></select></div>
  <div class="col-md-4"><button class="btn btn-yo">Filter</button> <a class="btn btn-outline-secondary" href="users.php">Reset</a></div>
</form>
<div class="card"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Orders</th><th>Total spent</th><th>Joined</th><th width="150">Actions</th></tr></thead><tbody>
<?php foreach ($list as $u): ?><tr><td><b><?= e($u['fullname']) ?></b><br><small class="text-muted"><?= e($u['email']) ?></small></td><td><?= e($u['username']) ?></td>
  <td><span class="badge bg-<?= $u['accesslevel'] === 'admin' ? 'danger' : ($u['accesslevel'] === 'staff' ? 'primary' : 'secondary') ?>"><?= e($u['accesslevel'] === 'user' ? 'CUSTOMER' : strtoupper($u['accesslevel'])) ?></span></td>
  <td><?= $u['order_count'] ?></td><td><?= peso($u['spent']) ?></td><td><?= e(date('M d, Y', strtotime($u['created_at']))) ?></td>
  <td><a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?= $u['account_id'] ?>">Edit</a>
  <?php if ($u['accesslevel'] !== 'admin'): ?><form method="post" class="d-inline" onsubmit="return confirm('Delete this user and their orders?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $u['account_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$list) echo "<tr><td colspan='7' class='text-center text-muted'>No users found.</td></tr>"; ?>
</tbody></table></div>
<?php endif; include 'includes/footer.php'; ?>
