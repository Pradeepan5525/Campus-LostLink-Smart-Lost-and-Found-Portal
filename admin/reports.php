<?php
/**
 * Campus LostLink — Admin Analytics & Summary Reports (admin/reports.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = get_db_connection();

// 1. Items per Category
$catStmt = $pdo->query('
    SELECT c.name, COUNT(i.id) AS item_count
    FROM categories c
    LEFT JOIN items i ON c.id = i.category_id
    GROUP BY c.id, c.name
    ORDER BY item_count DESC
');
$categoryData = $catStmt->fetchAll();

// 2. Status Distribution (Returned vs Available vs Pending vs Claimed)
$statusStmt = $pdo->query('
    SELECT status, COUNT(*) AS count
    FROM items
    GROUP BY status
');
$statusDataRaw = $statusStmt->fetchAll();
$statusCounts = [
    'available' => 0,
    'pending'   => 0,
    'claimed'   => 0,
    'returned'  => 0,
    'closed'    => 0,
];
$totalReported = 0;
foreach ($statusDataRaw as $row) {
    $statusCounts[$row['status']] = (int)$row['count'];
    $totalReported += (int)$row['count'];
}
$returnedCount = $statusCounts['returned'] + $statusCounts['closed'];
$unclaimedCount = $statusCounts['available'] + $statusCounts['pending'] + $statusCounts['claimed'];
$returnRate = ($totalReported > 0) ? round(($returnedCount / $totalReported) * 100, 1) : 0;

// 3. Items by Location
$locStmt = $pdo->query('
    SELECT l.name, COUNT(i.id) AS count
    FROM locations l
    LEFT JOIN items i ON l.id = i.location_id
    GROUP BY l.id, l.name
    ORDER BY count DESC
');
$locationData = $locStmt->fetchAll();

// 4. Claims Status Breakdown
$claimsStatsStmt = $pdo->query('
    SELECT status, COUNT(*) AS count
    FROM claims
    GROUP BY status
');
$claimsStats = $claimsStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalClaims = array_sum($claimsStats);

$pageTitle = 'Institutional Analytics & Reports — Admin Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
        <span class="topbar-title">Institutional Reports &amp; Analytics</span>
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
          <h1 class="page-title">Summary Reports &amp; Insights</h1>
          <p style="font-size: 13px; color: var(--text-muted);">Campus LostLink performance analytics, reunification rates, and item distribution.</p>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Reunification Rate</div>
          <div class="stat-value" style="color: #059669;"><?= $returnRate ?>%</div>
          <div style="font-size: 12px; color: var(--text-muted);"><?= $returnedCount ?> of <?= $totalReported ?> items returned</div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Active / Unclaimed</div>
          <div class="stat-value" style="color: #3B82F6;"><?= $unclaimedCount ?></div>
          <div style="font-size: 12px; color: var(--text-muted);">Items looking for owners</div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Total Claims Submitted</div>
          <div class="stat-value" style="color: #D97706;"><?= $totalClaims ?></div>
          <div style="font-size: 12px; color: var(--text-muted);"><?= $claimsStats['approved'] ?? 0 ?> approved claims</div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Pending Claim Queue</div>
          <div class="stat-value" style="color: #DC2626;"><?= $claimsStats['pending'] ?? 0 ?></div>
          <div style="font-size: 12px; color: var(--text-muted);">Awaiting verification</div>
        </div>
      </div>

      <!-- Charts Row -->
      <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px; margin-bottom: 28px;">
        <!-- Items per Category Chart -->
        <div class="data-card" style="margin-bottom: 0;">
          <div class="data-card-header">
            <h2 class="data-card-title"><i class="fa-solid fa-chart-column" style="color: var(--accent-blue); margin-right: 8px;"></i> Items by Category</h2>
          </div>
          <div style="padding: 24px;">
            <canvas id="categoryChart" height="240"></canvas>
          </div>
        </div>

        <!-- Returned vs Unclaimed Donut Chart -->
        <div class="data-card" style="margin-bottom: 0;">
          <div class="data-card-header">
            <h2 class="data-card-title"><i class="fa-solid fa-chart-pie" style="color: #10B981; margin-right: 8px;"></i> Status Distribution</h2>
          </div>
          <div style="padding: 24px; display: flex; flex-direction: column; align-items: center;">
            <div style="max-width: 240px; width: 100%;">
              <canvas id="statusChart"></canvas>
            </div>
            <div style="display: flex; gap: 16px; margin-top: 18px; font-size: 13px;">
              <div><span style="display: inline-block; width: 10px; height: 10px; background: #10B981; border-radius: 2px;"></span> Returned: <strong><?= $returnedCount ?></strong></div>
              <div><span style="display: inline-block; width: 10px; height: 10px; background: #3B82F6; border-radius: 2px;"></span> Available: <strong><?= $statusCounts['available'] ?></strong></div>
              <div><span style="display: inline-block; width: 10px; height: 10px; background: #F59E0B; border-radius: 2px;"></span> Pending: <strong><?= $statusCounts['pending'] ?></strong></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Location Hotspots Progress Bars -->
      <div class="data-card">
        <div class="data-card-header">
          <h2 class="data-card-title"><i class="fa-solid fa-map-pin" style="color: #EF4444; margin-right: 8px;"></i> Campus Item Hotspots (By Location)</h2>
        </div>
        <div style="padding: 24px;">
          <?php foreach ($locationData as $loc): ?>
            <?php $pct = ($totalReported > 0) ? round(((int)$loc['count'] / $totalReported) * 100) : 0; ?>
            <div style="margin-bottom: 16px;">
              <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                <span><?= e($loc['name']) ?></span>
                <span style="color: var(--text-muted);"><?= (int)$loc['count'] ?> items (<?= $pct ?>%)</span>
              </div>
              <div style="width: 100%; height: 8px; background: #E2E8F0; border-radius: var(--radius-full); overflow: hidden;">
                <div style="width: <?= $pct ?>%; height: 100%; background: var(--accent-blue); border-radius: var(--radius-full);"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Category Bar Chart
  const categoryLabels = <?= json_encode(array_column($categoryData, 'name')) ?>;
  const categoryCounts = <?= json_encode(array_map('intval', array_column($categoryData, 'item_count'))) ?>;

  const catCtx = document.getElementById('categoryChart').getContext('2d');
  new Chart(catCtx, {
    type: 'bar',
    data: {
      labels: categoryLabels,
      datasets: [{
        label: 'Items Reported',
        data: categoryCounts,
        backgroundColor: '#1D4ED8',
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1 }
        }
      }
    }
  });

  // Status Donut Chart
  const statusCtx = document.getElementById('statusChart').getContext('2d');
  new Chart(statusCtx, {
    type: 'doughnut',
    data: {
      labels: ['Returned', 'Available', 'Pending Claim', 'Claimed'],
      datasets: [{
        data: [
          <?= $returnedCount ?>,
          <?= $statusCounts['available'] ?>,
          <?= $statusCounts['pending'] ?>,
          <?= $statusCounts['claimed'] ?>
        ],
        backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#8B5CF6']
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false }
      },
      cutout: '65%'
    }
  });
});
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
