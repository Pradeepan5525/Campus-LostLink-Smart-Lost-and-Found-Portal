<?php
/**
 * Campus LostLink — Registration Page (register.php)
 * Matches Mockup Screen 3
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

redirect_if_logged_in();

$errors = [];
$fullName = '';
$email    = '';
$role     = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid or expired CSRF session token. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = trim($_POST['role'] ?? 'student');

        if (empty($fullName) || mb_strlen($fullName) < 2) {
            $errors[] = 'Full Name must be at least 2 characters long.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid campus email address.';
        }
        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if (!in_array($role, ['student', 'admin'], true)) {
            $errors[] = 'Invalid role selection.';
        }

        if (empty($errors)) {
            $pdo = get_db_connection();

            // Check if email already registered
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $checkStmt->execute(['email' => strtolower($email)]);
            if ($checkStmt->fetch()) {
                $errors[] = 'An account with this email address already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $pdo->prepare('
                    INSERT INTO users (full_name, email, password_hash, role, is_active)
                    VALUES (:full_name, :email, :password_hash, :role, true)
                    RETURNING id
                ');
                $insertStmt->execute([
                    'full_name'     => $fullName,
                    'email'         => strtolower($email),
                    'password_hash' => $hash,
                    'role'          => $role
                ]);
                $newUserId = (int)$insertStmt->fetchColumn();

                // Log the newly registered user in directly
                login_user([
                    'id'        => $newUserId,
                    'full_name' => $fullName,
                    'email'     => strtolower($email),
                    'role'      => $role
                ]);

                set_flash('success', 'Account created successfully! Welcome to Campus LostLink.');
                if ($role === 'admin') {
                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                } else {
                    header('Location: ' . BASE_URL . '/dashboard.php');
                }
                exit;
            }
        }
    }
}

$pageTitle = 'Create New Account — Campus LostLink';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page-wrapper">
  <div class="auth-card">
    <div class="auth-header">
      <h1 class="auth-title">Create New Account</h1>
      <p class="auth-subtitle">Join Campus LostLink to report and claim lost items</p>
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

    <form action="<?= BASE_URL ?>/register.php" method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="full_name" class="form-label">Full Name</label>
        <div class="input-with-icon">
          <i class="fa-regular fa-user input-icon-left"></i>
          <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Rahul Sharma" value="<?= e($fullName) ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-with-icon">
          <i class="fa-regular fa-envelope input-icon-left"></i>
          <input type="email" id="email" name="email" class="form-control" placeholder="e.g. student@lostlink.com" value="<?= e($email) ?>" required>
        </div>
      </div>
      

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <div class="input-with-icon">
          <i class="fa-solid fa-lock input-icon-left"></i>
          <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
          <button type="button" class="password-toggle-btn" data-target="password" aria-label="Toggle password visibility">
            <i class="fa-regular fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label for="role" class="form-label">Select Role</label>
        <div class="input-with-icon">
          <i class="fa-solid fa-id-badge input-icon-left"></i>
          <select id="role" name="role" class="form-control" required>
            <option value="student" <?= ($role === 'student') ? 'selected' : '' ?>>Student</option>
            <option value="admin" <?= ($role === 'admin') ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
      </div>

      <button type="submit" class="btn-auth-submit">
        <span>Register</span>
      </button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="<?= BASE_URL ?>/login.php">Login</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
