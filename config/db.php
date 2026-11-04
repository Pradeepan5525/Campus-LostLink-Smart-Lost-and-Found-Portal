<?php
/**
 * Campus LostLink — Database Configuration & App Constants
 */

declare(strict_types=1);

// App Constants
define('APP_NAME', 'Campus LostLink');
define('APP_TAGLINE', 'Smart Lost and Found Portal');
define('APP_VERSION', '1.0.0');

// Calculate base web root dynamically so it works in both root and subfolder (/campus-lostlink)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Detect if running under /campus-lostlink or at document root
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($scriptName, '/campus-lostlink') !== false) {
        define('BASE_URL', $protocol . $host . '/campus-lostlink');
    } else {
        // If web server root is pointed directly to the project folder
        define('BASE_URL', $protocol . $host);
    }
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB limit
define('ALLOWED_MIME_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg'
]);

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'campus_lostlink');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '1234');

/**
 * Returns a singleton PDO connection to the PostgreSQL database.
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly error message for users, with hints for setup
            http_response_code(500);
            echo '<div style="font-family: Inter, sans-serif; max-width: 600px; margin: 40px auto; padding: 24px; border: 1px solid #fed7aa; background: #fffbeb; border-radius: 8px;">';
            echo '<h2 style="color: #9a3412; margin-top: 0;">Database Connection Error</h2>';
            echo '<p style="color: #7c2d12;">Could not connect to PostgreSQL database <strong>' . htmlspecialchars(DB_NAME) . '</strong>.</p>';
            echo '<p style="color: #4b5563; font-size: 14px;"><strong>Error details:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<div style="background: #f8fafc; padding: 12px; border-radius: 6px; font-size: 13px; color: #334155;">';
            echo '<strong>Troubleshooting Checklist:</strong><br>';
            echo '1. Ensure PostgreSQL service is running on port 5432.<br>';
            echo '2. Verify database <code>campus_lostlink</code> exists (import <code>database/schema.sql</code> and <code>seed.sql</code>).<br>';
            echo '3. Check password in <code>config/db.php</code> (default: <code>1234</code> or <code>postgres</code>).<br>';
            echo '4. Verify <code>pdo_pgsql</code> extension is enabled in <code>php.ini</code>.';
            echo '</div>';
            echo '</div>';
            exit;
        }
    }

    return $pdo;
}
