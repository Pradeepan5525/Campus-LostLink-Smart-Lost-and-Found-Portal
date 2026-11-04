<?php
/**
 * Campus LostLink — Landing Page (index.php)
 * Matches Mockup Screen 1
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();

// Fetch 4 most recent items from database for the recent items grid
$stmt = $pdo->query('
    SELECT i.id, i.item_name, i.type, i.event_date, i.image_path, i.status,
           c.name AS category_name, l.name AS location_name
    FROM items i
    JOIN categories c ON i.category_id = c.id
    JOIN locations l ON i.location_id = l.id
    ORDER BY i.created_at DESC, i.id DESC
    LIMIT 4
');
$recentItems = $stmt->fetchAll();

$pageTitle = 'Home — Smart Lost and Found Portal';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
  <div class="hero-grid">
    <div class="hero-content">
      <h1 class="hero-headline">Lost Something?<br>Found Something?</h1>
      <p class="hero-description">
        Campus LostLink helps you report, find and reunite lost items on campus.
      </p>
      <a href="<?= is_logged_in() ? BASE_URL . '/report_lost.php' : BASE_URL . '/login.php' ?>" class="hero-cta-btn">
        <i class="fa-solid fa-plus-circle"></i>
        <span>Report Item</span>
      </a>
    </div>

    <div class="hero-illustration">
      <img src="<?= BASE_URL ?>/assets/img/hero-backpack.svg" alt="Campus LostLink Backpack Illustration">
    </div>
  </div>
</section>

<!-- Recent Items Section (Mockup Top-Left) -->
<section class="recent-items-section">
  <div class="section-header-row">
    <h2 class="section-title">Recent Items</h2>
    <a href="<?= BASE_URL ?>/items.php" class="section-view-all">View All &rarr;</a>
  </div>

  <div class="recent-items-grid">
    <?php if (empty($recentItems)): ?>
      <div style="grid-column: 1 / -1; background: #fff; padding: 40px; text-align: center; border-radius: var(--radius-md); border: 1px solid var(--border-color); color: var(--text-muted);">
        No items reported recently. Be the first to report a lost or found item!
      </div>
    <?php else: ?>
      <?php foreach ($recentItems as $item): ?>
        <?php
          // Determine icon/image fallback
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
        <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$item['id'] ?>" class="item-card" style="text-decoration: none; color: inherit;">
          <div class="item-card-icon-wrap">
            <img src="<?= $imageSrc ?>" alt="<?= e($item['item_name']) ?>">
          </div>
          <div class="item-card-name"><?= e($item['item_name']) ?></div>
          <div class="item-card-meta">
            <div class="item-card-meta-line" style="font-weight: 600; color: <?= ($item['type'] === 'lost') ? '#DC2626' : '#059669' ?>;">
              <span><?= ucfirst(e($item['type'])) ?></span>
            </div>
            <div class="item-card-meta-line">
              <i class="fa-solid fa-location-dot" style="font-size: 11px; color: var(--text-light);"></i>
              <span><?= e($item['location_name']) ?></span>
            </div>
            <div class="item-card-meta-line">
              <i class="fa-regular fa-calendar" style="font-size: 11px; color: var(--text-light);"></i>
              <span><?= format_date($item['event_date']) ?></span>
            </div>
          </div>
          <div class="item-card-btn">View Details</div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
