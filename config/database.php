<?php
declare(strict_types=1);

// ---------- Settings (Laragon defaults) ----------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'cv_builder_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ''); // Laragon's MySQL root has no password by default
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Frontend origins allowed to call this API (CORS). Add your production URL here.
define('ALLOWED_ORIGINS', array_values(array_filter(array_map(
    'trim',
    explode(',', getenv('ALLOWED_ORIGINS') ?: 'http://localhost:5173,http://127.0.0.1:5173')
))));

define('TOKEN_TTL_DAYS', 30);
define('DEBUG', filter_var(getenv('DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN));

date_default_timezone_set('Asia/Phnom_Penh');

/** Shared PDO connection (prepared statements only, real server-side prepares). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Database connection failed' . (DEBUG ? ': ' . $e->getMessage() : '')]);
            exit;
        }
    }
    return $pdo;
}
