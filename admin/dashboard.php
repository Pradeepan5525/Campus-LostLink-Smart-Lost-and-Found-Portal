<?php
/**
 * Campus LostLink — Admin Dashboard (admin/dashboard.php)
 * Matches Mockup Screen 9
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$adminUser = current_user();
$pdo = get_db_connection();

// 1. Calculate Stat Cards
// Total Items
$totalItemsCount = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

// Lost Items
$lostItemsCount = (int)$pdo->query('SELECT COUNT(*) FROM items WHERE type = \'lost\'')->fetchColumn();

// Found Items
$foundItemsCount = (int)$pdo->query('SELECT COUNT(*) FROM items WHERE type = \'found\'')->fetchColumn();

// Pending Claims
$pendingClaimsCount = (int)$pdo->query('SELECT COUNT(*) FROM claims WHERE status = \'pending\'')->fetchColumn();

// 2. Fetch Recent Claims (Mockup Screen 9 Table)
$stmt = $pdo->query('
    SELECT c.id, c.proof_description, c.status, c.created_at,
           i.id AS item_id, i.item_name, i.type AS item_type,
           u.full_name AS claimant_name, u.email AS claimant_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimant_id = u.id
    ORDER BY c.created_at DESC
    LIMIT 6
');
$recentClaims = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard — Campus LostLink';
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
  <!-- Admin Dark Navy Sidebar (Mockup Left) -->
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

  <!-- Main Content Workspace -->
  <div class="portal-main">
    <!-- Topbar (Mockup Top) -->
    <header class="portal-topbar">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button type="button" class="mobile-toggle-btn" onclick="document.getElementById('portalSidebar').classList.toggle('open');">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">Admin Dashboard</span>
      </div>

      <div class="topbar-nav">
        <span style="font-size: 13px; font-weight: 600; color: var(--primary-navy);">
          <i class="fa-solid fa-shield-halved" style="color: var(--accent-blue);"></i> Admin: <?= e($adminUser['full_name']) ?>
        </span>
        <a href="<?= BASE_URL ?>/logout.php" style="color: #DC2626;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
      </div>
    </header>

    <main class="portal-content">
      <?= render_flash() ?>

      <div class="page-header-row">
        <h1 class="page-title">Admin Dashboard</h1>
        <div style="display: flex; gap: 10px;">
          <a href="<?= BASE_URL ?>/admin/claims.php" class="btn-primary" style="background: #D97706;">
            <i class="fa-solid fa-clipboard-check"></i> Verify Claims
          </a>
          <a href="<?= BASE_URL ?>/admin/manage_items.php" class="btn-primary">
            <i class="fa-solid fa-boxes-stacked"></i> All Items
          </a>
        </div>
      </div>

      <!-- 4 Stat Cards (Mockup Screen 9) -->
      <div class="stats-grid">
        <div class="stat-card">
          <i class="fa-solid fa-box-archive stat-card-icon icon-reports"></i>
          <div class="stat-value"><?= $totalItemsCount ?></div>
          <div class="stat-label">Total Items</div>
        </div>

        <div class="stat-card">
          <i class="fa-regular fa-face-frown stat-card-icon icon-lost"></i>
          <div class="stat-value"><?= $lostItemsCount ?></div>
          <div class="stat-label">Lost Items</div>
        </div>

        <div class="stat-card">
          <i class="fa-regular fa-circle-check stat-card-icon icon-found"></i>
          <div class="stat-value"><?= $foundItemsCount ?></div>
          <div class="stat-label">Found Items</div>
        </div>

        <div class="stat-card">
          <i class="fa-solid fa-hourglass-half stat-card-icon icon-claims"></i>
          <div class="stat-value"><?= $pendingClaimsCount ?></div>
          <div class="stat-label">Pending Claims</div>
        </div>
      </div>

      <!-- Recent Claims Table (Mockup Screen 9) -->
      <div class="data-card">
        <div class="data-card-header">
          <h2 class="data-card-title">Recent Claims</h2>
          <a href="<?= BASE_URL ?>/admin/claims.php" class="section-view-all">View All &rarr;</a>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>User</th>
                <th>Status</th>
                <th>Date</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentClaims)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 36px;">
                    No claims filed yet.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentClaims as $claim): ?>
                  <tr>
                    <td>
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$claim['item_id'] ?>" style="font-weight: 600; color: var(--primary-navy);">
                        <?= e($claim['item_name']) ?>
                      </a>
                      <span style="margin-left: 6px;"><?= type_badge($claim['item_type']) ?></span>
                    </td>
                    <td>
                      <div><strong><?= e($claim['claimant_name']) ?></strong></div>
                      <div style="font-size: 11px; color: var(--text-muted);"><?= e($claim['claimant_email']) ?></div>
                    </td>
                    <td><?= status_badge($claim['status']) ?></td>
                    <td><?= format_date($claim['created_at']) ?></td>
                    <td style="text-align: right;">
                      <a href="<?= BASE_URL ?>/admin/claims.php" class="btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                        Manage
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
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
