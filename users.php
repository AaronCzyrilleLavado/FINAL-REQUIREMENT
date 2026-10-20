<?php
require 'includes/config.php'; require_admin();
$title = 'Accounts'; $errors = []; $action = $_GET['action'] ?? 'list';
$row = [];
$staff = ['fullname' => '', 'username' => '', 'email' => '', 'phone' => ''];
$roleFilter = $_GET['role'] ?? 'user'; // Default to registered users as requested

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'create_staff') {
            $staff = [
                'fullname' => trim($_POST['fullname'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'email'    => trim($_POST['email'] ?? ''),
                'phone'    => trim($_POST['phone'] ?? '')
            ];
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm'] ?? '';

            if ($staff['fullname'] === '') $errors[] = 'Full name is required.';
            if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $staff['username'])) $errors[] = 'Username must be 4-30 alphanumeric characters or underscores.';
            if (!filter_var($staff['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
            if ($staff['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $staff['phone'])) $errors[] = 'Phone number format is invalid.';
            if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
            if ($password !== $confirm) $errors[] = 'Password confirmation does not match.';

            if (!$errors) {
                // Check duplicate username or email
                $dup = one("SELECT account_id FROM accounts WHERE username=? OR email=?", 'ss', [$staff['username'], $staff['email']]);
                if ($dup) {
                    $errors[] = 'That username or email address is already registered.';
                } else {
                    $roleColumn = mysqli_fetch_assoc(mysqli_query($conn, "SHOW COLUMNS FROM accounts LIKE 'accesslevel'"));
                    if (!$roleColumn || strpos($roleColumn['Type'], "'staff'") === false) {
                        $errors[] = "Database requires staff access level enabled.";
                        $action = 'add';
                    } else {
                        q("INSERT INTO accounts (fullname, username, email, phone, password, accesslevel) VALUES (?,?,?,?,?,'staff')",
                          'sssss', [$staff['fullname'], $staff['username'], $staff['email'], $staff['phone'], password_hash($password, PASSWORD_DEFAULT)]);
                        flash('success', 'Staff account created successfully.');
                        redirect('users.php?role=staff');
                    }
                }
            }
            if ($errors) $action = 'add';

        } elseif ($do === 'change_password') {
            $targetId = (int)($_POST['account_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $targetAcc = one("SELECT * FROM accounts WHERE account_id=?", 'i', [$targetId]);
            if (!$targetAcc) {
                $errors[] = 'Account not found.';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            } else {
                q("UPDATE accounts SET password=? WHERE account_id=?", 'si', [password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
                flash('success', 'Password changed successfully for ' . $targetAcc['fullname'] . ' (@' . $targetAcc['username'] . ').');
                redirect('users.php?role=' . urlencode($roleFilter));
            }
            $action = 'change_password';

        } elseif ($do === 'delete') {
            $targetId = (int)($_POST['id'] ?? 0);
            $acc = one("SELECT * FROM accounts WHERE account_id=?", 'i', [$targetId]);
            if (!$acc) {
                $errors[] = 'Account not found.';
            } elseif ($acc['accesslevel'] === 'admin') {
                $errors[] = 'The administrator account cannot be deleted.';
            } elseif ($acc['account_id'] == $_SESSION['account_id']) {
                $errors[] = 'You cannot delete your own logged-in account.';
            } else {
                q("DELETE FROM accounts WHERE account_id=?", 'i', [$targetId]);
                flash('success', 'Account deleted successfully.');
                redirect('users.php?role=' . urlencode($roleFilter));
            }

        } else { // Edit account details
            $row = [
                'account_id' => (int)($_POST['account_id'] ?? 0),
                'fullname'   => trim($_POST['fullname'] ?? ''),
                'email'      => trim($_POST['email'] ?? ''),
                'phone'      => trim($_POST['phone'] ?? ''),
                'address'    => trim($_POST['address'] ?? '')
            ];
            if ($row['fullname'] === '') $errors[] = 'Full name is required.';
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if ($row['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $row['phone'])) $errors[] = 'Phone format is invalid.';

            if (!$errors) {
                // Duplicate email check
                $dupEmail = one("SELECT account_id FROM accounts WHERE email=? AND account_id<>?", 'si', [$row['email'], $row['account_id']]);
                if ($dupEmail) {
                    $errors[] = 'That email address is already in use by another account.';
                } else {
                    q("UPDATE accounts SET fullname=?, email=?, phone=?, address=? WHERE account_id=?",
                      'ssssi', [$row['fullname'], $row['email'], $row['phone'], $row['address'], $row['account_id']]);
                    flash('success', 'Account updated successfully.');
                    redirect('users.php?role=' . urlencode($roleFilter));
                }
            }
            $action = 'edit';
        }
    } catch (mysqli_sql_exception $ex) {
        $errors[] = db_error_message($ex);
    }
}

if (($action === 'edit' || $action === 'change_password') && !$errors) {
    $row = one("SELECT * FROM accounts WHERE account_id=?", 'i', [(int)($_GET['id'] ?? 0)]);
    if (!$row) { flash('danger', 'Account not found.'); redirect('users.php'); }
}

// ── Optimized Counts ──
$countsRow = one("SELECT 
    COUNT(CASE WHEN accesslevel = 'user' THEN 1 END) AS user_count,
    COUNT(CASE WHEN accesslevel = 'staff' THEN 1 END) AS staff_count,
    COUNT(CASE WHEN accesslevel = 'admin' THEN 1 END) AS admin_count,
    COUNT(*) AS total_count
FROM accounts");

$userCount = (int)($countsRow['user_count'] ?? 0);
$staffCount = (int)($countsRow['staff_count'] ?? 0);
$adminCount = (int)($countsRow['admin_count'] ?? 0);
$totalCount = (int)($countsRow['total_count'] ?? 0);
$adminStaffCount = $staffCount + $adminCount;

// ── Query with Role Separation ──
$search = trim($_GET['search'] ?? '');
$sql = "SELECT a.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.account_id=a.account_id) AS order_count,
        (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.account_id=a.account_id AND o.status<>'Voided') AS spent,
        (SELECT COUNT(*) FROM orders o WHERE o.handled_by=a.account_id) AS handled_count
        FROM accounts a WHERE (a.fullname LIKE ? OR a.username LIKE ? OR a.email LIKE ? OR a.phone LIKE ?)";
$types = 'ssss';
$params = ["%$search%", "%$search%", "%$search%", "%$search%"];

if ($roleFilter === 'user') {
    $sql .= " AND a.accesslevel='user'";
} elseif ($roleFilter === 'admin_staff') {
    $sql .= " AND a.accesslevel IN ('admin','staff')";
} elseif ($roleFilter === 'admin') {
    $sql .= " AND a.accesslevel='admin'";
} elseif ($roleFilter === 'staff') {
    $sql .= " AND a.accesslevel='staff'";
}

$sql .= " ORDER BY FIELD(a.accesslevel, 'admin', 'staff', 'user'), a.fullname ASC";
$list = rows(q($sql, $types, $params));

include 'includes/header.php';
?>

<?php if ($action === 'add'): ?>
<!-- ── Form: Create Staff Account ── -->
<div class="card" style="max-width:640px">
  <div class="card-body p-4">
    <h4 class="mb-1">Create Staff Account</h4>
    <p class="text-muted mb-3">Add a team member with staff-level permissions to process orders and track inventory.</p>
    <form method="post"><input type="hidden" name="do" value="create_staff">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Full Name</label>
          <input class="form-control" name="fullname" value="<?= e($staff['fullname']) ?>" required placeholder="Staff member's full name">
        </div>
        <div class="col-md-6">
          <label class="form-label">Username</label>
          <input class="form-control" name="username" value="<?= e($staff['username']) ?>" required placeholder="4-30 characters">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-control" name="email" value="<?= e($staff['email']) ?>" required placeholder="staff@example.com">
        </div>
        <div class="col-12">
          <label class="form-label">Phone Number <span class="fw-normal text-muted">(optional)</span></label>
          <input class="form-control" name="phone" value="<?= e($staff['phone']) ?>" placeholder="e.g. 0912 345 6789">
        </div>
        <div class="col-md-6">
          <label class="form-label">Temporary Password</label>
          <div class="input-password-wrap">
            <input id="staff_pw" type="password" class="form-control" name="password" required minlength="6" placeholder="Min 6 characters">
            <button type="button" class="btn-password-toggle" onclick="togglePw('staff_pw', this)">👁️</button>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password</label>
          <div class="input-password-wrap">
            <input id="staff_conf" type="password" class="form-control" name="confirm" required minlength="6" placeholder="Re-enter password">
            <button type="button" class="btn-password-toggle" onclick="togglePw('staff_conf', this)">👁️</button>
          </div>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button class="btn btn-yo">Create Staff Account</button>
          <a class="btn btn-outline-secondary" href="users.php?role=admin_staff">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>

<?php elseif ($action === 'change_password'): ?>
<!-- ── Form: Admin Change Password for Staff ── -->
<div class="card" style="max-width:600px">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div>
        <h4 class="mb-1">Change Account Password</h4>
        <p class="text-muted mb-0">Set a new password for <strong><?= e($row['fullname']) ?></strong> (<code><?= e($row['username']) ?></code>)</p>
      </div>
      <span class="badge bg-<?= $row['accesslevel'] === 'admin' ? 'danger' : ($row['accesslevel'] === 'staff' ? 'primary' : 'secondary') ?>">
        <?= e(strtoupper($row['accesslevel'] === 'user' ? 'REGISTERED USER' : $row['accesslevel'])) ?>
      </span>
    </div>
    <hr class="my-3">
    <form method="post">
      <input type="hidden" name="do" value="change_password">
      <input type="hidden" name="account_id" value="<?= e($row['account_id']) ?>">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="admin_new_pw">New Password</label>
          <div class="input-password-wrap">
            <input id="admin_new_pw" type="password" class="form-control" name="new_password" required minlength="6" placeholder="Enter new password (min 6 characters)">
            <button type="button" class="btn-password-toggle" onclick="togglePw('admin_new_pw', this)">👁️</button>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label" for="admin_conf_pw">Confirm New Password</label>
          <div class="input-password-wrap">
            <input id="admin_conf_pw" type="password" class="form-control" name="confirm_password" required minlength="6" placeholder="Confirm new password">
            <button type="button" class="btn-password-toggle" onclick="togglePw('admin_conf_pw', this)">👁️</button>
          </div>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button class="btn btn-yo">Save New Password</button>
          <a class="btn btn-outline-secondary" href="users.php?role=<?= e($roleFilter) ?>">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>

<?php elseif ($action === 'edit'): ?>
<!-- ── Form: Edit Account Details ── -->
<div class="row g-4" style="max-width:850px">
  <div class="col-md-7">
    <div class="card p-4 h-100">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <h4 class="mb-1">Edit Account Details</h4>
          <p class="text-muted small mb-0">Update profile information for <strong><?= e($row['username'] ?? '') ?></strong></p>
        </div>
        <span class="badge bg-<?= $row['accesslevel'] === 'admin' ? 'danger' : ($row['accesslevel'] === 'staff' ? 'primary' : 'secondary') ?>">
          <?= e(strtoupper($row['accesslevel'] === 'user' ? 'REGISTERED USER' : $row['accesslevel'])) ?>
        </span>
      </div>
      <hr class="my-3">
      <form method="post"><input type="hidden" name="account_id" value="<?= e($row['account_id']) ?>">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Full Name</label>
            <input class="form-control" name="fullname" value="<?= e($row['fullname']) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" name="email" value="<?= e($row['email']) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Phone Number</label>
            <input class="form-control" name="phone" value="<?= e($row['phone']) ?>" placeholder="e.g. 0912 345 6789">
          </div>
          <div class="col-12">
            <label class="form-label">Delivery Address</label>
            <textarea class="form-control" name="address" rows="2"><?= e($row['address']) ?></textarea>
          </div>
          <div class="col-12 d-flex gap-2 pt-2">
            <button class="btn btn-yo">Save Details</button>
            <a class="btn btn-outline-secondary" href="users.php?role=<?= e($roleFilter) ?>">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Quick Password Change Panel for Admin -->
  <div class="col-md-5">
    <div class="card p-4 h-100">
      <h5 class="fw-bold mb-1">Reset Password</h5>
      <p class="text-muted small mb-3">Set a new password for this staff member or user.</p>
      <form method="post">
        <input type="hidden" name="do" value="change_password">
        <input type="hidden" name="account_id" value="<?= e($row['account_id']) ?>">
        <div class="mb-3">
          <label class="form-label" for="edit_pw">New Password</label>
          <div class="input-password-wrap">
            <input id="edit_pw" type="password" class="form-control form-control-sm" name="new_password" required minlength="6" placeholder="Min 6 characters">
            <button type="button" class="btn-password-toggle" onclick="togglePw('edit_pw', this)">👁️</button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="edit_conf">Confirm Password</label>
          <div class="input-password-wrap">
            <input id="edit_conf" type="password" class="form-control form-control-sm" name="confirm_password" required minlength="6" placeholder="Re-enter password">
            <button type="button" class="btn-password-toggle" onclick="togglePw('edit_conf', this)">👁️</button>
          </div>
        </div>
        <button class="btn btn-outline-primary btn-sm w-100">Update Password</button>
      </form>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── Accounts Listing View ── -->
<div class="page-header">
  <div>
    <h3>Account Management</h3>
    <p class="text-muted small mb-0">Manage registered customers and staff management accounts with dedicated views.</p>
  </div>
  <a class="btn btn-yo" href="?action=add">+ Add Staff Account</a>
</div>

<!-- Overview Stats Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card stat-card p-3 text-center h-100">
      <div class="display-6"><?= number_format($userCount) ?></div>
      <div class="text-muted small fw-semibold">Registered Users</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card p-3 text-center h-100">
      <div class="display-6 text-primary"><?= number_format($staffCount) ?></div>
      <div class="text-muted small fw-semibold">Staff Accounts</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card p-3 text-center h-100">
      <div class="display-6 text-danger"><?= number_format($adminCount) ?></div>
      <div class="text-muted small fw-semibold">Administrators</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card p-3 text-center h-100">
      <div class="display-6 text-dark"><?= number_format($totalCount) ?></div>
      <div class="text-muted small fw-semibold">Total Accounts</div>
    </div>
  </div>
</div>

<!-- Segmented Role Tabs for Instant Switching -->
<ul class="nav nav-tabs-custom" role="tablist">
  <li class="nav-item">
    <a class="nav-link <?= $roleFilter === 'user' ? 'active' : '' ?>" href="?role=user<?= $search ? '&search=' . urlencode($search) : '' ?>">
      👤 Registered Users Only <span class="badge bg-secondary"><?= $userCount ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= ($roleFilter === 'admin_staff' || $roleFilter === 'staff' || $roleFilter === 'admin') ? 'active' : '' ?>" href="?role=admin_staff<?= $search ? '&search=' . urlencode($search) : '' ?>">
      🛡️ Admin &amp; Staff Roles <span class="badge bg-primary"><?= $adminStaffCount ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $roleFilter === '' ? 'active' : '' ?>" href="?role=<?= $search ? '&search=' . urlencode($search) : '' ?>">
      📋 All Accounts <span class="badge bg-dark"><?= $totalCount ?></span>
    </a>
  </li>
</ul>

<!-- Automated Filter Bar: Purely automated, no trigger button, just Reset Filter -->
<form class="filter-bar auto-filter" method="get">
  <div class="row g-2 align-items-center">
    <div class="col-md-5 col-lg-5">
      <input type="search" class="form-control" name="search" placeholder="Search by name, username, email, phone..." value="<?= e($search) ?>" aria-label="Search accounts">
    </div>
    <div class="col-md-4 col-lg-4">
      <select class="form-select" name="role" aria-label="Filter by account role">
        <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Registered Users Only (<?= $userCount ?>)</option>
        <option value="admin_staff" <?= $roleFilter === 'admin_staff' ? 'selected' : '' ?>>Admin &amp; Staff Roles (<?= $adminStaffCount ?>)</option>
        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrators Only (<?= $adminCount ?>)</option>
        <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Staff Only (<?= $staffCount ?>)</option>
        <option value="" <?= $roleFilter === '' ? 'selected' : '' ?>>All Accounts (<?= $totalCount ?>)</option>
      </select>
    </div>
    <div class="col-md-3 col-lg-3">
      <a class="reset-link" href="users.php?role=<?= e($roleFilter) ?>">↺ Reset Filter</a>
    </div>
  </div>
</form>

<!-- Table with Dynamic Columns: Separated Staff vs Customer requirements -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <?php if ($roleFilter === 'admin_staff' || $roleFilter === 'staff' || $roleFilter === 'admin'): ?>
            <!-- Staff & Admin Specific Columns (Orders and Total Spent REMOVED) -->
            <th>Staff / Admin Name</th>
            <th>Username</th>
            <th>Contact / Phone</th>
            <th>Role</th>
            <th>Orders Processed</th>
            <th>Joined</th>
            <th width="190">Actions</th>
          <?php elseif ($roleFilter === 'user'): ?>
            <!-- Registered Users Specific Columns (Orders and Total Spent DISPLAYED) -->
            <th>Customer Name</th>
            <th>Username</th>
            <th>Delivery Address</th>
            <th>Orders Placed</th>
            <th>Total Spent</th>
            <th>Joined</th>
            <th width="190">Actions</th>
          <?php else: ?>
            <!-- All Accounts View -->
            <th>User / Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Activity</th>
            <th>Total Spent</th>
            <th>Joined</th>
            <th width="190">Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $u): ?>
        <tr>
          <?php if ($roleFilter === 'admin_staff' || $roleFilter === 'staff' || $roleFilter === 'admin'): ?>
            <!-- Staff Table Row -->
            <td>
              <b><?= e($u['fullname']) ?></b><br>
              <small class="text-muted"><?= e($u['email']) ?></small>
            </td>
            <td><code><?= e($u['username']) ?></code></td>
            <td><?= e($u['phone'] ?: 'No phone') ?></td>
            <td>
              <span class="badge bg-<?= $u['accesslevel'] === 'admin' ? 'danger' : 'primary' ?>">
                <?= e(strtoupper($u['accesslevel'])) ?>
              </span>
            </td>
            <td>
              <span class="badge bg-light text-dark border"><?= (int)$u['handled_count'] ?> processed</span>
            </td>
            <td><small class="text-muted"><?= e(date('M d, Y', strtotime($u['created_at']))) ?></small></td>

          <?php elseif ($roleFilter === 'user'): ?>
            <!-- Registered Customer Table Row -->
            <td>
              <b><?= e($u['fullname']) ?></b><br>
              <small class="text-muted"><?= e($u['email']) ?></small>
              <?php if ($u['phone']): ?><br><small class="text-muted">📞 <?= e($u['phone']) ?></small><?php endif; ?>
            </td>
            <td><code><?= e($u['username']) ?></code></td>
            <td><small class="text-muted"><?= e($u['address'] ?: 'None recorded') ?></small></td>
            <td><span class="badge bg-light text-dark border"><?= (int)$u['order_count'] ?> orders</span></td>
            <td class="fw-semibold text-nowrap"><?= peso($u['spent']) ?></td>
            <td><small class="text-muted"><?= e(date('M d, Y', strtotime($u['created_at']))) ?></small></td>

          <?php else: ?>
            <!-- All Accounts Table Row -->
            <td>
              <b><?= e($u['fullname']) ?></b><br>
              <small class="text-muted"><?= e($u['email']) ?></small>
            </td>
            <td><code><?= e($u['username']) ?></code></td>
            <td>
              <span class="badge bg-<?= $u['accesslevel'] === 'admin' ? 'danger' : ($u['accesslevel'] === 'staff' ? 'primary' : 'secondary') ?>">
                <?= e($u['accesslevel'] === 'user' ? 'CUSTOMER' : strtoupper($u['accesslevel'])) ?>
              </span>
            </td>
            <td>
              <?php if ($u['accesslevel'] === 'user'): ?>
                <span class="badge bg-light text-dark border"><?= (int)$u['order_count'] ?> placed</span>
              <?php else: ?>
                <span class="badge bg-light text-dark border"><?= (int)$u['handled_count'] ?> handled</span>
              <?php endif; ?>
            </td>
            <td><?= $u['accesslevel'] === 'user' ? ('<span class="fw-semibold">' . peso($u['spent']) . '</span>') : '<span class="text-muted">—</span>' ?></td>
            <td><small class="text-muted"><?= e(date('M d, Y', strtotime($u['created_at']))) ?></small></td>
          <?php endif; ?>

          <td>
            <div class="action-btns">
              <a class="btn btn-sm btn-outline-primary" href="?action=edit&amp;id=<?= $u['account_id'] ?>&amp;role=<?= e($roleFilter) ?>">Edit</a>
              <a class="btn btn-sm btn-outline-secondary" href="?action=change_password&amp;id=<?= $u['account_id'] ?>&amp;role=<?= e($roleFilter) ?>" title="Change password">🔑 Password</a>
              <?php if ($u['accesslevel'] !== 'admin' && $u['account_id'] != $_SESSION['account_id']): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this account and its records? This cannot be undone.')">
                  <input type="hidden" name="do" value="delete">
                  <input type="hidden" name="id" value="<?= $u['account_id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?>
        <tr><td colspan="7" class="empty-state"><p>No accounts match your filter criteria.</p></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function togglePw(inputId, btn) {
  const el = document.getElementById(inputId);
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

<?php endif; include 'includes/footer.php'; ?>
