<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['PUT', 'POST']);

$user = require_auth();
$id   = int_param('id');

$st = db()->prepare('SELECT * FROM resumes WHERE id = ? AND user_id = ?');
$st->execute([$id, $user['id']]);
$row = $st->fetch();
if (!$row) {
    fail('Resume not found', 404);
}

// Missing fields keep their stored value (partial updates are fine).
$merged = array_merge([
    'title'    => $row['title'],
    'template' => $row['template'],
    'settings' => json_decode($row['settings'], true),
    'data'     => json_decode($row['data'], true),
], input());
[$title, $template, $settings, $data] = clean_resume($merged);

db()->prepare('UPDATE resumes SET title = ?, template = ?, settings = ?, data = ? WHERE id = ? AND user_id = ?')
    ->execute([$title, $template, $settings, $data, $id, $user['id']]);

respond(['ok' => true]);
