<?php
// Настройки БД
$host = 'localhost';
$db   = 'test';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsnBase = "mysql:host=$host;charset=$charset";
$dsnDb   = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsnBase, $user, $pass, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo = new PDO($dsnDb, $user, $pass, $options);

    // Таблица секторов колеса
    $pdo->exec("CREATE TABLE IF NOT EXISTS wheel_segments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(255) NOT NULL,
        is_main BOOLEAN DEFAULT 0
    )");

    // Таблица промокодов
	$pdo->exec("CREATE TABLE IF NOT EXISTS promo_codes (
		id INT AUTO_INCREMENT PRIMARY KEY,
		code VARCHAR(20) UNIQUE NOT NULL,
		used TINYINT(1) DEFAULT 0,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		used_by BIGINT DEFAULT NULL,
		used_at DATETIME DEFAULT NULL,
		segment_label VARCHAR(255) DEFAULT NULL
	)");

    // Таблица настроек
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key_name VARCHAR(50) PRIMARY KEY,
        value VARCHAR(255)
    )");

    // Таблица пользователей
	$pdo->exec("
		CREATE TABLE IF NOT EXISTS users (
			telegram_id     BIGINT      PRIMARY KEY,
			username        VARCHAR(64) DEFAULT NULL,
			phone           VARCHAR(20) DEFAULT NULL,   -- ← номер телефона
			wrong_attempts  INT         DEFAULT 0,
			banned_until    DATETIME    DEFAULT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	");


    // Вставить значение по умолчанию
    $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES ('main_chance', '10')")->execute();

    // Наполнить wheel_segments при первом запуске
    $stmt = $pdo->query("SELECT COUNT(*) FROM wheel_segments");
    if ($stmt->fetchColumn() == 0) {
        $segments = ['💰 Деньги', '🎁 Подарок', '🎫 Билет', '🍀 Удача', '👑 Джекпот', '🍭 Бонус', '🎉 Сюрприз', '🙈 Пусто'];
        foreach ($segments as $label) {
            $is_main = ($label === '👑 Джекпот') ? 1 : 0;
            $pdo->prepare("INSERT INTO wheel_segments (label, is_main) VALUES (?, ?)")->execute([$label, $is_main]);
        }
    }

} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}
