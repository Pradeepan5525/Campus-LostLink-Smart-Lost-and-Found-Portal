<?php
/**
 * Campus LostLink — Admin Manage Items (admin/manage_items.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = get_db_connection();

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowedStatuses = ['pending', 'available', 'claimed', 'returned', 'closed'];

        if ($itemId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            $stmt = $pdo->prepare('UPDATE items SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $newStatus, 'id' => $itemId]);
            set_flash('success', 'Item status updated to ' . ucfirst($newStatus) . '.');
        } else {
            set_flash('error', 'Invalid status update.');
        }
        header('Location: ' . BASE_URL . '/admin/manage_items.php?' . http_build_query($_GET));
        exit;
    }
}

// Handle Delete Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $delStmt = $pdo->prepare('DELETE FROM items WHERE id = :id');
        $delStmt->execute(['id' => $itemId]);
        set_flash('success', 'Item deleted successfully.');
        header('Location: ' . BASE_URL . '/admin/manage_items.php?' . http_build_query($_GET));
        exit;
    }
}

// Filters & Pagination
$categories = get_categories($pdo);
$locations  = get_locations($pdo);

$type       = strtolower(trim($_GET['type'] ?? ''));
$status     = strtolower(trim($_GET['status'] ?? ''));
$search     = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$locationId = (int)($_GET['location'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 10;
$offset     = ($page - 1) * $limit;

$where = [];
$params = [];

if (in_array($type, ['lost', 'found'], true)) {
    $where[] = 'i.type = :type';
    $params['type'] = $type;
}
if (!empty($status)) {
    $where[] = 'i.status = :status';
    $params['status'] = $status;
}
if (!empty($search)) {
    $where[] = '(i.item_name ILIKE :search OR i.description ILIKE :search OR u.full_name ILIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($categoryId > 0) {
    $where[] = 'i.category_id = :category_id';
    $params['category_id'] = $categoryId;
}
if ($locationId > 0) {
    $where[] = 'i.location_id = :location_id';
    $params['location_id'] = $locationId;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM items i JOIN users u ON i.user_id = u.id $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $limit));

$sql = "
    SELECT i.*, c.name AS category_name, l.name AS location_name, u.full_name AS reporter_name,
           (SELECT COUNT(*) FROM claims cl WHERE cl.item_id = i.id) AS claims_count
    FROM items i
    JOIN categories c ON i.category_id = c.id
    JOIN locations l ON i.location_id = l.id
    JOIN users u ON i.user_id = u.id
    $whereSql
    ORDER BY i.created_at DESC, i.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$pageTitle = 'Manage Items — Admin Portal';
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
        <span class="topbar-title">Manage Items</span>
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
          <h1 class="page-title">Manage All Items</h1>
          <p style="font-size: 13px; color: var(--text-muted);">Oversee all lost and found reports, edit statuses, and moderate content.</p>
        </div>
        <div style="font-weight: 600; font-size: 14px; color: var(--primary-navy);">
          Total: <?= $totalItems ?> items
        </div>
      </div>

      <!-- Filters -->
      <div class="search-filter-card">
        <form method="GET" action="<?= BASE_URL ?>/admin/manage_items.php">
          <div class="search-input-row">
            <div class="input-with-icon" style="flex: 1;">
              <i class="fa-solid fa-magnifying-glass input-icon-left"></i>
              <input type="text" name="q" class="form-control" placeholder="Search by item name, description, or reporter..." value="<?= e($search) ?>">
            </div>
            <button type="submit" class="btn-primary" style="padding: 0 24px;">Filter</button>
          </div>

          <div class="filter-controls-row">
            <select name="type" class="filter-select" onchange="this.form.submit()">
              <option value="">Type: All</option>
              <option value="lost" <?= ($type === 'lost') ? 'selected' : '' ?>>Type: Lost</option>
              <option value="found" <?= ($type === 'found') ? 'selected' : '' ?>>Type: Found</option>
            </select>

            <select name="status" class="filter-select" onchange="this.form.submit()">
              <option value="">Status: All</option>
              <option value="available" <?= ($status === 'available') ? 'selected' : '' ?>>Available</option>
              <option value="pending" <?= ($status === 'pending') ? 'selected' : '' ?>>Pending</option>
              <option value="claimed" <?= ($status === 'claimed') ? 'selected' : '' ?>>Claimed</option>
              <option value="returned" <?= ($status === 'returned') ? 'selected' : '' ?>>Returned</option>
              <option value="closed" <?= ($status === 'closed') ? 'selected' : '' ?>>Closed</option>
            </select>

            <select name="category" class="filter-select" onchange="this.form.submit()">
              <option value="0">Category: All</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ($categoryId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <a href="<?= BASE_URL ?>/admin/manage_items.php" class="btn-secondary" style="height: 38px; display: inline-flex; align-items: center; justify-content: center;">
              Reset
            </a>
          </div>
        </form>
      </div>

      <!-- Items Table -->
      <div class="data-card">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Item</th>
                <th>Type</th>
                <th>Category</th>
                <th>Location</th>
                <th>Reporter</th>
                <th>Date</th>
                <th>Status</th>
                <th>Change Status</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr>
                  <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">
                    No items found matching the selected filters.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($items as $row): ?>
                  <tr>
                    <td>#<?= (int)$row['id'] ?></td>
                    <td>
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$row['id'] ?>" style="font-weight: 600; color: var(--primary-navy);">
                        <?= e($row['item_name']) ?>
                      </a>
                    </td>
                    <td><?= type_badge($row['type']) ?></td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= e($row['location_name']) ?></td>
                    <td><?= e($row['reporter_name']) ?></td>
                    <td><?= format_date($row['event_date']) ?></td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td>
                      <form method="POST" style="display: flex; gap: 4px; align-items: center;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="item_id" value="<?= (int)$row['id'] ?>">
                        <select name="status" class="filter-select" style="height: 30px; font-size: 11px; padding: 2px 6px;" onchange="this.form.submit()">
                          <option value="available" <?= ($row['status'] === 'available') ? 'selected' : '' ?>>Available</option>
                          <option value="pending" <?= ($row['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                          <option value="claimed" <?= ($row['status'] === 'claimed') ? 'selected' : '' ?>>Claimed</option>
                          <option value="returned" <?= ($row['status'] === 'returned') ? 'selected' : '' ?>>Returned</option>
                          <option value="closed" <?= ($row['status'] === 'closed') ? 'selected' : '' ?>>Closed</option>
                        </select>
                      </form>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$row['id'] ?>" class="btn-secondary" style="padding: 4px 8px; font-size: 11px; margin-right: 4px;" title="View Details">
                        <i class="fa-solid fa-eye"></i>
                      </a>
                      <form method="POST" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn-danger" style="padding: 4px 8px; font-size: 11px;" data-confirm="Are you sure you want to permanently delete this item report?" title="Delete Item">
                          <i class="fa-regular fa-trash-can"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination-wrap">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-btn <?= ($p === $page) ? 'active' : '' ?>">
              <?= $p ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
