<?php
/**
 * Campus LostLink — Utility Functions & Business Logic
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Escapes HTML output safely.
 */
function e(?string $string): string
{
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generates or retrieves existing CSRF token.
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safe();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates submitted CSRF token.
 */
function verify_csrf(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safe();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Outputs a hidden input with CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Set a flash message (type: success, error, info, warning).
 */
function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safe();
    }
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Retrieve and clear flash messages.
 */
function get_flash(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safe();
    }
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Renders flash messages as HTML alerts.
 */
function render_flash(): string
{
    $flashes = get_flash();
    if (empty($flashes)) {
        return '';
    }

    $html = '<div class="flash-messages-container">';
    foreach ($flashes as $type => $messages) {
        $badgeClass = match ($type) {
            'error'   => 'alert-error',
            'success' => 'alert-success',
            'warning' => 'alert-warning',
            default   => 'alert-info',
        };
        $iconClass = match ($type) {
            'error'   => 'fa-solid fa-triangle-exclamation',
            'success' => 'fa-solid fa-circle-check',
            'warning' => 'fa-solid fa-circle-exclamation',
            default   => 'fa-solid fa-circle-info',
        };

        foreach ($messages as $msg) {
            $html .= sprintf(
                '<div class="alert %s"><i class="%s alert-icon"></i><span class="alert-text">%s</span><button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button></div>',
                e($badgeClass),
                e($iconClass),
                e($msg)
            );
        }
    }
    $html .= '</div>';
    return $html;
}

/**
 * Formats date into readable string (e.g., 'Apr 25, 2025').
 */
function format_date(?string $dateStr, string $format = 'M j, Y'): string
{
    if (empty($dateStr)) {
        return '—';
    }
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return e($dateStr);
    }
}

/**
 * Returns HTML badge for status matching the design system.
 */
function status_badge(string $status): string
{
    $statusLower = strtolower(trim($status));
    $label = ucfirst($statusLower);
    $cssClass = match ($statusLower) {
        'pending'   => 'badge-pending',
        'available' => 'badge-available',
        'approved'  => 'badge-approved',
        'rejected'  => 'badge-rejected',
        'returned'  => 'badge-returned',
        'closed'    => 'badge-closed',
        'claimed'   => 'badge-claimed',
        default     => 'badge-default'
    };

    return sprintf('<span class="status-badge %s">%s</span>', e($cssClass), e($label));
}

/**
 * Returns HTML badge for item type (Lost / Found).
 */
function type_badge(string $type): string
{
    $typeLower = strtolower(trim($type));
    $label = ucfirst($typeLower);
    $cssClass = ($typeLower === 'lost') ? 'type-badge-lost' : 'type-badge-found';
    return sprintf('<span class="type-badge %s">%s</span>', e($cssClass), e($label));
}

/**
 * Securely handles file upload for an item image.
 * Validates MIME type, file size <= 2MB, generates safe random filename.
 */
function handle_image_upload(array $file): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return null;
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Optional upload
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error code: ' . $file['error']);
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Image file size must be less than 2 MB.');
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, ALLOWED_MIME_TYPES)) {
        throw new RuntimeException('Invalid image format. Allowed formats: JPG, PNG, WebP, SVG.');
    }

    $ext = ALLOWED_MIME_TYPES[$mime];

    // Ensure uploads folder exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Generate safe, unguessable file name
    $newFileName = sprintf('item_%s_%s.%s', time(), bin2hex(random_bytes(8)), $ext);
    $targetPath = UPLOAD_DIR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Failed to move uploaded image.');
    }

    return 'uploads/' . $newFileName;
}

/**
 * Fetch all categories from database.
 */
