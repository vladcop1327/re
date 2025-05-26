<?php
/********************************************************************
 * Webhook: https://trustdev.website/bot.php/<alias>
 * curl "https://api.telegram.org/bot<token>/setWebhook?url=https://trustdev.website/bot.php/main"
 ********************************************************************/

require 'config.php';                       // $pdo

/* ---------- настройки ---------- */
$BOTS = [
    'main' => [
        'token'     => '',
        'app_path'  => '/'            // корень сайта
    ],
    'shop' => [
        'token'     => 'TOKEN_2',
        'app_path'  => '/shop/'       // под-папка
    ],
];

/* домен без завершающего / */
define('SITE_ORIGIN', 'https://');

/* ---------- alias ---------- */
$alias = trim($_SERVER['PATH_INFO'] ?? '', '/');
if (!isset($BOTS[$alias])) exit('Unknown bot alias');

$TOKEN      = $BOTS[$alias]['token'];
$WEBAPP_URL = SITE_ORIGIN . $BOTS[$alias]['app_path'];   // ← готовая ссылка

/* ---------- update ---------- */
$update = json_decode(file_get_contents('php://input'), true);
if (!$update || !isset($update['message'])) exit('OK');

$msg   = $update['message'];
$chat  = $msg['chat']['id'];
$uid   = $msg['from']['id'];
$uname = $msg['from']['username'] ?? null;

/* ---------- helper ---------- */
function send($chat, $text, $kb = null){
    global $TOKEN;
    $data = ['chat_id'=>$chat,'text'=>$text,'parse_mode'=>'HTML'];
    if ($kb) $data['reply_markup'] = json_encode($kb);
    file_get_contents("https://api.telegram.org/bot$TOKEN/sendMessage?".http_build_query($data));
}

/* ---------- users ---------- */
$pdo->prepare("INSERT IGNORE INTO users (telegram_id,username,wrong_attempts)
               VALUES (?,?,0)")->execute([$uid,$uname]);

/* ---------- contact ---------- */
if (isset($msg['contact']) && $msg['contact']['user_id'] == $uid){
    $phone = $msg['contact']['phone_number'];
    $pdo->prepare("UPDATE users SET phone=? WHERE telegram_id=?")
        ->execute([$phone,$uid]);

    send($chat,'✅ Спасибо! Номер сохранён.',['remove_keyboard'=>true]);
    send($chat,'Откройте приложение 👇',[ 'inline_keyboard'=>[[[
        'text'=>'🎰 Открыть приложение',
        'web_app'=>['url'=>$WEBAPP_URL]
    ]]]]);
    exit('OK');
}

/* ---------- /start ---------- */
if (isset($msg['text']) && str_starts_with($msg['text'],'/start')){

    $hasPhone = $pdo->prepare("SELECT phone FROM users WHERE telegram_id=?");
    $hasPhone->execute([$uid]);
    $hasPhone = $hasPhone->fetchColumn();

    if ($hasPhone){
        send($chat,"Добро пожаловать, <b>@$uname</b>!",[ 'inline_keyboard'=>[[[
            'text'=>'🎰 Открыть приложение','web_app'=>['url'=>$WEBAPP_URL]
        ]]]]);
    }else{
        send($chat,
            "Перед игрой подтвердите номер телефона.\n"
          . "Нажмите кнопку и поделитесь контактом.",[
            'keyboard'=>[[[
                'text'=>'📱 Поделиться телефоном','request_contact'=>true
            ]]],
            'resize_keyboard'=>true,'one_time_keyboard'=>true
        ]);
    }
    exit('OK');
}

exit('OK');
