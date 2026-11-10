<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['POST']);

$b     = input();
$phone = normalize_phone((string)($b['phone'] ?? ''));
$pass  = (string)($b['password'] ?? '');

$st = db()->prepare('SELECT * FROM users WHERE phone = ?');
$st->execute([$phone]);
$user = $st->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    fail('Wrong phone number or password', 401);
}
if ($user['status'] !== 'active') {
    fail('This account has been blocked. Contact the administrator.', 403);
}

// Housekeeping: drop expired sessions.
db()->prepare('DELETE FROM auth_tokens WHERE expires_at < ?')->execute([date('Y-m-d H:i:s')]);

if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
}

respond(['token' => issue_token((int)$user['id']), 'user' => public_user($user)]);
