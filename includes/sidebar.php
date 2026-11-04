<?php
/**
 * Campus LostLink — Portal Sidebar Component
 * Supports both Student and Admin roles with active highlight
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

$user = current_user();
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$isAdminSection = ($currentDir === 'admin');

$initial = strtoupper(substr($user['full_name'] ?? 'U', 0, 1));
?>

<aside class="portal-sidebar" id="portalSidebar">
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>/index.php" class="sidebar-brand">
      <img src="<?= BASE_URL ?>/assets/img/logo-pin.svg" alt="Campus LostLink Logo" style="width: 24px; height: 24px;">
      <span>Campus LostLink</span>
    </a>
  </div>

  <div class="sidebar-user-card">
    <div class="user-avatar-circle">
      <?= e($initial) ?>
    </div>
    <div class="user-info-text">
      <div class="user-welcome-label">Welcome,</div>
      <div class="user-display-name" title="<?= e($user['full_name'] ?? '') ?>"><?= e($user['full_name'] ?? 'User') ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php if ($isAdminSection || ($user['role'] ?? '') === 'admin'): ?>
      <!-- Admin Navigation Links -->
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link <?= ($currentFile === 'dashboard.php' && $isAdminSection) ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/manage_items.php" class="sidebar-link <?= ($currentFile === 'manage_items.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-boxes-stacked"></i>
        <span>Manage Items</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/manage_users.php" class="sidebar-link <?= ($currentFile === 'manage_users.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i>
        <span>Manage Users</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/claims.php" class="sidebar-link <?= ($currentFile === 'claims.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-clipboard-check"></i>
        <span>Claims</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/reports.php" class="sidebar-link <?= ($currentFile === 'reports.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-pie"></i>
        <span>Reports</span>
      </a>
    <?php else: ?>
      <!-- Student Navigation Links -->
      <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-link <?= ($currentFile === 'dashboard.php' && !$isAdminSection) ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= BASE_URL ?>/report_lost.php" class="sidebar-link <?= ($currentFile === 'report_lost.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span>Report Lost Item</span>
      </a>
      <a href="<?= BASE_URL ?>/report_found.php" class="sidebar-link <?= ($currentFile === 'report_found.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-circle-check"></i>
        <span>Report Found Item</span>
      </a>
      <a href="<?= BASE_URL ?>/my_reports.php" class="sidebar-link <?= ($currentFile === 'my_reports.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-list-check"></i>
        <span>My Reports</span>
      </a>
      <a href="<?= BASE_URL ?>/profile.php" class="sidebar-link <?= ($currentFile === 'profile.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
      </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link" style="color: #F87171;">
      <i class="fa-solid fa-arrow-right-from-bracket"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>
