<?php
/**
 * Campus LostLink — Items Listing (items.php)
 * Matches Mockup Screen 7 (Lost Items / Found Items with Filters & Search)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();

$categories = get_categories($pdo);
$locations  = get_locations($pdo);

// Query Filters
$type       = strtolower(trim($_GET['type'] ?? ''));
$search     = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$locationId = (int)($_GET['location'] ?? 0);
$dateFilter = trim($_GET['date'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 8;
$offset     = ($page - 1) * $limit;

// Base query construction
$where = [];
$params = [];

if (in_array($type, ['lost', 'found'], true)) {
    $where[] = 'i.type = :type';
    $params['type'] = $type;
}

if (!empty($search)) {
    $where[] = '(i.item_name ILIKE :search OR i.description ILIKE :search)';
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

if (!empty($dateFilter)) {
    $where[] = 'i.event_date = :event_date';
    $params['event_date'] = $dateFilter;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total matching items
$countSql = "SELECT COUNT(*) FROM items i $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $limit));

// Fetch items for current page
$sql = "
    SELECT i.id, i.item_name, i.type, i.event_date, i.image_path, i.status,
           c.name AS category_name, l.name AS location_name
    FROM items i
    JOIN categories c ON i.category_id = c.id
    JOIN locations l ON i.location_id = l.id
    $whereSql
    ORDER BY i.event_date DESC, i.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

// Dynamic title
$pageHeading = match($type) {
    'lost'  => 'Lost Items',
    'found' => 'Found Items',
    default => 'All Items'
};

$pageTitle = $pageHeading . ' — Campus LostLink';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1200px; margin: 32px auto; padding: 0 24px; width: 100%;">
  <div class="page-header-row">
    <div>
      <h1 class="page-title"><?= e($pageHeading) ?></h1>
      <p style="font-size: 14px; color: var(--text-muted); margin-top: 4px;">
        Browse reported items, search by keywords, or filter by category and location.
      </p>
    </div>

    <!-- Type quick switch pill tabs -->
    <div style="display: flex; gap: 8px; background: #E2E8F0; padding: 4px; border-radius: var(--radius-sm);">
      <a href="<?= BASE_URL ?>/items.php" class="page-btn <?= empty($type) ? 'active' : '' ?>" style="height: 32px; font-size: 12px; border: none;">All</a>
      <a href="<?= BASE_URL ?>/items.php?type=lost" class="page-btn <?= ($type === 'lost') ? 'active' : '' ?>" style="height: 32px; font-size: 12px; border: none;">Lost Items</a>
      <a href="<?= BASE_URL ?>/items.php?type=found" class="page-btn <?= ($type === 'found') ? 'active' : '' ?>" style="height: 32px; font-size: 12px; border: none;">Found Items</a>
    </div>
  </div>

  <!-- Search & Filter Card (Mockup Screen 7) -->
  <div class="search-filter-card">
    <form action="<?= BASE_URL ?>/items.php" method="GET" id="filter-items-form">
      <?php if (!empty($type)): ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
      <?php endif; ?>

      <!-- Search Row -->
      <div class="search-input-row">
        <div class="input-with-icon" style="flex: 1;">
          <i class="fa-solid fa-magnifying-glass input-icon-left"></i>
          <input type="text" name="q" id="search-item-input" class="form-control" placeholder="Search by item name..." value="<?= e($search) ?>">
        </div>
        <button type="submit" class="btn-primary" style="padding: 0 24px;">
          <span>Search</span>
        </button>
      </div>

      <!-- Filters Row -->
      <div class="filter-controls-row">
        <!-- Category Filter -->
        <select name="category" class="filter-select" onchange="this.form.submit()">
          <option value="0">Category: All</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= ($categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
              Category: <?= e($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Location Filter -->
        <select name="location" class="filter-select" onchange="this.form.submit()">
          <option value="0">Location: All</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= (int)$loc['id'] ?>" <?= ($locationId === (int)$loc['id']) ? 'selected' : '' ?>>
              Location: <?= e($loc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Date Filter -->
        <input type="date" name="date" class="filter-select" value="<?= e($dateFilter) ?>" onchange="this.form.submit()" title="Filter by date">

        <?php if (!empty($search) || $categoryId > 0 || $locationId > 0 || !empty($dateFilter)): ?>
          <a href="<?= BASE_URL ?>/items.php<?= !empty($type) ? '?type=' . urlencode($type) : '' ?>" class="btn-secondary" style="height: 38px; display: inline-flex; align-items: center; justify-content: center;">
            Reset Filters
          </a>
        <?php else: ?>
          <div></div>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Items Grid (Mockup Screen 7) -->
  <div class="recent-items-grid" style="grid-template-columns: repeat(4, 1fr);">
    <?php if (empty($items)): ?>
      <div style="grid-column: 1 / -1; background: #fff; padding: 60px 20px; text-align: center; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
        <i class="fa-regular fa-folder-open" style="font-size: 38px; color: var(--text-light); margin-bottom: 14px;"></i>
        <h3 style="font-size: 16px; color: var(--text-main); margin-bottom: 6px;">No items match your search</h3>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">Try adjusting your search keywords or clearing filters.</p>
        <a href="<?= BASE_URL ?>/items.php<?= !empty($type) ? '?type=' . urlencode($type) : '' ?>" class="btn-secondary">Clear Filters</a>
      </div>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <?php
          $imageSrc = (!empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path']))
              ? BASE_URL . '/' . e($item['image_path'])
              : BASE_URL . '/assets/img/items/' . match(strtolower($item['category_name'])) {
                  'wallet'       => 'wallet.svg',
                  'mobile phone' => 'mobile.svg',
                  'keys'         => 'keys.svg',
                  'laptop'       => 'laptop.svg',
                  'books'        => 'books.svg',
                  'bottle'       => 'bottle.svg',
                  'bag'          => 'bag.svg',
                  'id card'      => 'idcard.svg',
                  default        => 'bag.svg'
              };
        ?>
        <div class="item-card">
          <div class="item-card-icon-wrap">
            <img src="<?= $imageSrc ?>" alt="<?= e($item['item_name']) ?>">
          </div>
          <div class="item-card-name"><?= e($item['item_name']) ?></div>
          <div class="item-card-meta">
            <div class="item-card-meta-line" style="font-size: 13px;">
              <span style="font-weight: 600; color: <?= ($item['type'] === 'lost') ? '#DC2626' : '#059669' ?>;">
                <?= ucfirst(e($item['type'])) ?>
              </span>
              <span style="color: var(--text-light);">&bull;</span>
              <span><?= e($item['location_name']) ?></span>
            </div>
            <div class="item-card-meta-line">
              <i class="fa-regular fa-calendar" style="font-size: 11px; color: var(--text-light);"></i>
              <span><?= format_date($item['event_date']) ?></span>
            </div>
          </div>
          <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$item['id'] ?>" class="item-card-btn">
            View Details
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">&laquo; Prev</a>
      <?php endif; ?>

      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-btn <?= ($p === $page) ? 'active' : '' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">Next &raquo;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
