<?php
require 'includes/config.php';
require_login();

$title = 'My Profile & Account';
$errors = [];
$uid = (int)$_SESSION['account_id'];

// Fetch current user account details
$user = one("SELECT * FROM accounts WHERE account_id=?", 'i', [$uid]);
if (!$user) {
    flash('danger', 'Account record not found.');
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. Update Profile Details ──
    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        if ($fullname === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $phone)) {
            $errors[] = 'Phone number format is invalid (7-20 numbers or symbols).';
        }

        if (!$errors) {
            try {
                // Check if email belongs to someone else
                $existing = one("SELECT account_id FROM accounts WHERE email=? AND account_id<>?", 'si', [$email, $uid]);
                if ($existing) {
                    $errors[] = 'That email address is already in use by another account.';
                } else {
                    q("UPDATE accounts SET fullname=?, email=?, phone=?, address=? WHERE account_id=?",
                      'ssssi', [$fullname, $email, $phone, $address, $uid]);
                    $_SESSION['fullname'] = $fullname; // Sync session
                    flash('success', 'Profile details updated successfully.');
                    redirect('profile.php');
                }
            } catch (mysqli_sql_exception $ex) {
                $errors[] = db_error_message($ex);
            }
        }
    }

    // ── 2. Change Password ──
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $errors[] = 'Please enter your current password.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (!$errors) {
            try {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                q("UPDATE accounts SET password=? WHERE account_id=?", 'si', [$hash, $uid]);
                flash('success', 'Your password has been changed successfully.');
                redirect('profile.php');
            } catch (mysqli_sql_exception $ex) {
                $errors[] = db_error_message($ex);
            }
        }
    }
}

// Refresh user data if changed
$user = one("SELECT * FROM accounts WHERE account_id=?", 'i', [$uid]);
include 'includes/header.php';
?>

<div class="page-header mb-4">
  <div>
    <h3 class="mb-1">My Account &amp; Profile</h3>
    <p class="text-muted small mb-0">Manage your personal profile, contact information, and security credentials.</p>
  </div>
</div>

<div class="row g-4">
  <!-- Left Column: User Summary Card -->
  <div class="col-lg-4">
    <div class="card text-center p-4 mb-3">
      <div class="mx-auto mb-3" style="width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg, #b8431f, #d4723c); display:grid; place-items:center; color:#fff; font-size:1.8rem; font-weight:800; box-shadow:0 6px 18px rgba(184,67,31,.25);">
        <?= e(strtoupper(substr($user['fullname'], 0, 1))) ?>
      </div>
      <h5 class="mb-1 fw-bold"><?= e($user['fullname']) ?></h5>
      <p class="text-muted small mb-2">@<?= e($user['username']) ?></p>
      <div>
        <?php if ($user['accesslevel'] === 'admin'): ?>
          <span class="badge bg-danger px-3 py-1 fs-6">ADMINISTRATOR</span>
        <?php elseif ($user['accesslevel'] === 'staff'): ?>
          <span class="badge bg-primary px-3 py-1 fs-6">STAFF MEMBER</span>
        <?php else: ?>
          <span class="badge bg-secondary px-3 py-1 fs-6">REGISTERED CUSTOMER</span>
        <?php endif; ?>
      </div>

      <hr class="my-3">

      <div class="text-start small">
        <div class="mb-2"><strong>Email:</strong> <span class="text-muted"><?= e($user['email']) ?></span></div>
        <div class="mb-2"><strong>Phone:</strong> <span class="text-muted"><?= e($user['phone'] ?: 'None provided') ?></span></div>
        <div class="mb-2"><strong>Address:</strong> <span class="text-muted"><?= e($user['address'] ?: 'None provided') ?></span></div>
        <div><strong>Joined:</strong> <span class="text-muted"><?= e(date('F j, Y', strtotime($user['created_at']))) ?></span></div>
      </div>
    </div>
  </div>

  <!-- Right Column: Profile & Password Tabs -->
  <div class="col-lg-8">
    <!-- Profile Edit Card -->
    <div class="card p-4 mb-4">
      <h5 class="fw-bold mb-1">Edit Contact Information</h5>
      <p class="text-muted small mb-3">Keep your name and contact details up to date.</p>
      
      <form method="post">
        <input type="hidden" name="action" value="update_profile">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="prof_fullname">Full Name</label>
            <input id="prof_fullname" class="form-control" name="fullname" value="<?= e($user['fullname']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="prof_username">Username <small class="text-muted">(Read-only)</small></label>
            <input id="prof_username" class="form-control bg-light" value="<?= e($user['username']) ?>" readonly disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="prof_email">Email Address</label>
            <input id="prof_email" type="email" class="form-control" name="email" value="<?= e($user['email']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="prof_phone">Phone Number</label>
            <input id="prof_phone" class="form-control" name="phone" value="<?= e($user['phone']) ?>" placeholder="e.g. 0912 345 6789">
          </div>
          <div class="col-12">
            <label class="form-label" for="prof_address">Delivery Address</label>
            <textarea id="prof_address" class="form-control" name="address" rows="2" placeholder="Your street address or delivery notes"><?= e($user['address']) ?></textarea>
          </div>
          <div class="col-12 pt-2">
            <button class="btn btn-yo px-4">Save Profile Changes</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Change Password Card -->
    <div class="card p-4">
      <h5 class="fw-bold mb-1">Security &amp; Password</h5>
      <p class="text-muted small mb-3">Ensure your account is protected with a strong password.</p>
      
      <form method="post">
        <input type="hidden" name="action" value="change_password">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="curr_pw">Current Password</label>
            <div class="input-password-wrap">
              <input id="curr_pw" type="password" class="form-control" name="current_password" required placeholder="Enter your current password">
              <button type="button" class="btn-password-toggle" onclick="togglePassword('curr_pw', this)" aria-label="Toggle password visibility">
                👁️
              </button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="new_pw">New Password</label>
            <div class="input-password-wrap">
              <input id="new_pw" type="password" class="form-control" name="new_password" required minlength="6" placeholder="At least 6 characters">
              <button type="button" class="btn-password-toggle" onclick="togglePassword('new_pw', this)" aria-label="Toggle password visibility">
                👁️
              </button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="conf_pw">Confirm New Password</label>
            <div class="input-password-wrap">
              <input id="conf_pw" type="password" class="form-control" name="confirm_password" required minlength="6" placeholder="Re-type new password">
              <button type="button" class="btn-password-toggle" onclick="togglePassword('conf_pw', this)" aria-label="Toggle password visibility">
                👁️
              </button>
            </div>
          </div>
          <div class="col-12 pt-2">
            <button class="btn btn-yo px-4">Update Password</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🔒';
    btn.setAttribute('title', 'Hide password');
  } else {
    input.type = 'password';
    btn.textContent = '👁️';
    btn.setAttribute('title', 'Show password');
  }
}
</script>

<?php include 'includes/footer.php'; ?>
