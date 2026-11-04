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

// Database Credentials from Environment Variables (supports DB_*, PG*, and DATABASE_URL)
if ($dbUrl = getenv('DATABASE_URL')) {
    $dbParts = parse_url($dbUrl);
    $host     = $dbParts['host'] ?? 'localhost';
    $port     = (string)($dbParts['port'] ?? '5432');
    $dbname   = ltrim($dbParts['path'] ?? 'campus_lostlink', '/');
    $user     = $dbParts['user'] ?? 'postgres';
    $password = $dbParts['pass'] ?? '';
} else {
    $host     = getenv('DB_HOST') ?: getenv('PGHOST') ?: 'localhost';
    $dbname   = getenv('DB_NAME') ?: getenv('PGDATABASE') ?: 'campus_lostlink';
    $user     = getenv('DB_USER') ?: getenv('PGUSER') ?: 'postgres';
    $password = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: getenv('PGPASSWORD') ?: '1234';
    $port     = getenv('DB_PORT') ?: getenv('PGPORT') ?: '5432';
}

if (!defined('DB_HOST')) define('DB_HOST', $host);
if (!defined('DB_PORT')) define('DB_PORT', $port);
if (!defined('DB_NAME')) define('DB_NAME', $dbname);
if (!defined('DB_USER')) define('DB_USER', $user);
if (!defined('DB_PASS')) define('DB_PASS', $password);

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );
    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Database connection failed.");
}

/**
 * Returns the active PDO database connection singleton.
 */
function get_db_connection(): PDO
{
    global $pdo;
    return $pdo;
}