function get_categories(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

/**
 * Fetch all locations from database.
 */
function get_locations(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM locations ORDER BY name ASC');
    return $stmt->fetchAll();
}

/**
 * Smart Lost & Found Matching Algorithm:
 * Compares an item with opposite-type items based on:
 * - Same Category (40 pts)
 * - Same Location (30 pts)
 * - Proximity of dates (+/- 7 days, up to 20 pts)
 * - Name similarity (up to 10 pts)
 */
function find_possible_matches(PDO $pdo, int $itemId, int $limit = 4): array
{
    // 1. Fetch current item
    $stmt = $pdo->prepare('
        SELECT i.*, c.name AS category_name, l.name AS location_name
        FROM items i
        JOIN categories c ON i.category_id = c.id
        JOIN locations l ON i.location_id = l.id
        WHERE i.id = :id
    ');
    $stmt->execute(['id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        return [];
    }

    // Target the opposite type (if Lost -> match Found, if Found -> match Lost)
    $oppositeType = ($item['type'] === 'lost') ? 'found' : 'lost';

    // 2. Fetch candidates within date window +/- 7 days or matching category/location
    $candidateSql = '
        SELECT i.*, c.name AS category_name, l.name AS location_name, u.full_name AS reporter_name
        FROM items i
        JOIN categories c ON i.category_id = c.id
        JOIN locations l ON i.location_id = l.id
        JOIN users u ON i.user_id = u.id
        WHERE i.type = :opposite_type
          AND i.id != :item_id
          AND i.status IN (\'available\', \'pending\')
          AND (
                i.category_id = :category_id
             OR i.location_id = :location_id
             OR (i.event_date BETWEEN :date_start AND :date_end)
          )
    ';

    $eventDate = new DateTime($item['event_date']);
    $dateStart = (clone $eventDate)->modify('-7 days')->format('Y-m-d');
    $dateEnd   = (clone $eventDate)->modify('+7 days')->format('Y-m-d');

    $cStmt = $pdo->prepare($candidateSql);
    $cStmt->execute([
        'opposite_type' => $oppositeType,
        'item_id'       => $itemId,
        'category_id'   => $item['category_id'],
        'location_id'   => $item['location_id'],
        'date_start'    => $dateStart,
        'date_end'      => $dateEnd,
    ]);
    $candidates = $cStmt->fetchAll();

    $scoredMatches = [];

    foreach ($candidates as $candidate) {
        $score = 0;
        $reasons = [];

        // Category match: +40 points
        if ((int)$candidate['category_id'] === (int)$item['category_id']) {
            $score += 40;
            $reasons[] = 'Same Category (' . $item['category_name'] . ')';
        }

        // Location match: +30 points
        if ((int)$candidate['location_id'] === (int)$item['location_id']) {
            $score += 30;
            $reasons[] = 'Same Location (' . $item['location_name'] . ')';
        }

        // Date proximity (+/- 7 days): up to +20 points
        $candDate = new DateTime($candidate['event_date']);
        $dayDiff = abs((int)$eventDate->diff($candDate)->format('%r%a'));

        if ($dayDiff === 0) {
            $score += 20;
            $reasons[] = 'Exact same date';
        } elseif ($dayDiff <= 2) {
            $score += 16;
            $reasons[] = sprintf('Within %d days', $dayDiff);
        } elseif ($dayDiff <= 7) {
            $score += 10;
            $reasons[] = sprintf('Within %d days', $dayDiff);
        }

        // Title text similarity: up to +10 points
        similar_text(strtolower($item['item_name']), strtolower($candidate['item_name']), $simPercent);
        if ($simPercent > 60) {
            $score += 10;
            $reasons[] = 'High name similarity';
        } elseif ($simPercent > 40) {
            $score += 5;
        }

        // Only include if score meets reasonable threshold (>= 40)
        if ($score >= 40) {
            $candidate['match_score'] = min(100, $score);
            $candidate['match_reasons'] = $reasons;
            $scoredMatches[] = $candidate;
        }
    }

    // Sort by match score descending
    usort($scoredMatches, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

    return array_slice($scoredMatches, 0, $limit);
}

/**
 * Checks if a specific user can submit a claim for an item:
 * - Cannot claim own item
 * - Cannot submit duplicate pending claim
 * - Item must be available or pending
 */
function can_user_claim_item(PDO $pdo, int $userId, array $item): array
{
    if ((int)$item['user_id'] === $userId) {
        return [
            'allowed' => false,
            'reason'  => 'You cannot submit a claim for an item you reported.'
        ];
    }

    if (!in_array($item['status'], ['available', 'pending'], true)) {
        return [
            'allowed' => false,
            'reason'  => 'This item is no longer available for claims.'
        ];
    }

    // Check existing claim
    $stmt = $pdo->prepare('SELECT status FROM claims WHERE item_id = :item_id AND claimant_id = :user_id');
    $stmt->execute(['item_id' => $item['id'], 'user_id' => $userId]);
    $claim = $stmt->fetch();

    if ($claim) {
        return [
            'allowed' => false,
            'reason'  => 'You have already submitted a claim for this item (Status: ' . ucfirst($claim['status']) . ').'
        ];
    }

    return ['allowed' => true, 'reason' => ''];
}
