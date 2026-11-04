<?php
/**
 * Campus LostLink — Student Dashboard (dashboard.php)
 * Matches Mockup Screen 4
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = user_id();

// If user is admin, redirect to admin dashboard
if (is_admin()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$pdo = get_db_connection();

// 1. Calculate Stat Cards
// My Lost Items count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE user_id = :uid AND type = \'lost\'');
$stmt->execute(['uid' => $userId]);
$myLostCount = (int)$stmt->fetchColumn();

// My Found Items count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE user_id = :uid AND type = \'found\'');
$stmt->execute(['uid' => $userId]);
$myFoundCount = (int)$stmt->fetchColumn();

// Total Reports
$totalReportsCount = $myLostCount + $myFoundCount;

// Pending Claims (either claims made by this user that are pending, or claims on user's found items pending verification)
$stmt = $pdo->prepare('
    SELECT COUNT(*) 
    FROM claims c 
    JOIN items i ON c.item_id = i.id 
    WHERE (c.claimant_id = :uid OR i.user_id = :uid) AND c.status = \'pending\'
');
$stmt->execute(['uid' => $userId]);
$pendingClaimsCount = (int)$stmt->fetchColumn();

// 2. Fetch Recent Reports submitted by this user (or latest across the system if new user)
$stmt = $pdo->prepare('
    SELECT i.id, i.item_name, i.type, i.event_date, i.status,
           l.name AS location_name, c.name AS category_name
    FROM items i
    JOIN locations l ON i.location_id = l.id
    JOIN categories c ON i.category_id = c.id
    WHERE i.user_id = :uid
    ORDER BY i.created_at DESC, i.id DESC
    LIMIT 5
');
$stmt->execute(['uid' => $userId]);
$recentReports = $stmt->fetchAll();

// If the student hasn't reported anything yet, show recent reports on campus for easy discovery
$isPersonalReports = !empty($recentReports);
if (!$isPersonalReports) {
    $stmt = $pdo->query('
        SELECT i.id, i.item_name, i.type, i.event_date, i.status,
               l.name AS location_name, c.name AS category_name
        FROM items i
        JOIN locations l ON i.location_id = l.id
        JOIN categories c ON i.category_id = c.id
        ORDER BY i.created_at DESC, i.id DESC
        LIMIT 5
    ');
    $recentReports = $stmt->fetchAll();
}

$pageTitle = 'Student Dashboard — Campus LostLink';
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
  <!-- Navy Sidebar (Mockup Left) -->
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <!-- Main Content Workspace -->
  <div class="portal-main">
    <!-- Topbar (Mockup Top) -->
    <header class="portal-topbar">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button type="button" class="mobile-toggle-btn" onclick="document.getElementById('portalSidebar').classList.toggle('open');" aria-label="Toggle Sidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">Dashboard</span>
      </div>

      <div class="topbar-nav">
        <a href="<?= BASE_URL ?>/dashboard.php" style="color: var(--primary-navy); font-weight: 600;">Dashboard</a>
        <a href="<?= BASE_URL ?>/report_lost.php">Report Item</a>
        <a href="<?= BASE_URL ?>/my_reports.php">My Reports</a>
        <a href="<?= BASE_URL ?>/logout.php" style="color: #DC2626;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
      </div>
    </header>

    <main class="portal-content">
      <?= render_flash() ?>

      <!-- Page Header -->
      <div class="page-header-row">
        <h1 class="page-title">Dashboard</h1>
        <div style="display: flex; gap: 10px;">
          <a href="<?= BASE_URL ?>/report_lost.php" class="btn-primary" style="background: #DC2626;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Report Lost Item</span>
          </a>
          <a href="<?= BASE_URL ?>/report_found.php" class="btn-primary" style="background: #059669;">
            <i class="fa-solid fa-circle-check"></i>
            <span>Report Found Item</span>
          </a>
        </div>
      </div>

      <!-- 4 Stat Cards (Mockup Screen 4) -->
      <div class="stats-grid">
        <div class="stat-card">
          <i class="fa-regular fa-face-frown stat-card-icon icon-lost"></i>
          <div class="stat-value"><?= $myLostCount ?></div>
          <div class="stat-label">My Lost Items</div>
        </div>

        <div class="stat-card">
          <i class="fa-regular fa-circle-check stat-card-icon icon-found"></i>
          <div class="stat-value"><?= $myFoundCount ?></div>
          <div class="stat-label">My Found Items</div>
        </div>

        <div class="stat-card">
          <i class="fa-regular fa-file-lines stat-card-icon icon-reports"></i>
          <div class="stat-value"><?= $totalReportsCount ?></div>
          <div class="stat-label">Total Reports</div>
        </div>

        <div class="stat-card">
          <i class="fa-solid fa-hourglass-half stat-card-icon icon-claims"></i>
          <div class="stat-value"><?= $pendingClaimsCount ?></div>
          <div class="stat-label">Pending Claims</div>
        </div>
      </div>

      <!-- Recent Reports Table (Mockup Screen 4) -->
      <div class="data-card">
        <div class="data-card-header">
          <h2 class="data-card-title"><?= $isPersonalReports ? 'My Recent Reports' : 'Recent Campus Reports' ?></h2>
          <a href="<?= BASE_URL ?>/my_reports.php" class="section-view-all">View All &rarr;</a>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Type</th>
                <th>Location</th>
                <th>Date</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentReports)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 36px;">
                    No reports found. Report a lost or found item using the buttons above!
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentReports as $row): ?>
                  <tr>
                    <td>
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$row['id'] ?>" style="font-weight: 600; color: var(--primary-navy);">
                        <?= e($row['item_name']) ?>
                      </a>
                    </td>
                    <td><?= type_badge($row['type']) ?></td>
                    <td>
                      <i class="fa-solid fa-location-dot" style="font-size: 11px; color: var(--text-light); margin-right: 4px;"></i>
                      <?= e($row['location_name']) ?>
                    </td>
                    <td>
                      <i class="fa-regular fa-calendar" style="font-size: 11px; color: var(--text-light); margin-right: 4px;"></i>
                      <?= format_date($row['event_date']) ?>
                    </td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td style="text-align: right;">
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$row['id'] ?>" class="btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                        View
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
