<?php
/**
 * Campus LostLink — Report Found Item (report_found.php)
 * Matches Mockup Screen 6
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$userId = user_id();
$pdo = get_db_connection();

$categories = get_categories($pdo);
$locations  = get_locations($pdo);

$errors = [];
$itemName    = '';
$categoryId  = 0;
$locationId  = 0;
$eventDate   = date('Y-m-d');
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid or expired CSRF session token. Please try again.';
    } else {
        $itemName    = trim($_POST['item_name'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $locationId  = (int)($_POST['location_id'] ?? 0);
        $eventDate   = trim($_POST['event_date'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($itemName)) {
            $errors[] = 'Item Name is required.';
        }
        if ($categoryId <= 0) {
            $errors[] = 'Please select a Category.';
        }
        if ($locationId <= 0) {
            $errors[] = 'Please select the Location where the item was found.';
        }
        if (empty($eventDate)) {
            $errors[] = 'Date Found is required.';
        }
        if (empty($description)) {
            $errors[] = 'Please provide a detailed Description of the found item.';
        }

        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            try {
                $imagePath = handle_image_upload($_FILES['image']);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('
                INSERT INTO items (user_id, type, item_name, category_id, location_id, event_date, description, image_path, status)
                VALUES (:user_id, \'found\', :item_name, :category_id, :location_id, :event_date, :description, :image_path, \'available\')
                RETURNING id
            ');
            $stmt->execute([
                'user_id'     => $userId,
                'item_name'   => $itemName,
                'category_id' => $categoryId,
                'location_id' => $locationId,
                'event_date'  => $eventDate,
                'description' => $description,
                'image_path'  => $imagePath,
            ]);
            $newItemId = (int)$stmt->fetchColumn();

            set_flash('success', 'Found item reported successfully! Check below for potential owners who reported losing this item.');
            header('Location: ' . BASE_URL . '/item_details.php?id=' . $newItemId);
            exit;
        }
    }
}

$pageTitle = 'Report Found Item — Campus LostLink';
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
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <div class="portal-main">
    <header class="portal-topbar">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button type="button" class="mobile-toggle-btn" onclick="document.getElementById('portalSidebar').classList.toggle('open');">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">Report Found Item</span>
      </div>

      <div class="topbar-nav">
        <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/report_found.php" style="color: var(--primary-navy); font-weight: 600;">Report Item</a>
        <a href="<?= BASE_URL ?>/my_reports.php">My Reports</a>
        <a href="<?= BASE_URL ?>/logout.php" style="color: #DC2626;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
      </div>
    </header>

    <main class="portal-content">
      <?= render_flash() ?>

      <div class="page-header-row">
        <h1 class="page-title">Report Found Item</h1>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="margin-bottom: 24px;">
          <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
          <div class="alert-text">
            <?php foreach ($errors as $err): ?>
              <div><?= e($err) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="form-card">
        <form action="<?= BASE_URL ?>/report_found.php" method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="form-grid-2">
            <!-- Left Column: Item Name, Category, Location, Date -->
            <div>
              <div class="form-group">
                <label for="item_name" class="form-label">Item Name *</label>
                <input type="text" id="item_name" name="item_name" class="form-control no-icon" placeholder="e.g. Backpack" value="<?= e($itemName) ?>" required>
              </div>

              <div class="form-group">
                <label for="category_id" class="form-label">Category *</label>
                <select id="category_id" name="category_id" class="form-control no-icon" required>
                  <option value="">Select Category</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= ($categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                      <?= e($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="location_id" class="form-label">Location Found *</label>
                <select id="location_id" name="location_id" class="form-control no-icon" required>
                  <option value="">Select Location</option>
                  <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>" <?= ($locationId === (int)$loc['id']) ? 'selected' : '' ?>>
                      <?= e($loc['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="event_date" class="form-label">Date Found *</label>
                <input type="date" id="event_date" name="event_date" class="form-control no-icon" value="<?= e($eventDate) ?>" max="<?= date('Y-m-d') ?>" required>
              </div>
            </div>

            <!-- Right Column: Description & Image Upload -->
            <div>
              <div class="form-group">
                <label for="description" class="form-label">Description *</label>
                <textarea id="description" name="description" class="form-control" rows="5" placeholder="Describe the item in detail..." required><?= e($description) ?></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Upload Image <span style="font-weight: 400; color: var(--text-muted);">(Optional)</span></label>
                <div class="form-file-upload-box">
                  <input type="file" id="image" name="image" accept="image/*" data-preview="foundImagePreview">
                  <div class="image-preview-container" id="foundImagePreview"></div>
                </div>
              </div>
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
            <button type="submit" class="btn-primary" style="padding: 12px 36px; font-size: 15px;">
              <span>Submit</span>
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
