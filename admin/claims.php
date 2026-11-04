<?php
/**
 * Campus LostLink — Admin Claims Verification (admin/claims.php)
 * Handles Claim Approval (Item -> Returned/Closed) & Rejection
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$adminId = (int)user_id();
$pdo = get_db_connection();

// Handle Approve / Reject Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $action  = $_POST['action'];

        // Fetch claim and item
        $stmt = $pdo->prepare('
            SELECT c.*, i.id AS item_id, i.item_name
            FROM claims c
            JOIN items i ON c.item_id = i.id
            WHERE c.id = :id
        ');
        $stmt->execute(['id' => $claimId]);
        $claim = $stmt->fetch();

        if ($claim) {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                // 1. Update claim status to approved and record verified_by
                $upClaim = $pdo->prepare('
                    UPDATE claims
                    SET status = \'approved\', verified_by = :admin_id, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $upClaim->execute(['admin_id' => $adminId, 'id' => $claimId]);

                // 2. Flowchart Rule: Return Item and Close Report (item status = "returned")
                $upItem = $pdo->prepare('
                    UPDATE items
                    SET status = \'returned\'
                    WHERE id = :item_id
                ');
                $upItem->execute(['item_id' => $claim['item_id']]);

                // 3. Mark any other pending claims on this same item as rejected (since item has been returned to owner)
                $upOthers = $pdo->prepare('
                    UPDATE claims
                    SET status = \'rejected\', verified_by = :admin_id, updated_at = CURRENT_TIMESTAMP
                    WHERE item_id = :item_id AND id != :claim_id AND status = \'pending\'
                ');
                $upOthers->execute(['admin_id' => $adminId, 'item_id' => $claim['item_id'], 'claim_id' => $claimId]);

                $pdo->commit();
                set_flash('success', 'Claim approved successfully! The item "' . $claim['item_name'] . '" has been marked as Returned and the report is closed.');
            } elseif ($action === 'reject') {
                // Reject claim
                $upClaim = $pdo->prepare('
                    UPDATE claims
                    SET status = \'rejected\', verified_by = :admin_id, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $upClaim->execute(['admin_id' => $adminId, 'id' => $claimId]);

                // Check if any other pending claims exist for this item; if not, return item to available
                $chk = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE item_id = :item_id AND status = \'pending\'');
                $chk->execute(['item_id' => $claim['item_id']]);
                if ((int)$chk->fetchColumn() === 0) {
                    $pdo->prepare('UPDATE items SET status = \'available\' WHERE id = :item_id')->execute(['item_id' => $claim['item_id']]);
                }

                set_flash('warning', 'Claim has been rejected.');
            }
        } else {
            set_flash('error', 'Claim not found.');
        }

        header('Location: ' . BASE_URL . '/admin/claims.php?' . http_build_query($_GET));
        exit;
    }
}

// Filter claims
$statusFilter = strtolower(trim($_GET['status'] ?? ''));
$where = [];
$params = [];

if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'c.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT c.*, 
           i.id AS item_id, i.item_name, i.type AS item_type, i.status AS item_status,
           u.full_name AS claimant_name, u.email AS claimant_email,
           v.full_name AS verifier_name
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimant_id = u.id
    LEFT JOIN users v ON c.verified_by = v.id
    $whereSql
    ORDER BY (CASE WHEN c.status = 'pending' THEN 1 ELSE 2 END), c.created_at DESC
");
$stmt->execute($params);
$claims = $stmt->fetchAll();

$pageTitle = 'Claims Verification — Admin Portal';
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
        <span class="topbar-title">Claims Verification</span>
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
          <h1 class="page-title">Claims Verification Queue</h1>
          <p style="font-size: 13px; color: var(--text-muted);">Review ownership proofs, verify claims, and officially return items to close reports.</p>
        </div>

        <!-- Filter tabs -->
        <div style="display: flex; gap: 8px; background: #E2E8F0; padding: 4px; border-radius: var(--radius-sm);">
          <a href="<?= BASE_URL ?>/admin/claims.php" class="page-btn <?= empty($statusFilter) ? 'active' : '' ?>" style="height: 32px; font-size: 12px; border: none;">All Claims</a>
          <a href="<?= BASE_URL ?>/admin/claims.php?status=pending" class="page-btn <?= ($statusFilter === 'pending') ? 'active' : '' ?>" style="height: 32px; font-size: 12px; border: none;">Pending</a>
          <a href="<?= BASE_URL ?>/admin/claims.php?status=approved" class="page-btn <?= ($statusFilter === 'approved') ? 'active' : '' ?>" style="height: 32px; font-size: 12px; border: none;">Approved</a>
          <a href="<?= BASE_URL ?>/admin/claims.php?status=rejected" class="page-btn <?= ($statusFilter === 'rejected') ? 'active' : '' ?>" style="height: 32px; font-size: 12px; border: none;">Rejected</a>
        </div>
      </div>

      <div class="data-card">
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Target Item</th>
                <th>Claimant</th>
                <th>Proof of Ownership</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Verified By</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($claims)): ?>
                <tr>
                  <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
                    No claims found for this filter.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($claims as $c): ?>
                  <tr style="<?= ($c['status'] === 'pending') ? 'background-color: #FFFDF7;' : '' ?>">
                    <td>#<?= (int)$c['id'] ?></td>
                    <td>
                      <a href="<?= BASE_URL ?>/item_details.php?id=<?= (int)$c['item_id'] ?>" style="font-weight: 600; color: var(--primary-navy);">
                        <?= e($c['item_name']) ?>
                      </a>
                      <div style="margin-top: 2px;">
                        <?= type_badge($c['item_type']) ?>
                        <span style="font-size: 11px; color: var(--text-muted); margin-left: 4px;">Item: <?= ucfirst(e($c['item_status'])) ?></span>
                      </div>
                    </td>
                    <td>
                      <div><strong><?= e($c['claimant_name']) ?></strong></div>
                      <div style="font-size: 11px; color: var(--text-muted);"><?= e($c['claimant_email']) ?></div>
                    </td>
                    <td style="max-width: 320px;">
                      <div style="font-size: 13px; line-height: 1.4; background: #F8FAFC; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); color: #334155;">
                        <?= nl2br(e($c['proof_description'])) ?>
                      </div>
                    </td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td><?= format_date($c['created_at']) ?></td>
                    <td>
                      <?php if (!empty($c['verifier_name'])): ?>
                        <span style="font-size: 12px; color: var(--text-muted);">
                          <i class="fa-solid fa-user-check" style="color: #059669;"></i> <?= e($c['verifier_name']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color: var(--text-light); font-size: 12px;">Unverified</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <?php if ($c['status'] === 'pending'): ?>
                        <form method="POST" style="display: inline-block;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="approve">
                          <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                          <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 12px; background: #059669;" data-confirm="Approve this claim? This will mark the item as 'Returned' and close the report." title="Approve & Return Item">
                            <i class="fa-solid fa-check"></i> Approve
                          </button>
                        </form>

                        <form method="POST" style="display: inline-block; margin-left: 4px;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="reject">
                          <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                          <button type="submit" class="btn-danger" style="padding: 6px 12px; font-size: 12px;" data-confirm="Reject this claim request?" title="Reject Claim">
                            <i class="fa-solid fa-xmark"></i> Reject
                          </button>
                        </form>
                      <?php else: ?>
                        <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">
                          Resolved
                        </span>
                      <?php endif; ?>
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
