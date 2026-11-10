<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['GET']);
require_admin();

$pdo = db();
$one = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();

// Last 7 days (including today), zero-filled.
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = ['date' => date('Y-m-d', strtotime("-$i day")), 'users' => 0, 'resumes' => 0];
}
foreach (['users', 'resumes'] as $table) {
    $rows = $pdo->query(
        "SELECT DATE(created_at) d, COUNT(*) c FROM $table
          WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)"
    )->fetchAll();
    foreach ($rows as $r) {
        if (isset($days[$r['d']])) {
            $days[$r['d']][$table] = (int)$r['c'];
        }
    }
}

respond([
    'totals' => [
        'users'     => $one("SELECT COUNT(*) FROM users WHERE role = 'user'"),
        'admins'    => $one("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
        'blocked'   => $one("SELECT COUNT(*) FROM users WHERE status = 'blocked'"),
        'resumes'   => $one('SELECT COUNT(*) FROM resumes'),
        'newUsers7' => $one('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE() - INTERVAL 6 DAY'),
    ],
    'daily'     => array_values($days),
    'templates' => $pdo->query('SELECT template, COUNT(*) AS count FROM resumes GROUP BY template ORDER BY count DESC')->fetchAll(),
    'recent'    => $pdo->query('SELECT id, name, phone, role, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll(),
]);
