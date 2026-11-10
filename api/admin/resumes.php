<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['GET', 'DELETE']);
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $st = db()->prepare('DELETE FROM resumes WHERE id = ?');
    $st->execute([int_param('id')]);
    if ($st->rowCount() === 0) {
        fail('Resume not found', 404);
    }
    respond(['ok' => true]);
}

$rows = db()->query(
    'SELECT r.id, r.title, r.template, r.created_at, r.updated_at, u.name AS owner_name, u.phone AS owner_phone
       FROM resumes r JOIN users u ON u.id = r.user_id
      ORDER BY r.updated_at DESC LIMIT 500'
)->fetchAll();
respond(['resumes' => $rows]);
