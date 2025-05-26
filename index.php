<?php
session_start();
require 'config.php';

/* ───── первый визит: даём JS вставить tg_user ───── */
if (!isset($_SESSION['tg_id']) && !isset($_GET['tg_user'])) {
    $segments   = [];
    $mainChance = 0;
    goto html_output;
}

/* ───── разбираем tg_user, приходящий из Telegram Web-App ───── */
if (!isset($_SESSION['tg_id'])) {
    $tgData = json_decode($_GET['tg_user'] ?? '', true);
    if (!$tgData || !isset($tgData['id'])) {
        echo '<h2 style="color:red;text-align:center;margin-top:50px;">⛔ Ошибка: Telegram ID не получен</h2>';
        exit;
    }
    $_SESSION['tg_id']   = (int)$tgData['id'];
    $_SESSION['tg_name'] = $tgData['username'] ?? null;          // ← username (может отсутствовать)
}
$telegramId = $_SESSION['tg_id'];
$username   = $_SESSION['tg_name'];

/* ───── проверяем пользователя ───── */
$stmt = $pdo->prepare("
        SELECT username, phone, banned_until
        FROM   users
        WHERE  telegram_id = ?
");
$stmt->execute([$telegramId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* нет записи? создаём */
if (!$user) {
    $pdo->prepare("INSERT INTO users (telegram_id,username,wrong_attempts)
                   VALUES (?,?,0)")
        ->execute([$telegramId,$username]);
    $user = ['phone'=>null,'banned_until'=>null,'username'=>$username];
}

/* блокировка */
if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
    echo '<h2 style="color:red;text-align:center;margin-top:50px;">🚫 Заблокирован до '
       . date('d.m.Y H:i', strtotime($user['banned_until'])) . '</h2>';
    exit;
}

/* username изменился? – обновляем */
if ($username && $username !== $user['username']) {
    $pdo->prepare("UPDATE users SET username=? WHERE telegram_id=?")
        ->execute([$username,$telegramId]);
}

/* 📴 телефона нет – показываем заглушку и выходим */
if (empty($user['phone'])) {
    $botLink = 'https://t.me/TestTrustDevBot';   // измените на @своего_бота
    echo '<!doctype html><meta charset="utf-8">
          <style>
            body{display:flex;align-items:center;justify-content:center;height:100vh;
                 font-family:sans-serif;text-align:center;background:#f6f6f6}
            a{display:inline-block;padding:12px 20px;background:#26a2ff;color:#fff;
              border-radius:8px;text-decoration:none;font-weight:bold}
          </style>
          <div>
            <h2>☎️ Подтвердите номер телефона</h2>
            <p>Нажмите кнопку ниже, отправьте свой<br> номер в Telegram-боте и вернитесь.</p>
            <a href="'.$botLink.'?start='.$telegramId.'">Отправить номер</a>
          </div>';
    exit;
}

/* ───── данные колеса ───── */
$segments   = $pdo->query("SELECT label, is_main FROM wheel_segments ORDER BY id LIMIT 8")
                  ->fetchAll(PDO::FETCH_ASSOC);
$mainChance = (int)($pdo->query("SELECT value FROM settings WHERE key_name='main_chance'")
                    ->fetchColumn() ?: 10);
$labels     = array_column($segments,'label');
while (count($labels) < 8) $labels[] = '—';

html_output:
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Fortune Wheel</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&display=swap" rel="stylesheet">
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
(function () {
  const tg = window.Telegram.WebApp;
  tg.expand();

  const user = tg.initDataUnsafe?.user;
  console.log('[TG WebApp] initDataUnsafe:', tg.initDataUnsafe);

  if (!user || !user.id) {
    console.warn('[TG WebApp] ❌ Не удалось получить Telegram ID');
    document.body.innerHTML = '<h2 style="color:red;text-align:center;margin-top:50px;">⛔ Запустите из Telegram</h2>';
    return;
  }

  const currentUrl = new URL(window.location.href);
  if (!currentUrl.searchParams.get('tg_user')) {
    const encoded = encodeURIComponent(JSON.stringify(user));
    console.log('[TG WebApp] 🔁 Перенаправление с tg_user:', encoded);
    currentUrl.searchParams.set('tg_user', JSON.stringify(user));
    window.location.href = currentUrl.toString();
  } else {
    console.log('[TG WebApp] ✅ tg_user уже есть в URL');
  }
})();
</script>


  <style>
    body {
      display: flex; height: 100vh; align-items: center; justify-content: center;
      overflow: hidden;
      background: repeating-conic-gradient(#ffdd00 0 18deg,#ffc300 0 36deg);
      font-family: 'Fredoka One', sans-serif;
    }
    .fortune-wheel, .wheel {
      position: relative; display: flex; align-items: center; justify-content: center;
    }
    .wheel {
      width: 250px; height: 250px; border-radius: 50%;
      background: conic-gradient(
         #00acc3 0    45deg, #79af3e  45deg 90deg,
         #fd8b00 90deg 135deg,#e53935 135deg 180deg,
         #465a65 180deg 225deg,#00abc1 225deg 270deg,
         #7db343 270deg 315deg,#f98b00 315deg 360deg);
      overflow: visible;
    }
    .back-wheel, .back-wheel:after {position:absolute; border-radius:50%}
    .back-wheel {
      width:270px;height:270px;background:#333;
      transform:translate(-50%,-50%);top:50%;left:50%;z-index:-1;
    }
    .back-wheel:after {
      content:"";width:260px;height:260px;border:5px dotted yellow;
      animation:light .5s linear infinite;
    }
    @keyframes light {
      0%{filter:hue-rotate(0)}50%{filter:hue-rotate(130deg)}100%{filter:hue-rotate(0)}
    }
    .holder {
      position: absolute;width:100px;height:100px;background:#333;overflow:hidden;
      top:220px;left:32%;z-index:-2;
    }
    .holder:before {
      content:"";position:absolute;width:270px;height:270px;
      background:rgba(0,0,0,.4);border-radius:50%;
      top:-218px;left:-85%;
    }
    .shadow {
      position:absolute;width:250px;height:30px;background:rgba(0,0,0,.3);
      border-radius:50%;top:320px;
    }
    .shadow:before {
      content:"";position:absolute;width:200px;height:30px;left:30px;top:-15px;
      background:#333;border-radius:100px 100px 0 0;
      box-shadow:inset 0 -10px rgba(0,0,0,.3);
    }
    .shadow:after {
      content:"";position:absolute;width:30px;height:30px;border:5px solid #333;
      background:yellow;border-radius:50%;
      box-shadow:inset -5px -5px rgba(0,0,0,.2);
      top:-215px;left:105px;
    }
    .arrow, .arrow:before {position:absolute;border-style:solid;width:0;height:0}
    .arrow {
      border-color:#333 transparent transparent transparent;
      border-width:50px 20px 0 20px;top:-15px;
    }
    .arrow:before {
      content:"";border-color:#9e2a2b transparent transparent transparent;
      border-width:38px 13px 0 13px;top:-46px;left:-13px;
    }
    .ring {
      position: absolute;width: 100%;height: 100%;
      border-radius: 100%;transform: rotate(-22.5deg);left: -30px;
    }
    .label {
      position:absolute;left:50%;top:50%;width:60px;text-align:center;
      transform-origin:0 0;font-size:16px;color:#fff;user-select:none;
      white-space: nowrap;overflow: hidden;text-overflow: ellipsis;
    }
    .label span {
      display:block;width:100%;padding:2px 0;
    }
    .label:nth-child(1){transform:rotate(0deg)   translateY(-95px) rotate(0deg);}
    .label:nth-child(2){transform:rotate(45deg)  translateY(-95px) rotate(-45deg);}
    .label:nth-child(3){transform:rotate(90deg)  translateY(-95px) rotate(-90deg);}
    .label:nth-child(4){transform:rotate(135deg) translateY(-95px) rotate(-135deg);}
    .label:nth-child(5){transform:rotate(180deg) translateY(-95px) rotate(-180deg);}
    .label:nth-child(6){transform:rotate(225deg) translateY(-95px) rotate(-225deg);}
    .label:nth-child(7){transform:rotate(270deg) translateY(-95px) rotate(-270deg);}
    .label:nth-child(8){transform:rotate(315deg) translateY(-95px) rotate(-315deg);}
    .spin {
      position:absolute;top:-80px;width:150px;height:50px;background:transparent;
      border:5px solid red;border-radius:50px;color:red;font-weight:900;font-size:30px;
      transition:.1s;cursor:pointer;
    }
    .spin:hover{background:red;color:yellow}
    .spin:active{width:200px;color:#affc41}
    .spin:disabled{opacity:.4;cursor:not-allowed}
    #modal {
      position:fixed;top:0;left:0;width:100%;height:100%;
      background:rgba(0,0,0,0.6);display:none;align-items:center;justify-content:center;
      z-index:9999;
    }
    #modal > div {
      background:linear-gradient(135deg,#ffffff,#f5f5f5);
      color:#111;padding:30px 40px;border-radius:20px;
      font-size:22px;text-align:center;
      box-shadow:0 10px 30px rgba(0,0,0,0.3);
      max-width:90%;width:300px;animation:pop .4s ease;
    }
    @keyframes pop {
      0%{transform:scale(0.5);opacity:0} 100%{transform:scale(1);opacity:1}
    }
	
	.code-btn {
	  position: absolute;
	  top: -140px;
	  background: linear-gradient(135deg, #ff4b1f, #ff9068);
	  color: white;
	  font-size: 18px;
	  font-weight: bold;
	  padding: 12px 24px;
	  border: none;
	  border-radius: 30px;
	  cursor: pointer;
	  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
	  transition: all 0.2s ease-in-out;
	}
	.code-btn:hover {
	  transform: scale(1.05);
	  background: linear-gradient(135deg, #ff6a3d, #ffb38a);
	}
	.code-btn:active {
	  transform: scale(0.95);
	}
	.promo-status {
	  position: absolute;
	  top: -120px; /* немного выше кнопки SPIN */
	  left: 50%;
	  transform: translateX(-50%);
	  background: #00c853;
	  color: #fff;
	  font-size: 16px;
	  font-weight: bold;
	  padding: 6px 16px;
	  border-radius: 30px;
	  white-space: nowrap;
	  box-shadow: 0 4px 10px rgba(0,0,0,0.2);
	  display: none;
	  z-index: 20;
	}
	.promo-status::before {
	  content: "✅ ";
	}
  </style>
  
<script>
  window.telegramUserId = <?= (int)($_SESSION['tg_id'] ?? 0) ?>;
</script>
</head>
<body>

<div class="fortune-wheel">
  <div id="wheel" class="wheel">
    <div class="ring">
      <?php foreach($labels as $txt): ?>
        <div class="label"><span><?= htmlspecialchars($txt) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="back-wheel"></div>
  <div class="holder"></div>
  <div class="shadow"></div>
  <div class="arrow"></div>

<button id="enter-code" class="code-btn">🎟️ Ввести промокод</button>

  <button id="spin" class="spin" style="display:none;">SPIN</button>
  <div id="promo-status" class="promo-status" style="display:none;">Промокод активирован</div>
</div>

<div id="modal">
  <div>
    <div style="font-size:40px;margin-bottom:10px;">🎊</div>
    <div id="modal-text" style="font-weight:bold;">Вы выиграли: ...</div>
    <button onclick="document.getElementById('modal').style.display='none'"
      style="margin-top:25px;padding:10px 20px;font-size:16px;
             background:#ff3b3b;color:#fff;border:none;border-radius:10px;cursor:pointer;">
      ОК
    </button>
  </div>
</div>

<div id="promo-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;
  background:rgba(0,0,0,0.6);display:none;align-items:center;justify-content:center;
  z-index:9999;">
  <div style="background:#fff;padding:30px 40px;border-radius:20px;text-align:center;
              box-shadow:0 10px 30px rgba(0,0,0,0.3);">
    <input id="promo-input" placeholder="Введите промокод"
      style="padding:10px;width:200px;font-size:16px;border-radius:10px;border:2px solid #333;">
    <br><br>
    <button id="submit-code"
      style="padding:10px 20px;border-radius:10px;background:red;color:#fff;border:none;font-size:16px;">
      Ввести
    </button>
    <button id="cancel-code"
      style="margin-left:10px;padding:10px 20px;border-radius:10px;background:gray;color:#fff;border:none;font-size:16px;">
      Отмена
    </button>
  </div>
</div>

<script>
	/* ---------- глобальные данные от PHP ---------- */
	window.telegramUserId = <?= (int)($_SESSION['tg_id'] ?? 0) ?>;
	const labels     = <?= json_encode($segments) ?>;
	const mainChance = <?= $mainChance ?>;

	/* ---------- DOM-узлы ---------- */
	const wheel      = document.getElementById('wheel');
	const button     = document.getElementById('spin');
	const modal      = document.getElementById('modal');
	const modalText  = document.getElementById('modal-text');

	const promoModal = document.getElementById('promo-modal');
	const promoInput = document.getElementById('promo-input');
	const submitCode = document.getElementById('submit-code');
	const enterBtn   = document.getElementById('enter-code');
	const statusBox  = document.getElementById('promo-status');

	/* ---------- служебные переменные ---------- */
	let current            = 0;      // накопленный угол колеса
	let spinning           = false;  // сейчас идёт прокрутка?
	window.lastSectorIndex = 0;      // индекс сектора-победителя
	window.activePromoId   = null;   // ID активного, ещё не сыгранного промокода

	/* === восстановление состояния промокода (после F5) === */
	const savedPromo = localStorage.getItem('activePromoId');
	if (savedPromo) {
		window.activePromoId   = savedPromo;   // спина ещё не было
		enterBtn.style.display = 'none';       // прячем «Ввести промокод»
		button.style.display   = 'inline-block';
		statusBox.textContent  = 'Промокод активирован';
		statusBox.style.display= 'block';
	}

  /* ---------- вращение ---------- */
  async function startSpin (sectorIndex) {
      window.lastSectorIndex = sectorIndex;

      spinning       = true;
      button.disabled = true;

      const spins  = 3 + Math.floor(Math.random() * 3);
      const offset = sectorIndex * 45 + 22.5;
      current     += spins * 360 + offset;

      wheel.style.transition = 'ease-out 1.5s';
      wheel.style.transform  = 'rotate(' + current + 'deg)';

      setTimeout(() => {
          const spans   = document.querySelectorAll('.label span');
          const winText = spans[sectorIndex].textContent || '???';

          modalText.textContent = '🎉 Вы выиграли: ' + winText;
          modal.style.display   = 'flex';

          /* логируем результат, если был активирован промокод */
          if (window.activePromoId) {
              fetch('log_win.php', {
                  method : 'POST',
                  headers: {'Content-Type':'application/x-www-form-urlencoded'},
                  body   : new URLSearchParams({
                               id   : window.activePromoId,
                               label: winText
                           })
              });
              window.activePromoId = null;  
				localStorage.removeItem('activePromoId');		
          }

          spinning        = false;
          button.disabled = false;
          button.style.display = 'none';
          enterBtn.style.display = 'inline-block';
          statusBox.style.display = 'none';
      }, 1500);
  }

  /* ---------- нажали SPIN ---------- */
  button.addEventListener('click', () => {
      if (spinning) return;

      const rand         = Math.floor(Math.random() * mainChance);
      const mainIndexes  = [], otherIndexes = [];

      labels.forEach((seg, i) => (seg.is_main ? mainIndexes : otherIndexes).push(i));

      const sector = (rand === 0 && mainIndexes.length)
          ? mainIndexes[Math.floor(Math.random() * mainIndexes.length)]
          : otherIndexes[Math.floor(Math.random() * otherIndexes.length)];

      startSpin(sector);
  });

  /* ---------- промокод ---------- */
  enterBtn.addEventListener('click', () => promoModal.style.display = 'flex');

  submitCode.addEventListener('click', async () => {
      const code = promoInput.value.trim();
      if (!code || spinning) return;

      const res  = await fetch('check_code.php', {
          method : 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body   : new URLSearchParams({
                     code : code,
                     tg_id: window.telegramUserId
                  })
      });

      const text             = await res.text();
      const [respStatus, id] = text.split(':');

      if (respStatus === 'OK') {
	      window.activePromoId = id;
	      localStorage.setItem('activePromoId', id);   // запомнил

          promoModal.style.display = 'none';
          enterBtn.style.display   = 'none';
          button.style.display     = 'inline-block';

          statusBox.textContent = 'Промокод активирован';
          statusBox.style.display = 'block';
      } else if (respStatus === 'BANNED') {
          alert('🚫 Вы заблокированы за 3 неправильных попытки. Попробуйте через 24 часа.');
          location.reload();
      } else {
          alert('❌ Промокод неверен или уже использован.');
      }
  });

  /* ---------- отмена ввода промокода ---------- */
  document.getElementById('cancel-code')
          .addEventListener('click', () => {
              promoModal.style.display = 'none';
              promoInput.value = '';
          });
</script>


</body>
</html>
