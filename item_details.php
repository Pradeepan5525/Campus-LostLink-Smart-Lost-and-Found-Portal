<?php
/**
 * Campus LostLink — Item Details & Claim Submission (item_details.php)
 * Matches Mockup Screen 8
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();

$itemId = (int)($_GET['id'] ?? 0);
if ($itemId <= 0) {
    header('Location: ' . BASE_URL . '/items.php');
    exit;
}

// Fetch item with category, location, and reporter name
$stmt = $pdo->prepare('
    SELECT i.*, c.name AS category_name, l.name AS location_name, u.full_name AS reporter_name, u.email AS reporter_email
    FROM items i
    JOIN categories c ON i.category_id = c.id
    JOIN locations l ON i.location_id = l.id
    JOIN users u ON i.user_id = u.id
    WHERE i.id = :id
');
$stmt->execute(['id' => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Item not found.');
    header('Location: ' . BASE_URL . '/items.php');
    exit;
}

$currentUser = current_user();
$userId = user_id();

// Handle Claim Request Submission
$claimErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_claim') {
    require_login('/item_details.php?id=' . $itemId);

    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $claimErrors[] = 'Invalid or expired CSRF token.';
    } else {
        $proofDescription = trim($_POST['proof_description'] ?? '');
        if (empty($proofDescription) || mb_strlen($proofDescription) < 10) {
            $claimErrors[] = 'Please provide detailed proof of ownership (at least 10 characters).';
        }

        $claimCheck = can_user_claim_item($pdo, $userId, $item);
        if (!$claimCheck['allowed']) {
            $claimErrors[] = $claimCheck['reason'];
        }

        if (empty($claimErrors)) {
            $insertClaim = $pdo->prepare('
                INSERT INTO claims (item_id, claimant_id, proof_description, status, created_at, updated_at)
                VALUES (:item_id, :claimant_id, :proof_description, \'pending\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            $insertClaim->execute([
                'item_id'           => $itemId,
                'claimant_id'       => $userId,
                'proof_description' => $proofDescription
            ]);

            // Update item status to pending if it was available
            if ($item['status'] === 'available') {
                $updateItem = $pdo->prepare('UPDATE items SET status = \'pending\' WHERE id = :id');
                $updateItem->execute(['id' => $itemId]);
                $item['status'] = 'pending';
            }

            set_flash('success', 'Your claim request has been submitted successfully! The admin and finder will verify your claim.');
            header('Location: ' . BASE_URL . '/item_details.php?id=' . $itemId);
            exit;
        }
    }
}

// Find possible matches based on smart matching algorithm (+/- 7 days, category, location)
$possibleMatches = find_possible_matches($pdo, $itemId);

// Check if current user can claim this item
$canClaim = is_logged_in() ? can_user_claim_item($pdo, $userId, $item) : ['allowed' => true, 'reason' => ''];

// Image source
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

$pageTitle = $item['item_name'] . ' — Details';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1040px; margin: 32px auto; padding: 0 24px; width: 100%;">
  <!-- Back Link (Mockup Screen 8 Top-Left) -->
  <a href="<?= BASE_URL ?>/items.php<?= !empty($item['type']) ? '?type=' . e($item['type']) : '' ?>" class="back-link">
    <i class="fa-solid fa-arrow-left"></i>
    <span>Back to List</span>
  </a>

  <?php if (!empty($claimErrors)): ?>
    <div class="alert alert-error" style="margin-bottom: 24px;">
      <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
      <div class="alert-text">
        <?php foreach ($claimErrors as $err): ?>
          <div><?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Item Details Card (Mockup Screen 8) -->
  <div class="item-details-grid">
    <!-- Left Column: Large Image Placeholder -->
    <div class="item-details-image-box">
      <img src="<?= $imageSrc ?>" alt="<?= e($item['item_name']) ?>">
    </div>

    <!-- Right Column: Metadata & Claim CTA -->
    <div class="item-details-info">
      <h1 class="details-title"><?= e($item['item_name']) ?></h1>

      <div class="details-badges-row">
        <?= type_badge($item['type']) ?>
        <span class="location-pill">
          <i class="fa-solid fa-location-dot" style="font-size: 11px; margin-right: 4px;"></i>
          <?= e($item['location_name']) ?>
        </span>
        <span class="location-pill" style="background: #EFF6FF; color: #1D4ED8;">
          <?= e($item['category_name']) ?>
        </span>
        <?= status_badge($item['status']) ?>
      </div>

      <div class="details-date">
        <i class="fa-regular fa-calendar"></i>
        <span>Date <?= ($item['type'] === 'lost') ? 'Lost' : 'Found' ?>: <strong><?= format_date($item['event_date']) ?></strong></span>
      </div>

      <div class="details-section-heading">Description</div>
      <p class="details-text"><?= nl2br(e($item['description'])) ?></p>

      <div class="details-section-heading">Contact &amp; Claim Information</div>
      <p class="details-text" style="color: var(--text-muted); font-size: 13px;">
        If you have information about this item or are the rightful owner, please submit a claim request through the system. For campus safety, claims are verified by the finder or campus administration before return.
      </p>

      <div style="margin-top: auto; padding-top: 16px;">
        <?php if (!is_logged_in()): ?>
          <a href="<?= BASE_URL ?>/login.php?return=<?= urlencode('/item_details.php?id=' . $itemId) ?>" class="btn-primary" style="padding: 12px 28px;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            <span>Login to Send Claim Request</span>
          </a>
        <?php elseif ($userId === (int)$item['user_id']): ?>
          <div style="background: #F1F5F9; padding: 12px 18px; border-radius: var(--radius-sm); font-size: 13px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-user-check" style="color: var(--accent-blue);"></i>
            <span>You reported this item. You can manage it under <a href="<?= BASE_URL ?>/my_reports.php" style="font-weight: 600;">My Reports</a>.</span>
          </div>
        <?php elseif (!$canClaim['allowed']): ?>
          <div class="alert alert-warning" style="margin: 0; display: inline-flex;">
            <i class="fa-solid fa-circle-info alert-icon"></i>
            <span><?= e($canClaim['reason']) ?></span>
          </div>
        <?php else: ?>
          <button type="button" class="btn-primary" data-modal-open="claimModal" style="padding: 12px 28px; font-size: 14px;">
            <i class="fa-solid fa-hand-holding-hand"></i>
            <span>Send Claim Request</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Possible Matches Section (Flowchart Match Step) -->
  <?php if (!empty($possibleMatches)): ?>
    <div class="possible-matches-box">
      <div class="possible-matches-title">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
        <span>Possible Smart Matches (<?= count($possibleMatches) ?> found)</span>
      </div>
      <p style="font-size: 13px; color: #78350F; margin-bottom: 16px;">
        Our campus matching algorithm found matching <?= ($item['type'] === 'lost') ? 'found' : 'lost' ?> reports with similar category, location, and date proximity (&plusmn;7 days).
      </p>

      <div class="matches-cards-grid">
        <?php foreach ($possibleMatches as $match): ?>
          <div class="match-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
              <span class="badge-match">
                <i class="fa-solid fa-check"></i> <?= $match['match_score'] ?>% Match
              </span>
              <?= type_badge($match['type']) ?>
            </div>
            <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px; color: var(--text-main);">
              <?= e($match['item_name']) ?>
            </div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
              <div><i class="fa-solid fa-location-dot" style="width: 14px;"></i> <?= e($match['location_name']) ?></div>
              <div><i class="fa-regular fa-calendar" style="width: 14px;"></i> <?= format_date($match['event_date']) ?></div>
            </div>
            <div style="font-size: 11px; color: #92400E; background: #FEF3C7; padding: 4px 8px; border-radius: 4px; margin-bottom: 10px;">
              <?= e(implode(' &bull; ', $match['match_reasons'])) ?>
            </div>
            <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$match['id'] ?>" class="btn-primary" style="width: 100%; padding: 6px; font-size: 12px; text-align: center;">
              Inspect Match &rarr;
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Send Claim Request Modal (Mockup Screen 8 Flow) -->
<div class="modal-overlay" id="claimModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title">Submit Claim Request</h3>
      <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
    </div>

    <form action="<?= BASE_URL ?>/item_details.php?id=<?= $itemId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="submit_claim">

      <div class="modal-body">
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
          To verify that you are the rightful owner of <strong><?= e($item['item_name']) ?></strong>, please provide detailed proof of ownership (e.g. unique scratches, stickers, internal contents, lockscreen wallpaper, or receipt/serial numbers).
        </p>

        <div class="form-group" style="margin-bottom: 0;">
          <label for="proof_description" class="form-label">Proof of Ownership / Description *</label>
          <textarea id="proof_description" name="proof_description" class="form-control" rows="5" placeholder="e.g. It has a Batman sticker on the bottom left corner and contains my student ID card..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">
          <i class="fa-solid fa-paper-plane"></i>
          <span>Submit Claim</span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
