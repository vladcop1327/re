<?php
require 'config.php';

function generateCode() {
    return strtoupper(bin2hex(random_bytes(4)));
}

$amount = $_POST['amount'] ?? 10;
for ($i = 0; $i < $amount; $i++) {
    $code = generateCode();
    $stmt = $pdo->prepare("INSERT IGNORE INTO promo_codes (code) VALUES (?)");
    $stmt->execute([$code]);
}

header("Location: admin.php");
