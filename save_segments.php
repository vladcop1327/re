<?php
require 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$segments = $data['segments'] ?? [];
$main_id = (int)($data['main'] ?? -1);
$main_chance = (int)($data['main_chance'] ?? 10);

$pdo->exec("DELETE FROM wheel_segments");
foreach ($segments as $i => $label) {
    $is_main = ($i == $main_id) ? 1 : 0;
    $pdo->prepare("INSERT INTO wheel_segments (label, is_main) VALUES (?, ?)")->execute([$label, $is_main]);
}

$pdo->prepare("REPLACE INTO settings (key_name, value) VALUES ('main_chance', ?)")->execute([$main_chance]);
echo json_encode(['status' => 'ok']);
