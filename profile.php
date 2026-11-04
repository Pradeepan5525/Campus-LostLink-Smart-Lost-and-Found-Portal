<?php
/**
 * Campus LostLink — User Profile (profile.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$userId = user_id();
$pdo = get_db_connection();

$errors = [];
$successMessage = '';

// Fetch current user details
$stmt = $pdo->prepare('SELECT id, full_name, email, role, created_at FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$userData = $stmt->fetch();

if (!$userData) {
    logout_user();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid or expired CSRF token.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($fullName) || mb_strlen($fullName) < 2) {
            $errors[] = 'Full name must be at least 2 characters long.';
        }

        // Update name
        if (empty($errors)) {
            $upName = $pdo->prepare('UPDATE users SET full_name = :full_name WHERE id = :id');
            $upName->execute(['full_name' => $fullName, 'id' => $userId]);
            $_SESSION['full_name'] = $fullName;
            $userData['full_name'] = $fullName;
            $successMessage = 'Profile name updated successfully.';
        }

        // Password change if provided
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                $errors[] = 'Current password is required to change password.';
            } else {
                $passStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
                $passStmt->execute(['id' => $userId]);
                $hash = $passStmt->fetchColumn();

                if (!password_verify($currentPassword, $hash)) {
                    $errors[] = 'Current password is incorrect.';
                } elseif (strlen($newPassword) < 6) {
                    $errors[] = 'New password must be at least 6 characters.';
                } elseif ($newPassword !== $confirmPassword) {
                    $errors[] = 'New password and confirmation do not match.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $upPass = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                    $upPass->execute(['hash' => $newHash, 'id' => $userId]);
                    $successMessage = 'Profile and password updated successfully!';
                }
            }
        }
    }
}

$pageTitle = 'Profile Settings — Campus LostLink';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<div class="portal-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <div class="portal-main">
    <header class="portal-topbar">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button type="button" class="mobile-toggle-btn" onclick="document.getElementById('portalSidebar').classList.toggle('open');">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">Profile Settings</span>
      </div>

      <div class="topbar-nav">
        <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/profile.php" style="color: var(--primary-navy); font-weight: 600;">Profile</a>
        <a href="<?= BASE_URL ?>/logout.php" style="color: #DC2626;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
      </div>
    </header>

    <main class="portal-content">
      <?= render_flash() ?>

      <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check alert-icon"></i>
          <span><?= e($successMessage) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
          <div class="alert-text">
            <?php foreach ($errors as $err): ?>
              <div><?= e($err) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="page-header-row">
        <h1 class="page-title">Profile Settings</h1>
      </div>

      <div class="form-card" style="max-width: 680px;">
        <form action="<?= BASE_URL ?>/profile.php" method="POST">
          <?= csrf_field() ?>

          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="text" class="form-control no-icon" value="<?= e($userData['email']) ?>" disabled style="background: #F1F5F9; color: var(--text-muted);">
            <small style="color: var(--text-muted); font-size: 11px;">Email cannot be changed.</small>
          </div>

          <div class="form-group">
            <label class="form-label">Role</label>
            <input type="text" class="form-control no-icon" value="<?= ucfirst(e($userData['role'])) ?>" disabled style="background: #F1F5F9; color: var(--text-muted);">
          </div>

          <div class="form-group">
            <label for="full_name" class="form-label">Full Name *</label>
            <input type="text" id="full_name" name="full_name" class="form-control no-icon" value="<?= e($userData['full_name']) ?>" required>
          </div>

          <hr style="border: none; border-top: 1px solid var(--border-color); margin: 28px 0;">

          <h3 style="font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Change Password</h3>
          <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Leave blank if you do not want to change your password.</p>

          <div class="form-group">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" id="current_password" name="current_password" class="form-control no-icon" placeholder="••••••••">
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="new_password" class="form-label">New Password</label>
              <input type="password" id="new_password" name="new_password" class="form-control no-icon" placeholder="Minimum 6 characters">
            </div>

            <div class="form-group">
              <label for="confirm_password" class="form-label">Confirm New Password</label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control no-icon" placeholder="Re-enter new password">
            </div>
          </div>

          <button type="submit" class="btn-primary" style="margin-top: 10px;">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>Save Changes</span>
          </button>
        </form>
      </div>
    </main>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
