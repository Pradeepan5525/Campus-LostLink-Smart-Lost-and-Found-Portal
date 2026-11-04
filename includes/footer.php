<?php
/**
 * Campus LostLink — Public & Shared Site Footer
 */

declare(strict_types=1);
?>

<footer class="site-footer">
  <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
    <div>
      <strong>Campus LostLink</strong> &copy; <?= date('Y') ?> — Smart Lost &amp; Found Portal
    </div>
    <div style="display: flex; gap: 20px; font-size: 13px;">
      <a href="<?= BASE_URL ?>/items.php?type=lost" style="color: var(--text-muted);">Lost Items</a>
      <a href="<?= BASE_URL ?>/items.php?type=found" style="color: var(--text-muted);">Found Items</a>
      <a href="<?= BASE_URL ?>/index.php" style="color: var(--text-muted);">Home</a>
    </div>
  </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
