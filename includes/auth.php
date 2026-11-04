<?php
/**
 * Campus LostLink — Authentication & Access Control
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function start_session_safe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Secure cookie settings
        $lifetime = 60 * 60 * 24; // 1 day
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// Automatically initialize session on include
start_session_safe();

/**
 * Checks if the visitor is currently authenticated.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Returns current logged-in user array or null.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'        => $_SESSION['user_id'] ?? 0,
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'email'     => $_SESSION['email'] ?? '',
        'role'      => $_SESSION['role'] ?? 'student',
    ];
}

/**
 * Get current user ID.
 */
function user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current user role.
 */
function user_role(): string
{
    return $_SESSION['role'] ?? '';
}

/**
 * Check if the user is an admin.
 */
function is_admin(): bool
{
    return is_logged_in() && user_role() === 'admin';
}

/**
 * Guard: requires user to be logged in, otherwise redirects to login.php.
 */
function require_login(string $returnUrl = ''): void
{
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        $target = BASE_URL . '/login.php';
        if (!empty($returnUrl)) {
            $target .= '?return=' . urlencode($returnUrl);
        }
        header("Location: $target");
        exit;
    }
}

/**
 * Guard: requires user to be an admin, otherwise redirects to dashboard or 403.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'Access denied. Administrator privileges required.';
        header("Location: " . BASE_URL . "/dashboard.php");
        exit;
    }
}

/**
 * If already logged in, redirect user away from login/register pages.
 */
function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        if (is_admin()) {
            header("Location: " . BASE_URL . "/admin/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "/dashboard.php");
        }
        exit;
    }
}

/**
 * Log in a user and regenerate session ID to prevent fixation.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['logged_at'] = time();
}

/**
 * Destroy session and log out user.
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
