<?php
/**
 * Campus LostLink — Public & Shared Site Header
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' — ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="Campus LostLink helps students and staff report, find, and reunite lost and found items quickly and transparently across campus.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<header class="site-header">
  <div class="navbar-container">
    <a href="<?= BASE_URL ?>/index.php" class="brand-link">
      <img src="<?= BASE_URL ?>/assets/img/logo-pin.svg" alt="Campus LostLink Logo" class="brand-logo-img">
      <span>Campus LostLink</span>
    </a>

    <button type="button" class="mobile-toggle-btn" aria-label="Toggle navigation">
      <i class="fa-solid fa-bars"></i>
    </button>

    <ul class="nav-links">
      <li><a href="<?= BASE_URL ?>/index.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>">Home</a></li>
      <li><a href="<?= BASE_URL ?>/items.php?type=lost" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'items.php' && ($_GET['type'] ?? '') === 'lost') ? 'active' : '' ?>">Lost Items</a></li>
      <li><a href="<?= BASE_URL ?>/items.php?type=found" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'items.php' && ($_GET['type'] ?? '') === 'found') ? 'active' : '' ?>">Found Items</a></li>
      
      <?php if ($user): ?>
        <li><a href="<?= ($user['role'] === 'admin') ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/dashboard.php' ?>" class="nav-link-btn"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <?php else: ?>
        <li><a href="<?= BASE_URL ?>/login.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'login.php') ? 'active' : '' ?>">Login</a></li>
        <li><a href="<?= BASE_URL ?>/register.php" class="nav-link-btn">Register</a></li>
      <?php endif; ?>
    </ul>
  </div>
</header>

<?= render_flash() ?>
