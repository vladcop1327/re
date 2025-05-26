<?php
require 'config.php';

$id    = (int)($_POST['id'] ?? 0);
$label = trim($_POST['label'] ?? '');

if ($id && $label) {
    $pdo->prepare("UPDATE promo_codes SET segment_label = ? WHERE id = ?")
        ->execute([$label, $id]);
    echo 'OK';
}
