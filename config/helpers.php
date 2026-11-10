<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

const TEMPLATES = ['circular', 'professional', 'vertical', 'horizontal', 'elegant', 'modern', 'casual', 'chrono', 'luxurious', 'classic', 'simple', 'metro', 'executive', 'profile'];

function ensure_custom_templates(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS resume_templates (
        id VARCHAR(30) NOT NULL PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        description VARCHAR(160) NOT NULL DEFAULT "",
        layout VARCHAR(30) NOT NULL,
        image_data MEDIUMTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');
    if (!db()->query("SHOW COLUMNS FROM resume_templates LIKE 'image_data'")->fetch()) {
        db()->exec('ALTER TABLE resume_templates ADD COLUMN image_data MEDIUMTEXT NULL');
    }
    db()->exec('CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB');
    $seeded = db()->query("SELECT setting_value FROM app_settings WHERE setting_key = 'template_catalog_v1'")->fetchColumn();
    if (!$seeded) {
        $defaults = [
            ['circular', 'Circular', 'Soft rounded details and photo-led header', 'circular'],
            ['professional', 'Professional', 'Polished structure with a skill sidebar', 'professional'],
            ['vertical', 'Vertical', 'Skills and contact in a vertical panel', 'vertical'],
            ['horizontal', 'Horizontal', 'Wide header with full-width sections', 'horizontal'],
            ['elegant', 'Elegant', 'Centered nameplate and refined details', 'elegant'],
            ['modern', 'Modern', 'Fresh accents and clear section rhythm', 'modern'],
            ['casual', 'Casual', 'Friendly color blocks and compact details', 'casual'],
            ['chrono', 'Chrono', 'Timeline style for work experience', 'chrono'],
            ['luxurious', 'Luxurious', 'Deep tones with a restrained accent', 'luxurious'],
            ['classic', 'Classic', 'Traditional single-column layout', 'classic'],
            ['simple', 'Simple', 'Minimal styling and open whitespace', 'simple'],
            ['metro', 'Metro', 'Geometric accents and strong section labels', 'metro'],
        ];
        $insert = db()->prepare('INSERT IGNORE INTO resume_templates (id, name, description, layout) VALUES (?, ?, ?, ?)');
        foreach ($defaults as $row) $insert->execute($row);
        db()->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('template_catalog_v1', 'seeded')");
    }
    db()->prepare('INSERT IGNORE INTO resume_templates (id, name, description, layout) VALUES (?, ?, ?, ?)')
        ->execute(['profile', 'Default CV', 'Blue profile header with a clean sidebar and readable sections.', 'profile']);
    // Remove the retired Population template from older installations as well as new ones.
    $retired = db()->query("SELECT id FROM resume_templates WHERE LOWER(id) LIKE '%population%' OR LOWER(name) LIKE '%population%'")->fetchAll(PDO::FETCH_COLUMN);
    if ($retired) {
        $fallback = 'modern';
        $placeholders = implode(',', array_fill(0, count($retired), '?'));
        db()->prepare("UPDATE resumes SET template = ? WHERE template IN ($placeholders)")->execute(array_merge([$fallback], $retired));
        db()->prepare("DELETE FROM resume_templates WHERE id IN ($placeholders)")->execute($retired);
    }
}

set_exception_handler(function (Throwable $e): void {
    error_log($e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => DEBUG ? $e->getMessage() : 'Server error']);
});

/** CORS + JSON headers + method check. Call first in every endpoint. */
function bootstrap(array $methods): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $originHost = parse_url($origin, PHP_URL_HOST);
    $isLocalOrigin = is_string($originHost)
        && (in_array(strtolower($originHost), ['localhost', '127.0.0.1'], true)
            || str_ends_with(strtolower($originHost), '.test'));
    if (in_array($origin, ALLOWED_ORIGINS, true) || $isLocalOrigin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if (!in_array($method, $methods, true)) {
        fail('Method not allowed', 405);
    }
}

function respond($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $code = 400): void
{
    respond(['error' => $message], $code);
}

/** Decoded JSON request body. */
function input(): array
{
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input') ?: '';
        if (strlen($raw) > 6000000) {
            fail('Payload too large', 413);
        }
        $body = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($body)) {
            fail('Invalid JSON body');
        }
    }
    return $body;
}

function int_param(string $key): int
{
    $v = $_GET[$key] ?? (input()[$key] ?? null);
    if (!is_numeric($v) || (int)$v < 1) {
        fail("Missing or invalid '$key'", 422);
    }
    return (int)$v;
}

// ---------- Auth ----------

function bearer_token(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $h = $v;
            }
        }
    }
    return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : null;
}

function current_user(): ?array
{
    $token = bearer_token();
    if (!$token) {
        return null;
    }
    $st = db()->prepare(
        'SELECT u.id, u.name, u.phone, u.role, u.status
           FROM auth_tokens t JOIN users u ON u.id = t.user_id
          WHERE t.token_hash = ? AND t.expires_at > ?'
    );
    $st->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
    return $st->fetch() ?: null;
}

function require_auth(): array
{
    $u = current_user();
    if (!$u) {
        fail('Please sign in again', 401);
    }
    if ($u['status'] !== 'active') {
        fail('This account has been blocked', 403);
    }
    return $u;
}

function require_admin(): array
{
    $u = require_auth();
    if ($u['role'] !== 'admin') {
        fail('Admins only', 403);
    }
    return $u;
}

function issue_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400 * TOKEN_TTL_DAYS);
    db()->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
        ->execute([$userId, hash('sha256', $token), $expires]);
    return $token;
}

function normalize_phone(string $phone): string
{
    $p = preg_replace('/[\s\-().]/', '', trim($phone));
    if (!preg_match('/^\+?\d{8,15}$/', $p)) {
        fail('Enter a valid phone number (8–15 digits)', 422);
    }
    return $p;
}

function public_user(array $u): array
{
    return ['id' => (int)$u['id'], 'name' => $u['name'], 'phone' => $u['phone'], 'role' => $u['role']];
}

// ---------- Resumes ----------

/** Validate resume input → [title, template, settingsJson, dataJson]. */
function clean_resume(array $b): array
{
    $title = mb_substr(trim((string)($b['title'] ?? '')), 0, 150) ?: 'Untitled Resume';
    $requestedTemplate = (string)($b['template'] ?? 'profile');
    ensure_custom_templates();
    $st = db()->prepare('SELECT id FROM resume_templates WHERE id = ?');
    $st->execute([$requestedTemplate]);
    $tpl = $st->fetchColumn() ? $requestedTemplate : 'professional';
    $settings = is_array($b['settings'] ?? null) ? $b['settings'] : [];
    $data = is_array($b['data'] ?? null) ? $b['data'] : [];
    return [
        $title,
        $tpl,
        json_encode($settings ?: new stdClass, JSON_UNESCAPED_UNICODE),
        json_encode($data ?: new stdClass, JSON_UNESCAPED_UNICODE),
    ];
}

function resume_row_to_api(array $r): array
{
    $r['id'] = (int)$r['id'];
    $r['settings'] = json_decode($r['settings'], true) ?: new stdClass;
    $r['data'] = json_decode($r['data'], true) ?: new stdClass;
    return $r;
}
