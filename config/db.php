<?php
declare(strict_types=1);

/**
 * Campus LostLink — Database Configuration & App Constants
 */

// ==========================================
// 1. APPLICATION CONSTANTS
// ==========================================

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Campus LostLink');
}

if (!defined('APP_TAGLINE')) {
    define('APP_TAGLINE', 'Smart Lost and Found Portal');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// ==========================================
// 2. BASE URL CONFIGURATION
// Supports Render HTTPS and Local Development
// ==========================================

if (!defined('BASE_URL')) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $forwardedProto = strtolower(
        trim(
            explode(
                ',',
                $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
            )[0]
        )
    );

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https'
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

    $protocol = $isHttps ? 'https://' : 'http://';

    if (stripos($host, 'onrender.com') !== false) {
        define('BASE_URL', $protocol . $host);
    } else {
        $scriptName = str_replace(
            '\\',
            '/',
            $_SERVER['SCRIPT_NAME'] ?? ''
        );

        $basePath = '';

        if (strpos($scriptName, '/campus-lostlink/') !== false) {
            $basePath = '/campus-lostlink';
        }

        define('BASE_URL', $protocol . $host . $basePath);
    }
}

// ==========================================
// 3. UPLOAD CONFIGURATION
// ==========================================

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/../uploads/');
}

if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
}

if (!defined('ALLOWED_MIME_TYPES')) {
    define('ALLOWED_MIME_TYPES', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ]);
}

// ==========================================
// 4. DATABASE CONFIGURATION
// Supports Render DATABASE_URL and PG variables
// ==========================================

$dbUrl = getenv('DATABASE_URL');

$sslMode = '';

if ($dbUrl !== false && $dbUrl !== '') {
    $dbParts = parse_url($dbUrl);

    if ($dbParts === false || empty($dbParts['host'])) {
        error_log('Invalid DATABASE_URL configuration.');
        http_response_code(500);
        exit('Invalid database configuration.');
    }

    $dbHost = $dbParts['host'];
    $dbPort = (string)($dbParts['port'] ?? '5432');

    $dbName = rawurldecode(
        ltrim($dbParts['path'] ?? 'campus_lostlink', '/')
    );

    $dbUser = rawurldecode(
        $dbParts['user'] ?? 'postgres'
    );

    $dbPassword = rawurldecode(
        $dbParts['pass'] ?? ''
    );

    if (!empty($dbParts['query'])) {
        parse_str($dbParts['query'], $queryParams);
        $sslMode = $queryParams['sslmode'] ?? '';
    }
} else {
    $dbHost = getenv('DB_HOST')
        ?: getenv('PGHOST')
        ?: 'localhost';

    $dbName = getenv('DB_NAME')
        ?: getenv('PGDATABASE')
        ?: 'campus_lostlink';

    $dbUser = getenv('DB_USER')
        ?: getenv('PGUSER')
        ?: 'postgres';

    $dbPassword = getenv('DB_PASSWORD')
        ?: getenv('DB_PASS')
        ?: getenv('PGPASSWORD')
        ?: '1234';

    $dbPort = getenv('DB_PORT')
        ?: getenv('PGPORT')
        ?: '5432';

    $sslMode = getenv('DB_SSLMODE') ?: '';
}

// ==========================================
// 5. DEFINE DATABASE CONSTANTS
// ==========================================

if (!defined('DB_HOST')) {
    define('DB_HOST', $dbHost);
}

if (!defined('DB_PORT')) {
    define('DB_PORT', $dbPort);
}

if (!defined('DB_NAME')) {
    define('DB_NAME', $dbName);
}

if (!defined('DB_USER')) {
    define('DB_USER', $dbUser);
}

if (!defined('DB_PASS')) {
    define('DB_PASS', $dbPassword);
}

// ==========================================
// 6. POSTGRESQL CONNECTION
// ==========================================

try {
    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";

    if ($sslMode !== '') {
        $dsn .= ";sslmode={$sslMode}";
    }

    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log(
        'Campus LostLink Database Error: ' . $e->getMessage()
    );

    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

// ==========================================
// 7. DATABASE CONNECTION FUNCTION
// ==========================================

function get_db_connection(): PDO
{
    global $pdo;

    return $pdo;
}
