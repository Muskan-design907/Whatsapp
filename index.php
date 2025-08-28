<?php
// index.php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$me = (int)$_SESSION['user_id'];
 
// fetch my info
$stmt = $conn->prepare("SELECT id, name, username, avatar FROM users WHERE id = ?");
$stmt->bind_param('i', $me);
$stmt->execute();
$stmt->bind_result($uid, $name, $username, $avatar);
$stmt->fetch();
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>ChatClone — WhatsApp style</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#e9eef2}
.app{display:flex;height:100vh}
.sidebar{width:320px;background:#fff;border-right:1px solid #ddd;display:flex;flex-direction:column}
.header{padding:14px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #eee}
.header img{width:44px;height:44px;border-radius:50%}
.search{padding:10px;border-bottom:1px solid #f1f1f1}
.search input{width:100%;padding:10px;border-radius:20px;border:1px solid #eee}
.contacts{overflow:auto;flex:1}
.contact{display:flex;align-items:center;padding:12px;border-bottom:1px solid #f7f7f7;cursor:pointer}
.contact img{width:48px;height:48px;border-radius:50%}
.contact .meta{margin-left:10px}
.contact .meta strong{display:block}
.contact.active{background:#f6fdf6}
.main{flex:1;display:flex;flex-direction:column;background:#f0f2f5}
.chat-header{padding:12px;border-bottom:1px solid #e6e6e6;display:flex;align-items:center;gap:12px;background:#fff}
.chat-body{flex:1;overflow:auto;padding:18px;display:flex;flex-direction:column;gap:12px}
.msg{max-width:65%;padding:10px 12px;border-radius:10px;line-height:1.3}
.msg.sent{background:#dcf8c6;align-self:flex-end;border-bottom-right-radius:2px}
.msg.recv{background:#fff;align-self:flex-start;border-bottom-left-radius:2px}
.time{display:block;font-size:11px;color:#666;margin-top:6px;text-align:right}
.input-area{padding:10px;border-top:1px solid #ddd;background:#fff;display:flex;gap:8px;align-items:center}
.input-area textarea{flex:1;padding:10px;border-radius:8px;border:1px solid #eee;resize:none;height:46px}
.btn{padding:10px 12px;border-radius:8px;border:none;background:#25D366;color:#fff;cursor:pointer}
.img-preview{max-width:160px;border-radius:8px;margin-top:8px}
.small{font-size:12px;color:#666}
@media (max-width:720px){
  .sidebar{width:100%;height:40vh;position:relative;z-index:2}
  .main{height:60vh}
}
</style>
</head>
<body>
<div class="app">
  <div class="sidebar">
    <div class="header">
      <img src="<?=htmlspecialchars($avatar)?>" alt="me">
      <div>
        <strong><?=htmlspecialchars($name)?></strong>
        <div class="small"><?=htmlspecialchars($username)?></div>
      </div>
      <div style="margin-left:auto"><a href="logout.php" class="small">Logout</a></div>
    </div>
 
    <div class="search">
      <input id="search" placeholder="Search or start new chat">
    </div>
 
    <div class="contacts" id="contacts">
      <!-- contacts loaded by JS -->
    </div>
  </div>
 
  <div class="main">
    <div class="chat-header" id="chatHeader">
      <div id="chatWithInfo">Select a chat to start messaging</div>
    </div>
 
    <div class="chat-body" id="chatBody">
      <!-- messages will load here -->
    </div>
 
    <div class="input-area">
      <form id="sendForm" enctype="multipart/form-data" style="display:flex;gap:8px;width:100%;">
        <input type="hidden" name="receiver_id" id="receiver_id">
        <textarea id="message" name="message" placeholder="Type a message"></textarea>
        <input type="file" name="media" id="media" accept="image/*" style="display:none">
        <button type="button" id="attachBtn" class="btn" style="background:#f1f2f3;color:#222">📎</button>
        <button type="submit" class="btn">Send</button>
      </form>
    </div>
  </div>
</div>
 
<script>
const me = <?=json_encode($me)?>;
let activeChat = null;
let lastLoadAt = 0;
 
function escapeHtml(s){ return s ? s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;') : ''; }
 
async function loadContacts(q='') {
  try {
    const resp = await fetch('load.php?action=contacts&q=' + encodeURIComponent(q));
    if (!resp.ok) return;
    const data = await resp.json();
    const box = document.getElementById('contacts');
    box.innerHTML = '';
    data.forEach(u => {
      const div = document.createElement('div');
      div.className = 'contact' + (activeChat==u.id ? ' active' : '');
      div.innerHTML = `<img src="${escapeHtml(u.avatar)}"><div class="meta"><strong>${escapeHtml(u.name)}</strong><span class="small">${escapeHtml(u.last_message||'Tap to chat')}</span></div>`;
      div.onclick = () => { openChat(u.id, u.name, u.avatar); }
      box.appendChild(div);
    });
  } catch(e){ console.error(e); }
}
 
function openChat(id, name, avatar){
  activeChat = id;
  document.getElementById('receiver_id').value = id;
  document.getElementById('chatWithInfo').innerHTML = `<img src="${escapeHtml(avatar)}" style="width:36px;height:36px;border-radius:50%;vertical-align:middle;margin-right:8px"><strong>${escapeHtml(name)}</strong>`;
  fetch('load.php?action=mark_seen&with=' + id).catch(()=>{});
  loadMessages(true);
  loadContacts(document.getElementById('search').value);
}
 
async function loadMessages(scrollBottom=false) {
  if (!activeChat) return;
  try {
    const resp = await fetch('load.php?action=messages&with=' + activeChat + '&since=' + lastLoadAt);
    if (!resp.ok) return;
    const data = await resp.json();
    const chatBody = document.getElementById('chatBody');
    if (data.full) {
      chatBody.innerHTML = '';
      data.messages.forEach(m => appendMessageToDOM(m));
      if (scrollBottom) chatBody.scrollTop = chatBody.scrollHeight;
    } else {
      let appended=false;
      data.messages.forEach(m => { appendMessageToDOM(m); appended=true; });
      if (appended && scrollBottom) chatBody.scrollTop = chatBody.scrollHeight;
    }
    if (data.last_ts) lastLoadAt = data.last_ts;
  } catch(e){ console.error(e); }
}
 
function appendMessageToDOM(m) {
  if (document.querySelector(`[data-mid="${m.id}"]`)) return;
  const chatBody = document.getElementById('chatBody');
  const div = document.createElement('div');
  div.dataset.mid = m.id;
  div.className = 'msg ' + (m.sender_id==me ? 'sent' : 'recv');
  let html = '';
  if (m.media) {
    html += `<img src="${escapeHtml(m.media)}" class="img-preview"><br>`;
  }
  html += `<span>${escapeHtml(m.message || '')}</span>`;
  html += `<div class="time">${escapeHtml(m.created_at)} ${m.sender_id==me ? (m.status=='seen' ? '✓✓' : (m.status=='delivered' ? '✓✓' : '✓')) : ''}</div>`;
  div.innerHTML = html;
  chatBody.appendChild(div);
}
 
document.getElementById('sendForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const form = new FormData(this);
  if (!form.get('message') && !document.getElementById('media').files.length) return alert('Type a message or attach an image.');
  try {
    const res = await fetch('send.php', { method:'POST', body: form });
    const j = await res.json();
    if (j.success) {
      document.getElementById('message').value = '';
      document.getElementById('media').value = '';
      loadMessages(true);
      loadContacts(document.getElementById('search').value);
    } else {
      alert(j.error || 'Send failed');
    }
  } catch(e){ console.error(e); alert('Send failed'); }
});
 
document.getElementById('attachBtn').addEventListener('click', ()=> document.getElementById('media').click());
 
let sTimer;
document.getElementById('search').addEventListener('input', function(){
  clearTimeout(sTimer);
  sTimer = setTimeout(()=> loadContacts(this.value), 350);
});
 
// polling
setInterval(()=> {
  loadContacts(document.getElementById('search').value);
  loadMessages(false);
}, 2000);
 
loadContacts('');
</script>
</body>
</html>
 
