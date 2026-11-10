<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['POST']);

$user = require_auth();
[$title, $template, $settings, $data] = clean_resume(input());

db()->prepare('INSERT INTO resumes (user_id, title, template, settings, data) VALUES (?, ?, ?, ?, ?)')
    ->execute([$user['id'], $title, $template, $settings, $data]);

respond(['id' => (int)db()->lastInsertId()], 201);
