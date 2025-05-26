<?php
require 'config.php';

$telegramId = (int)($_POST['tg_id'] ?? 0);
$code = trim($_POST['code'] ?? '');
$segment = trim($_POST['segment'] ?? '');

if (!$telegramId) {
    http_response_code(403);
    exit('NO TELEGRAM');
}
if (!$code) exit('NO');

// Получаем текущие данные пользователя
$stmt = $pdo->prepare("SELECT wrong_attempts, banned_until FROM users WHERE telegram_id = ?");
$stmt->execute([$telegramId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(403);
    exit('USER NOT FOUND');
}

// Проверка блокировки
if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
    exit('BANNED');
}

// Проверка промокода
$stmt = $pdo->prepare("SELECT id FROM promo_codes WHERE code = ? AND used = 0");
$stmt->execute([$code]);
$promo = $stmt->fetch();

if ($promo) {
    // OK: сброс счётчика, записываем победителя
    $pdo->prepare("UPDATE users SET wrong_attempts = 0 WHERE telegram_id = ?")->execute([$telegramId]);
    $pdo->prepare("UPDATE promo_codes SET used = 1, used_by = ?, used_at = NOW(), segment_label = ? WHERE id = ?")
        ->execute([$telegramId, $segment, $promo['id']]);
	echo 'OK:' . $promo['id'];   // ▸ было:  exit('OK');
	exit;
} else {
    // Ошибка — увеличиваем количество попыток
    $wrong = (int)$user['wrong_attempts'] + 1;

    if ($wrong >= 3) {
        $banUntil = date('Y-m-d H:i:s', time() + 86400);
        $pdo->prepare("UPDATE users SET wrong_attempts = 0, banned_until = ? WHERE telegram_id = ?")
            ->execute([$banUntil, $telegramId]);
        exit('BANNED');
    } else {
        $pdo->prepare("UPDATE users SET wrong_attempts = ? WHERE telegram_id = ?")
            ->execute([$wrong, $telegramId]);
        exit('NO');
    }
}
