<?php
require 'config.php';

/* ───── HTTP Basic ───── */
if (
    !isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'admin' ||
    !isset($_SERVER['PHP_AUTH_PW'])   || $_SERVER['PHP_AUTH_PW']   !== '1234'
){
    header('WWW-Authenticate: Basic realm="Admin Area"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Доступ запрещён');
}

/* ───── данные “Настройки” ───── */
$segRows     = $pdo->query("SELECT * FROM wheel_segments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$labels      = array_column($segRows,'label');
$main_index  = array_search(1,array_column($segRows,'is_main'));
$main_chance = (int)($pdo->query("SELECT value FROM settings WHERE key_name='main_chance'")->fetchColumn() ?? 10);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{
  --bg:#0d1117;--fg:#fff;--input:#161b22;--accent:#26a2ff;
  --danger:#ff4d4d;--success:#2ecc71;
}
body{margin:0;background:var(--bg);color:var(--fg);font-family:"Segoe UI",system-ui,sans-serif}
.section{display:none;padding:20px;max-width:750px;margin:auto}
.section.active{display:block}

.tabs{display:flex;background:#161b22;border-bottom:1px solid #222}
.tab{flex:1;text-align:center;padding:14px 0;cursor:pointer;
     font:500 16px/1 "Segoe UI",system-ui,sans-serif;border-bottom:3px solid transparent}
.tab.active{color:var(--accent);border-color:var(--accent)}
.tab i{font-style:normal}.tab span{margin-left:6px}
@media(max-width:480px){.tab span{display:none}}

input,select{width:100%;padding:8px 10px;margin:6px 0;border:none;border-radius:8px;
             background:var(--input);color:var(--fg);font-size:15px}
input[type=radio]{width:auto;margin:0 4px}
label{display:block;margin-top:10px;font-size:14px;color:#ccc}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:6px 0}
.row input[type=text]{flex:1}

button{background:var(--accent);color:#fff;border:none;border-radius:8px;
       padding:8px 14px;font-size:14px;cursor:pointer}
button:hover{background:#1a80d6}
button.small{padding:4px 8px;font-size:12px}

.code-list{background:var(--input);border-radius:10px;font-family:monospace;
           padding:15px;margin-top:10px}
.code-item{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;
           padding:10px;margin-bottom:10px;border-radius:8px;background:#1e232b;
           border-left:5px solid var(--accent);font-size:14px;line-height:1.4}
.used{color:var(--danger)}.ok{color:var(--success)}

#saved-msg{text-align:center;color:var(--success);font-size:14px;margin-top:8px;display:none}
#logsPaginator,#userPaginator,#codesPaginator{text-align:center;margin:15px 0}
</style>
</head>
<body>

<div class="tabs">
  <div class="tab active" data-tab="sectors"><i>⚙️</i><span>Настройки</span></div>
  <div class="tab"         data-tab="codes"  ><i>🎟</i><span>Промокоды</span></div>
  <div class="tab"         data-tab="users"  ><i>👥</i><span>Юзеры</span></div>
  <div class="tab"         data-tab="logs"   ><i>📜</i><span>Логи</span></div>
</div>

<!-- ───── НАСТРОЙКИ ───── -->
<div class="section active" id="sectors">
  <form id="segments-form">
    <?php foreach($labels as $i=>$label):?>
      <div class="row">
        <input type="text" name="segments[]" value="<?=htmlspecialchars($label)?>" placeholder="Сектор <?=$i+1?>">
        <input type="radio" name="main" value="<?=$i?>" <?=$i==$main_index?'checked':''?>>
      </div>
    <?php endforeach;?>
    <label>Шанс главного сектора:</label>
    <select name="main_chance">
      <?php foreach([10,20,30] as $v):?>
        <option value="<?=$v?>" <?=$v==$main_chance?'selected':''?>>1 к <?=$v?></option>
      <?php endforeach;?>
    </select>
    <button type="button" onclick="saveSegments()">💾 Сохранить</button>
    <div id="saved-msg">✔ Сохранено</div>
  </form>
</div>

<!-- ───── ПРОМОКОДЫ ───── -->
<div class="section" id="codes">
  <div class="row">
    <select id="codeFilter">
      <option value="all">Все</option>
      <option value="unused">Не использованные</option>
      <option value="used">Использованные</option>
    </select>
    <button onclick="loadCodes(1)">🔄 Обновить</button>
  </div>

  <form method="POST" action="gen_codes.php" style="margin-top:10px">
    <label>Сколько промокодов создать:</label>
    <div class="row">
      <input type="number" name="amount" value="10" min="1">
      <button type="submit">➕ Сгенерировать</button>
    </div>
  </form>

  <div id="codesContainer"  class="code-list">Загрузка…</div>
  <div id="codesPaginator"></div>
</div>

<!-- ───── ЮЗЕРЫ ───── -->
<div class="section" id="users">
  <div class="row">
    <input id="userSearch" type="text" placeholder="Поиск по TG-ID…">
    <button onclick="loadUsers(1)">🔍 Найти</button>
  </div>
  <div id="usersContainer" class="code-list">Загрузка…</div>
  <div id="userPaginator"></div>
</div>

<!-- ───── ЛОГИ ───── -->
<div class="section" id="logs">
  <div id="logsContainer" class="code-list">Загрузка…</div>
  <div id="logsPaginator"></div>
</div>

<script>
/* вкладки ----------------------------------------------------------------- */
document.querySelectorAll('.tab').forEach(tab=>{
  tab.addEventListener('click',()=>{
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById(tab.dataset.tab).classList.add('active');

    if(tab.dataset.tab==='codes') loadCodes(1);
    if(tab.dataset.tab==='users') loadUsers(1);
    if(tab.dataset.tab==='logs')  loadLogs(1);
  });
});

/* сохранить сектора ------------------------------------------------------- */
function saveSegments(){
  const seg=[...document.querySelectorAll('#segments-form input[type=text]')].map(i=>i.value.trim());
  const main=document.querySelector('#segments-form input[type=radio]:checked')?.value||0;
  const chance=document.querySelector('#segments-form select').value;

  fetch('save_segments.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({segments:seg,main,main_chance:chance})
  }).then(r=>r.json()).then(d=>{
    if(d.status==='ok'){
      const m=document.getElementById('saved-msg');
      m.style.display='block';setTimeout(()=>m.style.display='none',2000);
    }
  });
}

/* промокоды --------------------------------------------------------------- */
async function loadCodes(page=1){
  const filt=document.getElementById('codeFilter').value;
  const box =document.getElementById('codesContainer');
  box.textContent='Загрузка…';

  const res =await fetch(`api/codes.php?filter=${filt}&page=${page}`).then(r=>r.json());

  box.innerHTML=res.rows.map(c=>`
    <div class="code-item">
      <span>${c.code}</span>
      <span class="${c.used?'used':'ok'}">
        ${
          c.used
          ? `❌ (${c.used_by}${c.used_by_username?' @'+c.used_by_username:''}${c.used_by_phone?' 📞'+c.used_by_phone:''})`
          : '✅'
        }
      </span>
    </div>`).join('')||'—';

  const pag=document.getElementById('codesPaginator');
  pag.innerHTML='';
  if(page>1)      pag.innerHTML+=`<button class="small" onclick="loadCodes(${page-1})">← Пред</button>`;
  if(res.hasMore) pag.innerHTML+=`<button class="small" onclick="loadCodes(${page+1})">След →</button>`;
}
document.getElementById('codeFilter').addEventListener('change',()=>loadCodes(1));

/* логи -------------------------------------------------------------------- */
async function loadLogs(p=1){
  const box=document.getElementById('logsContainer');
  box.textContent='Загрузка…';
  const res=await fetch(`api/logs.php?page=${p}`).then(r=>r.json());

  box.innerHTML=res.rows.map(l=>`
    <div class="code-item">
      <div>
        <strong>Код:</strong> ${l.code}<br>
        <strong>Пользователь:</strong> ${l.used_by}
          ${l.used_by_username?' @'+l.used_by_username:''}
          ${l.used_by_phone    ?' 📞'+l.used_by_phone:''}<br>
        <strong>Время:</strong> ${l.used_at}
      </div>
      <div style="text-align:right">
        <strong>Выпало:</strong><br>
        <span style="color:#ffd700;font-weight:bold">${l.segment_label||'—'}</span>
      </div>
    </div>`).join('')||'—';

  const pag=document.getElementById('logsPaginator');
  pag.innerHTML='';
  if(p>1)        pag.innerHTML+=`<button class="small" onclick="loadLogs(${p-1})">← Пред</button>`;
  if(res.hasMore)pag.innerHTML+=`<button class="small" onclick="loadLogs(${p+1})">След →</button>`;
}

/* юзеры ------------------------------------------------------------------- */
async function loadUsers(p=1){
  const q  =document.getElementById('userSearch').value.trim();
  const box=document.getElementById('usersContainer');
  box.textContent='Загрузка…';

  const resp=await fetch(`api/users.php?page=${p}&q=${encodeURIComponent(q)}`);
  if(!resp.ok){box.textContent='⛔ Ошибка ('+resp.status+')';return;}
  const res =await resp.json();

  box.innerHTML=res.rows.map(u=>`
    <div class="code-item">
      <div>
        <strong>TG-ID:</strong> ${u.telegram_id}
          ${u.username?' @'+u.username:''}
          ${u.phone   ?' 📞'+u.phone:''}<br>
        Попыток: ${u.wrong_attempts}<br>
        Бан до: ${u.banned_until||'—'}
      </div>
      <div style="text-align:right">
        <button class="small" onclick="banUser(${u.telegram_id},'ban24')">24 ч</button>
        <button class="small" onclick="banUser(${u.telegram_id},'ban7')">7 дн</button>
        <button class="small" onclick="banUser(${u.telegram_id},'ban30')">30 дн</button>
        <button class="small" onclick="banUser(${u.telegram_id},'banForever')">∞</button>
        <button class="small" onclick="banUser(${u.telegram_id},'unban')">✔</button>
      </div>
    </div>`).join('')||'—';

  const pag=document.getElementById('userPaginator');
  pag.innerHTML='';
  if(p>1)        pag.innerHTML+=`<button class="small" onclick="loadUsers(${p-1})">← Пред</button>`;
  if(res.hasMore)pag.innerHTML+=`<button class="small" onclick="loadUsers(${p+1})">След →</button>`;
}

/* бан / разбан ------------------------------------------------------------ */
function banUser(id,type){
  if(!confirm('Подтвердите действие'))return;
  fetch('api/user_ban.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({id,type})
  }).then(()=>loadUsers(1));
}

/* первый запуск ----------------------------------------------------------- */
loadCodes(1);loadUsers(1);loadLogs(1);
</script>
</body>
</html>
