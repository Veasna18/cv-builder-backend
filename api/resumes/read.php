<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['GET']);

$user = require_auth();

// GET read.php?id=12 → one full resume
if (isset($_GET['id'])) {
    $st = db()->prepare('SELECT * FROM resumes WHERE id = ? AND user_id = ?');
    $st->execute([int_param('id'), $user['id']]);
    $row = $st->fetch();
    if (!$row) {
        fail('Resume not found', 404);
    }
    respond(['resume' => resume_row_to_api($row)]);
}

// GET read.php → list of the signed-in user's resumes (lightweight)
$st = db()->prepare(
    "SELECT id, title, template, created_at, updated_at,
            JSON_UNQUOTE(JSON_EXTRACT(data, '\$.personal.fullName')) AS full_name,
            JSON_UNQUOTE(JSON_EXTRACT(data, '\$.personal.jobTitle')) AS job_title
       FROM resumes WHERE user_id = ? ORDER BY updated_at DESC"
);
$st->execute([$user['id']]);
respond(['resumes' => $st->fetchAll()]);
