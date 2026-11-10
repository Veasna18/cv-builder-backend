<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['GET', 'PUT', 'DELETE']);

$admin  = require_admin();
$method = $_SERVER['REQUEST_METHOD'];

// ---- List / search ----
if ($method === 'GET') {
    $q    = trim((string)($_GET['q'] ?? ''));
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $st = db()->prepare(
        'SELECT u.id, u.name, u.phone, u.role, u.status, u.created_at,
                (SELECT COUNT(*) FROM resumes r WHERE r.user_id = u.id) AS resume_count
           FROM users u
          WHERE (? = "" OR u.name LIKE ? OR u.phone LIKE ?)
          ORDER BY u.created_at DESC LIMIT 500'
    );
    $st->execute([$q, $like, $like]);
    respond(['users' => $st->fetchAll()]);
}

// ---- Update: status / role / password / name ----
if ($method === 'PUT') {
    $b  = input();
    $id = int_param('id');

    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $target = $st->fetch();
    if (!$target) {
        fail('User not found', 404);
    }

    $sets = [];
    $vals = [];
    $revokeSessions = false;

    if (isset($b['status'])) {
        if (!in_array($b['status'], ['active', 'blocked'], true)) fail('Invalid status', 422);
        if ($id === (int)$admin['id'] && $b['status'] !== 'active') fail('You cannot block your own account', 422);
        $sets[] = 'status = ?'; $vals[] = $b['status'];
        $revokeSessions = $revokeSessions || $b['status'] === 'blocked';
    }
    if (isset($b['role'])) {
        if (!in_array($b['role'], ['admin', 'user'], true)) fail('Invalid role', 422);
        if ($id === (int)$admin['id'] && $b['role'] !== 'admin') fail('You cannot remove your own admin role', 422);
        $sets[] = 'role = ?'; $vals[] = $b['role'];
    }
    if (isset($b['name'])) {
        $n = trim((string)$b['name']);
        if (mb_strlen($n) < 2 || mb_strlen($n) > 100) fail('Name must be 2–100 characters', 422);
        $sets[] = 'name = ?'; $vals[] = $n;
    }
    if (isset($b['password'])) {
        if (strlen((string)$b['password']) < 8) fail('Password must be at least 8 characters', 422);
        $sets[] = 'password_hash = ?'; $vals[] = password_hash((string)$b['password'], PASSWORD_DEFAULT);
        $revokeSessions = $revokeSessions || $id !== (int)$admin['id']; // keep the admin signed in
    }
    if (!$sets) {
        fail('Nothing to update', 422);
    }

    $vals[] = $id;
    db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    if ($revokeSessions) {
        db()->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$id]);
    }
    respond(['ok' => true]);
}

// ---- Delete (resumes + sessions are removed by ON DELETE CASCADE) ----
$id = int_param('id');
if ($id === (int)$admin['id']) {
    fail('You cannot delete your own account', 422);
}
$st = db()->prepare('DELETE FROM users WHERE id = ?');
$st->execute([$id]);
if ($st->rowCount() === 0) {
    fail('User not found', 404);
}
respond(['ok' => true]);
