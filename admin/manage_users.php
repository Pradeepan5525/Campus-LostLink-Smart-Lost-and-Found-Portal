<?php
/**
 * Campus LostLink — Admin Manage Users (admin/manage_users.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$adminId = (int)user_id();
$pdo = get_db_connection();

// Handle Toggle Active/Inactive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId === $adminId) {
            set_flash('error', 'You cannot deactivate your own administrator account.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = :id');
            $stmt->execute(['id' => $targetUserId]);
            set_flash('success', 'User account status updated.');
        }
        header('Location: ' . BASE_URL . '/admin/manage_users.php');
        exit;
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId === $adminId) {
            set_flash('error', 'You cannot delete your own account.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $targetUserId]);
            set_flash('success', 'User deleted successfully.');
        }
        header('Location: ' . BASE_URL . '/admin/manage_users.php');
        exit;
    }
}

// Fetch all users with report counts
$stmt = $pdo->query('
    SELECT u.id, u.full_name, u.email, u.role, u.is_active, u.created_at,
           (SELECT COUNT(*) FROM items i WHERE i.user_id = u.id) AS reports_count,
           (SELECT COUNT(*) FROM claims c WHERE c.claimant_id = u.id) AS claims_count
    FROM users u
    ORDER BY u.created_at DESC
');
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users — Admin Portal';
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
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="portal-main">
    <header class="portal-topbar">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button type="button" class="mobile-toggle-btn" onclick="document.getElementById('portalSidebar').classList.toggle('open');">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">Manage Users</span>
      </div>

      <div class="topbar-nav">
        <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/logout.php" style="color: #DC2626;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
      </div>
    </header>

    <main class="portal-content">
      <?= render_flash() ?>

      <div class="page-header-row">
        <div>
          <h1 class="page-title">Manage Users</h1>
          <p style="font-size: 13px; color: var(--text-muted);">View all registered students and administrators, activate or deactivate accounts.</p>
        </div>
        <div style="font-weight: 600; font-size: 14px; color: var(--primary-navy);">
          Total Users: <?= count($users) ?>
        </div>
      </div>

      <div class="data-card">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email Address</th>
                <th>Role</th>
                <th>Status</th>
                <th>Reports</th>
                <th>Claims</th>
                <th>Registered</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td>#<?= (int)$u['id'] ?></td>
                  <td><strong><?= e($u['full_name']) ?></strong></td>
                  <td><?= e($u['email']) ?></td>
                  <td>
                    <span class="location-pill" style="<?= ($u['role'] === 'admin') ? 'background: #EFF6FF; color: #1D4ED8; font-weight: 700;' : '' ?>">
                      <?= ucfirst(e($u['role'])) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($u['is_active']): ?>
                      <span class="status-badge badge-available">Active</span>
                    <?php else: ?>
                      <span class="status-badge badge-rejected">Deactivated</span>
                    <?php endif; ?>
                  </td>
                  <td><?= (int)$u['reports_count'] ?></td>
                  <td><?= (int)$u['claims_count'] ?></td>
                  <td><?= format_date($u['created_at']) ?></td>
                  <td style="text-align: right; white-space: nowrap;">
                    <?php if ((int)$u['id'] !== $adminId): ?>
                      <form method="POST" style="display: inline-block; margin-right: 4px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                          <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                      </form>

                      <form method="POST" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn-danger" style="padding: 4px 8px; font-size: 11px;" data-confirm="Are you sure you want to delete this user and all associated data?">
                          <i class="fa-regular fa-trash-can"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">Current User</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
