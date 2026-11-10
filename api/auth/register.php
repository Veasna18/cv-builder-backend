<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['POST']);

$b     = input();
$name  = trim((string)($b['name'] ?? ''));
$phone = normalize_phone((string)($b['phone'] ?? ''));
$pass  = (string)($b['password'] ?? '');

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    fail('Name must be 2–100 characters', 422);
}
if (strlen($pass) < 8) {
    fail('Password must be at least 8 characters', 422);
}

$st = db()->prepare('SELECT id FROM users WHERE phone = ?');
$st->execute([$phone]);
if ($st->fetch()) {
    fail('An account with this phone number already exists', 409);
}

try {
    db()->prepare('INSERT INTO users (name, phone, password_hash, role) VALUES (?, ?, ?, ?)')
        ->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT), 'user']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        fail('An account with this phone number already exists', 409);
    }
    throw $e;
}

$id = (int)db()->lastInsertId();
respond([
    'token' => issue_token($id),
    'user'  => ['id' => $id, 'name' => $name, 'phone' => $phone, 'role' => 'user'],
], 201);
