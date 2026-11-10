<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['POST']);

if ($token = bearer_token()) {
    db()->prepare('DELETE FROM auth_tokens WHERE token_hash = ?')->execute([hash('sha256', $token)]);
}
respond(['ok' => true]);
