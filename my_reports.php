<?php
/**
 * Campus LostLink — My Reports & Claim Verification (my_reports.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$userId = user_id();
$pdo = get_db_connection();

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $deleteId = (int)($_POST['item_id'] ?? 0);
        $delStmt = $pdo->prepare('DELETE FROM items WHERE id = :id AND user_id = :uid');
        $delStmt->execute(['id' => $deleteId, 'uid' => $userId]);

        if ($delStmt->rowCount() > 0) {
            set_flash('success', 'Report deleted successfully.');
        } else {
            set_flash('error', 'Item could not be deleted or access denied.');
        }
        header('Location: ' . BASE_URL . '/my_reports.php');
        exit;
    }
}

// Handle Finder Verification of Claim (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve_claim', 'reject_claim'], true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $claimId = (int)($_POST['claim_id'] ?? 0);

        // Verify that the current user is the finder of the claimed item
        $cStmt = $pdo->prepare('
            SELECT c.id, c.item_id, i.user_id, i.status AS item_status
            FROM claims c
            JOIN items i ON c.item_id = i.id
            WHERE c.id = :cid
        ');
        $cStmt->execute(['cid' => $claimId]);
        $claimInfo = $cStmt->fetch();

        if ($claimInfo && (int)$claimInfo['user_id'] === $userId) {
            if ($_POST['action'] === 'approve_claim') {
                // Approve claim and set item status to returned/closed
                $pdo->beginTransaction();
                $upClaim = $pdo->prepare('UPDATE claims SET status = \'approved\', verified_by = :uid, updated_at = CURRENT_TIMESTAMP WHERE id = :cid');
                $upClaim->execute(['uid' => $userId, 'cid' => $claimId]);

                $upItem = $pdo->prepare('UPDATE items SET status = \'returned\' WHERE id = :iid');
                $upItem->execute(['iid' => $claimInfo['item_id']]);
                $pdo->commit();

                set_flash('success', 'Claim approved! The item is now marked as Returned and the report is closed.');
            } else {
                // Reject claim
                $upClaim = $pdo->prepare('UPDATE claims SET status = \'rejected\', verified_by = :uid, updated_at = CURRENT_TIMESTAMP WHERE id = :cid');
                $upClaim->execute(['uid' => $userId, 'cid' => $claimId]);

                // Reset item to available if no other pending claims
                $chkPending = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE item_id = :iid AND status = \'pending\'');
                $chkPending->execute(['iid' => $claimInfo['item_id']]);
                if ((int)$chkPending->fetchColumn() === 0) {
                    $pdo->prepare('UPDATE items SET status = \'available\' WHERE id = :iid')->execute(['iid' => $claimInfo['item_id']]);
                }

                set_flash('warning', 'Claim rejected.');
            }
        } else {
            set_flash('error', 'Unauthorized action on claim.');
        }

        header('Location: ' . BASE_URL . '/my_reports.php');
        exit;
    }
}

// Fetch all items reported by the user
$stmt = $pdo->prepare('
    SELECT i.*, c.name AS category_name, l.name AS location_name,
           (SELECT COUNT(*) FROM claims cl WHERE cl.item_id = i.id) AS total_claims_count,
           (SELECT COUNT(*) FROM claims cl WHERE cl.item_id = i.id AND cl.status = \'pending\') AS pending_claims_count
    FROM items i
    JOIN categories c ON i.category_id = c.id
    JOIN locations l ON i.location_id = l.id
    WHERE i.user_id = :uid
    ORDER BY i.created_at DESC
');
$stmt->execute(['uid' => $userId]);
$myReports = $stmt->fetchAll();

// Fetch claims submitted BY this user on other items
$claimStmt = $pdo->prepare('
    SELECT c.*, i.item_name, i.type, i.status AS item_status, l.name AS location_name, u.full_name AS reporter_name
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN locations l ON i.location_id = l.id
    JOIN users u ON i.user_id = u.id
    WHERE c.claimant_id = :uid
    ORDER BY c.created_at DESC
');
$claimStmt->execute(['uid' => $userId]);
$mySubmittedClaims = $claimStmt->fetchAll();

// Fetch claims submitted ON items this user found (for finder verification)
$incomingClaimsStmt = $pdo->prepare('
    SELECT c.*, i.item_name, i.status AS item_status, u.full_name AS claimant_name, u.email AS claimant_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimant_id = u.id
    WHERE i.user_id = :uid AND c.status = \'pending\'
    ORDER BY c.created_at ASC
');
$incomingClaimsStmt->execute(['uid' => $userId]);
$incomingClaims = $incomingClaimsStmt->fetchAll();

$pageTitle = 'My Reports & Claims — Campus LostLink';
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
        <span class="topbar-title">My Reports</span>
      </div>

      <div class="topbar-nav">
        <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/report_lost.php">Report Item</a>
        <a href="<?= BASE_URL ?>/my_reports.php" style="color: var(--primary-navy); font-weight: 600;">My Reports</a>
        <a href="<?= BASE_URL ?>/logout.php" style="color: #DC2626;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
      </div>
    </header>

    <main class="portal-content">
      <?= render_flash() ?>

      <div class="page-header-row">
        <div>
          <h1 class="page-title">My Reported Items</h1>
          <p style="font-size: 13px; color: var(--text-muted);">Manage your submitted lost and found reports and track claim activity.</p>
        </div>
        <div style="display: flex; gap: 10px;">
          <a href="<?= BASE_URL ?>/report_lost.php" class="btn-primary" style="background: #DC2626;">+ Lost Item</a>
          <a href="<?= BASE_URL ?>/report_found.php" class="btn-primary" style="background: #059669;">+ Found Item</a>
        </div>
      </div>

      <!-- Incoming Claims for Finder to Verify (Flowchart Step) -->
      <?php if (!empty($incomingClaims)): ?>
        <div class="data-card" style="border: 2px solid #FDE68A;">
          <div class="data-card-header" style="background: #FFFBEB;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-bell" style="color: #D97706;"></i>
              <h2 class="data-card-title" style="color: #92400E;">Claims Awaiting Your Verification (<?= count($incomingClaims) ?>)</h2>
            </div>
          </div>
          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Claimant</th>
                  <th>Proof Provided</th>
                  <th>Date</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($incomingClaims as $ic): ?>
                  <tr>
                    <td><strong><?= e($ic['item_name']) ?></strong></td>
                    <td>
                      <div><?= e($ic['claimant_name']) ?></div>
                      <div style="font-size: 11px; color: var(--text-muted);"><?= e($ic['claimant_email']) ?></div>
                    </td>
                    <td style="max-width: 320px;">
                      <div style="font-size: 13px; line-height: 1.4; background: #F8FAFC; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                        <?= nl2br(e($ic['proof_description'])) ?>
                      </div>
                    </td>
                    <td><?= format_date($ic['created_at']) ?></td>
                    <td style="text-align: right; white-space: nowrap;">
                      <form method="POST" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve_claim">
                        <input type="hidden" name="claim_id" value="<?= (int)$ic['id'] ?>">
                        <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 12px; background: #059669;" data-confirm="Approve this claim? This will mark the item as Returned and close the report.">
                          <i class="fa-solid fa-check"></i> Approve
                        </button>
                      </form>

                      <form method="POST" style="display: inline-block; margin-left: 6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject_claim">
                        <input type="hidden" name="claim_id" value="<?= (int)$ic['id'] ?>">
                        <button type="submit" class="btn-danger" style="padding: 6px 12px; font-size: 12px;" data-confirm="Are you sure you want to reject this claim?">
                          <i class="fa-solid fa-xmark"></i> Reject
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- Table of Student's Reported Items -->
      <div class="data-card">
        <div class="data-card-header">
          <h2 class="data-card-title">All My Reports</h2>
          <span style="font-size: 13px; color: var(--text-muted);"><?= count($myReports) ?> Total Items</span>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Type</th>
                <th>Category</th>
                <th>Location</th>
                <th>Event Date</th>
                <th>Status</th>
                <th>Claims</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($myReports)): ?>
                <tr>
                  <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
                    You have not submitted any reports yet.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($myReports as $item): ?>
                  <tr>
                    <td>
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$item['id'] ?>" style="font-weight: 600; color: var(--primary-navy);">
                        <?= e($item['item_name']) ?>
                      </a>
                    </td>
                    <td><?= type_badge($item['type']) ?></td>
                    <td><?= e($item['category_name']) ?></td>
                    <td><?= e($item['location_name']) ?></td>
                    <td><?= format_date($item['event_date']) ?></td>
                    <td><?= status_badge($item['status']) ?></td>
                    <td>
                      <?php if ((int)$item['total_claims_count'] > 0): ?>
                        <span style="font-weight: 600; color: var(--accent-blue);"><?= (int)$item['total_claims_count'] ?></span>
                        <?php if ((int)$item['pending_claims_count'] > 0): ?>
                          <span class="badge-pending" style="font-size: 10px; margin-left: 4px;"><?= (int)$item['pending_claims_count'] ?> pending</span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span style="color: var(--text-muted);">None</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$item['id'] ?>" class="btn-secondary" style="padding: 4px 10px; font-size: 12px; margin-right: 4px;">
                        View
                      </a>

                      <form method="POST" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="btn-danger" data-confirm="Are you sure you want to permanently delete this report?">
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

      <!-- My Claims History -->
      <div class="data-card">
        <div class="data-card-header">
          <h2 class="data-card-title">Claims I Have Submitted</h2>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Reported By</th>
                <th>Location</th>
                <th>Proof Submitted</th>
                <th>Claim Status</th>
                <th>Date Submitted</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($mySubmittedClaims)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">
                    You have not submitted any claim requests yet.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($mySubmittedClaims as $c): ?>
                  <tr>
                    <td>
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$c['item_id'] ?>" style="font-weight: 600; color: var(--primary-navy);">
                        <?= e($c['item_name']) ?>
                      </a>
                    </td>
                    <td><?= e($c['reporter_name']) ?></td>
                    <td><?= e($c['location_name']) ?></td>
                    <td style="max-width: 280px; font-size: 12px; color: var(--text-muted);">
                      <?= e(mb_strimwidth($c['proof_description'], 0, 80, '...')) ?>
                    </td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td><?= format_date($c['created_at']) ?></td>
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
