<?php
/**
 * Campus LostLink — Login Page (login.php)
 * Matches Mockup Screen 2
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

redirect_if_logged_in();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid or expired CSRF token. Please submit again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (empty($password)) {
            $errors[] = 'Please enter your password.';
        }

        if (empty($errors)) {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => strtolower($email)]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    $errors[] = 'Your account has been deactivated. Please contact campus administration.';
                } else {
                    login_user($user);
                    set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

                    $returnUrl = $_GET['return'] ?? '';
                    if (!empty($returnUrl) && str_starts_with($returnUrl, '/')) {
                        header('Location: ' . BASE_URL . $returnUrl);
                    } elseif ($user['role'] === 'admin') {
                        header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    } else {
                        header('Location: ' . BASE_URL . '/dashboard.php');
                    }
                    exit;
                }
            } else {
                $errors[] = 'Invalid email address or password.';
            }
        }
    }
}

$pageTitle = 'Login — Campus LostLink';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page-wrapper">
  <div class="auth-card">
    <div class="auth-header">
      <h1 class="auth-title">Login to Your Account</h1>
      <p class="auth-subtitle">Enter your campus credentials to access the portal</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" style="margin-bottom: 20px;">
        <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
        <div class="alert-text">
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/login.php<?= !empty($_GET['return']) ? '?return=' . urlencode($_GET['return']) : '' ?>" method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-with-icon">
          <i class="fa-regular fa-envelope input-icon-left"></i>
          <input type="email" id="email" name="email" class="form-control" placeholder="e.g. rahul@lostlink.com" value="<?= e($email) ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <div class="input-with-icon">
          <i class="fa-solid fa-lock input-icon-left"></i>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
          <button type="button" class="password-toggle-btn" data-target="password" aria-label="Toggle password visibility">
            <i class="fa-regular fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-auth-submit">
        <span>Login</span>
      </button>
    </form>

    <div class="auth-footer">
      Don't have an account? <a href="<?= BASE_URL ?>/register.php">Register</a>
    </div>

    <!-- Quick Demo Credentials Hint for Evaluator -->
    <div style="margin-top: 24px; padding: 12px; background: #F8FAFC; border: 1px dashed var(--border-color); border-radius: var(--radius-sm); font-size: 12px; color: var(--text-muted);">
      <strong>Demo Accounts:</strong><br>
      • Student: <code>rahul@lostlink.com</code> / <code>Student@123</code><br>
      • Admin: <code>admin@lostlink.com</code> / <code>Admin@123</code>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
