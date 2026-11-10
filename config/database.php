<?php
declare(strict_types=1);

// ---------- Settings (Laragon defaults) ----------
const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'cv_builder_db';
const DB_USER    = 'root';
const DB_PASS    = '';              // Laragon's MySQL root has no password by default
const DB_CHARSET = 'utf8mb4';

// Frontend origins allowed to call this API (CORS). Add your production URL here.
const ALLOWED_ORIGINS = ['http://localhost:5173', 'http://127.0.0.1:5173'];

const TOKEN_TTL_DAYS = 30;
const DEBUG          = true;        // set to false in production

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
