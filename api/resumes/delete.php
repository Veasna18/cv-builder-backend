<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['DELETE', 'POST']);

$user = require_auth();
$st = db()->prepare('DELETE FROM resumes WHERE id = ? AND user_id = ?');
$st->execute([int_param('id'), $user['id']]);

if ($st->rowCount() === 0) {
    fail('Resume not found', 404);
}
respond(['ok' => true]);
