<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/helpers.php';
bootstrap(['GET', 'POST', 'PUT', 'DELETE']);
ensure_custom_templates();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $custom = db()->query('SELECT id, name, description AS `desc`, layout, image_data FROM resume_templates ORDER BY created_at DESC')->fetchAll();
    respond(['templates' => $custom]);
}

require_admin();
$b = input();
$id = strtolower(trim((string)($b['id'] ?? '')));
$name = trim((string)($b['name'] ?? ''));
$description = trim((string)($b['desc'] ?? ''));
$layout = (string)($b['layout'] ?? 'modern');
$image = trim((string)($b['image_data'] ?? ''));
if (!preg_match('/^[a-z0-9-]{2,30}$/', $id)) fail('Template ID must be 2–30 lowercase letters, digits, or hyphens', 422);
if ($name === '' || mb_strlen($name) > 80 || mb_strlen($description) > 160) fail('Enter a template name and a description under 160 characters', 422);
if (!in_array($layout, TEMPLATES, true)) fail('Choose a valid base layout', 422);
if (strlen($image) > 5000000 || ($image !== '' && !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $image))) {
    fail('Upload a JPG, PNG, or WebP template preview under 3.5 MB', 422);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->prepare('INSERT INTO resume_templates (id, name, description, layout, image_data) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $name, $description, $layout, $image ?: null]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') fail('A template with that ID already exists', 409);
        throw $e;
    }
    respond(['ok' => true], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $st = db()->prepare('UPDATE resume_templates SET name = ?, description = ?, layout = ?, image_data = ? WHERE id = ?');
    $st->execute([$name, $description, $layout, $image ?: null, $id]);
    if ($st->rowCount() === 0) {
        $check = db()->prepare('SELECT id FROM resume_templates WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) fail('Template not found', 404);
    }
    respond(['ok' => true]);
}

$st = db()->prepare('SELECT layout FROM resume_templates WHERE id = ?');
$st->execute([$id]);
$fallback = $st->fetchColumn();
if (!$fallback) fail('Template not found', 404);
db()->prepare('UPDATE resumes SET template = ? WHERE template = ?')->execute([$fallback, $id]);
db()->prepare('DELETE FROM resume_templates WHERE id = ?')->execute([$id]);
respond(['ok' => true]);
