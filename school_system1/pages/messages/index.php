<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle = 'Мессеж';
$myId = $_SESSION['user_id'];

// Илгээх
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    verifyCsrf();
    $receiverId = (int)$_POST['receiver_id'];
    $content    = trim($_POST['content'] ?? '');
    if (!$receiverId || !$content) { setFlash('error','Хүлээн авагч болон мессежийн агуулга оруулна уу!'); }
    elseif ($receiverId === $myId) { setFlash('error','Өөртөө мессеж илгээх боломжгүй!'); }
    else {
        dbExec("INSERT INTO messages (sender_id,receiver_id,content) VALUES (?,?,?)", [$myId, $receiverId, $content]);
    }
    header('Location: /school_system1/pages/messages/index.php?usr=' . $receiverId); exit;
}

$activeUsr = (int)($_GET['usr'] ?? 0);

// Уншсан болгох if active user is set
if ($activeUsr) {
    dbUpdate("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?", [$myId, $activeUsr]);
}

// Contacts жагсаалт (Бүх хэрэглэгчид)
$users = dbQuery("SELECT user_id, full_name, role_name, last_activity FROM users u JOIN user_roles r ON u.role_id=r.role_id WHERE u.is_active=1 AND u.user_id!=? ORDER BY u.full_name", [$myId]);

$groupedUsers = [];
foreach($users as $u) {
    $groupedUsers[$u['role_name']][] = $u;
}
// Эрэмбэлэх: Удирдлага -> Багш -> Сурагч -> Эцэг эх
$roleOrder = ['Захирал' => 1, 'Менежер' => 2, 'Багш' => 3, 'Сурагч' => 4, 'Эцэг эх' => 5];
uksort($groupedUsers, function($a, $b) use ($roleOrder) {
    $oa = $roleOrder[$a] ?? 99;
    $ob = $roleOrder[$b] ?? 99;
    if ($oa === $ob) return strcmp($a, $b);
    return $oa <=> $ob;
});

// Unread counts grouped by sender
$unreadsData = dbQuery("SELECT sender_id, COUNT(*) as c FROM messages WHERE receiver_id=? AND is_read=0 GROUP BY sender_id", [$myId]);
$unreads = [];
foreach($unreadsData as $u) $unreads[$u['sender_id']] = $u['c'];

// Хэрэв activeUsr байгаа бол мессежүүдийг татах
$messages = [];
$activeUserRow = null;
if ($activeUsr) {
    $messages = dbQuery("SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY sent_at ASC", [$myId, $activeUsr, $activeUsr, $myId]);
    $activeUserRow = dbOne("SELECT full_name, role_name, last_activity FROM users u JOIN user_roles r ON u.role_id=r.role_id WHERE u.user_id=?", [$activeUsr]);
}

include __DIR__ . '/../../includes/header.php';
?>
<style>
.chat-container { display:flex; height:70vh; border:1px solid var(--border); border-radius:8px; overflow:hidden; background:var(--card-bg); }
.chat-sidebar { width:300px; border-right:1px solid var(--border); background:var(--bg); display:flex; flex-direction:column; }
.chat-sidebar-header { padding:15px; border-bottom:1px solid var(--border); background:var(--table-head); font-weight:bold; color:var(--text); }
.chat-list { flex:1; overflow-y:auto; }
.chat-item { display:flex; align-items:center; justify-content:space-between; padding:12px 15px; border-bottom:1px solid var(--border); cursor:pointer; color:inherit; text-decoration:none; transition:background .2s; }
.chat-item:hover { background:var(--hover-row); }
.chat-item.active { background:var(--hover-row); border-left:4px solid var(--primary); }
.chat-main { flex:1; display:flex; flex-direction:column; background:var(--bg); }
.chat-header { padding:15px; border-bottom:1px solid var(--border); background:var(--card-bg); display:flex; align-items:center; font-weight:bold; font-size:16px; color:var(--text); }
.chat-history { flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; }
.msg-bubble { max-width:70%; padding:10px 14px; border-radius:18px; font-size:14px; line-height:1.4; position:relative; word-wrap: break-word;}
.msg-incoming { align-self:flex-start; background:var(--card-bg); border:1px solid var(--border); border-bottom-left-radius:4px; color:var(--text); }
.msg-outgoing { align-self:flex-end; background:var(--primary); color:#fff; border-bottom-right-radius:4px; }
.msg-time { font-size:11px; margin-top:4px; opacity:0.7; text-align:right; }
.chat-input-area { padding:15px; background:var(--card-bg); border-top:1px solid var(--border); display:flex; gap:10px; }
.chat-input { flex:1; padding:10px 15px; border:1px solid var(--border); background:var(--input-bg); color:var(--text); border-radius:20px; outline:none; font-family:inherit; }
.online-dot { width:10px; height:10px; border-radius:50%; background:#10b981; display:inline-block; margin-right:5px; box-shadow:0 0 5px #10b981; }
</style>

<div class="chat-container">
  <!-- Зүүн тал: Хэрэглэгчид -->
  <div class="chat-sidebar">
    <div class="chat-sidebar-header">
      <div style="margin-bottom:10px; display:flex; align-items:center; gap:5px;"><i class="fas fa-users"></i> Харилцагчид</div>
      <input type="text" id="contactSearch" placeholder="Хайх..." style="width:100%; padding:6px 12px; border:1px solid var(--border); border-radius:15px; font-size:12px; outline:none; background:var(--bg); color:var(--text);" autocomplete="off">
    </div>
    <div class="chat-list">
      <?php foreach($groupedUsers as $roleName => $roleUsers): ?>
        <div class="role-group-title" style="padding:8px 15px; background:rgba(0,0,0,0.03); border-bottom:1px solid var(--border); border-top:1px solid var(--border); font-size:11px; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.5px;">
           <?= h($roleName) ?> <span style="color:var(--muted); font-weight:normal;">(<?= count($roleUsers) ?>)</span>
        </div>
        <?php foreach($roleUsers as $u):
            $ur = $unreads[$u['user_id']] ?? 0;
            $isOnline = (strtotime($u['last_activity'] ?? '') > (time() - 300));
        ?>
        <a href="?usr=<?= $u['user_id'] ?>" class="chat-item <?= $activeUsr === $u['user_id'] ? 'active' : '' ?>" data-search-name="<?= h(mb_strtolower($u['full_name'], 'UTF-8')) ?>">
          <div style="display:flex; align-items:center; gap:10px;">
            <div style="position:relative;">
               <i class="fas fa-user-circle" style="font-size:32px; color:var(--muted)"></i>
               <?php if($isOnline): ?><span class="online-dot" style="position:absolute; bottom:0; right:0; border:2px solid var(--bg); margin:0;"></span><?php endif; ?>
            </div>
            <div>
              <div style="font-weight:600;font-size:14px;color:var(--text)"><?= h($u['full_name']) ?></div>
            </div>
          </div>
          <?php if($ur): ?><span class="badge badge-danger"><?= $ur ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if(!$users): ?>
        <div style="padding:20px; text-align:center; color:var(--muted);">Харилцагч олдсонгүй</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Баруун тал: Чат -->
  <div class="chat-main">
    <?php if(!$activeUsr): ?>
      <div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--muted);flex-direction:column">
        <i class="far fa-comments" style="font-size:48px;margin-bottom:10px"></i>
        <p>Харилцах хүнээ сонгоно уу</p>
      </div>
    <?php else: ?>
      <div class="chat-header">
        <div style="position:relative; margin-right:10px;">
           <i class="fas fa-user-circle" style="font-size:32px; color:var(--muted)"></i>
           <?php if(strtotime($activeUserRow['last_activity'] ?? '') > (time() - 300)): ?>
             <span class="online-dot" style="position:absolute; bottom:2px; right:0; border:2px solid var(--card-bg); margin:0;"></span>
           <?php endif; ?>
        </div>
        <div>
          <?= h($activeUserRow['full_name']) ?>
          <div style="font-size:12px;font-weight:normal;color:var(--muted)">
            <?= h($activeUserRow['role_name']) ?>
            <?php if(strtotime($activeUserRow['last_activity'] ?? '') > (time() - 300)): ?>
               <span style="color:#10b981; font-weight:600; font-size:10px; margin-left:5px;">• ОНЛАЙН</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="chat-history" id="chatHistory">
        <?php if(!$messages): ?>
          <div style="text-align:center;color:var(--muted);margin-top:20px">Мессежийн түүх хоосон байна. Сонирхолтой чат эхлүүлээрэй!</div>
        <?php endif; ?>
        <?php foreach($messages as $m): 
            $isMe = $m['sender_id'] == $myId;
        ?>
          <div class="msg-bubble <?= $isMe ? 'msg-outgoing' : 'msg-incoming' ?>">
            <div><?= nl2br(h($m['content'])) ?></div>
            <div class="msg-time"><?= date('H:i', strtotime($m['sent_at'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <form id="chatForm" class="chat-input-area">
        <input type="hidden" name="csrf" id="chatCsrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="receiver_id" id="receiverId" value="<?= $activeUsr ?>">
        <input type="text" name="content" id="chatInput" class="chat-input" placeholder="Мессеж бичих..." required autocomplete="off" autofocus>
        <button type="submit" class="btn btn-primary" style="border-radius:20px;padding:0 20px"><i class="fas fa-paper-plane"></i></button>
      </form>
      <script>
        const chatHistory = document.getElementById('chatHistory');
        const chatForm = document.getElementById('chatForm');
        const chatInput = document.getElementById('chatInput');
        const receiverId = document.getElementById('receiverId').value;
        const csrfToken = document.getElementById('chatCsrf').value;
        let lastMsgId = <?= $messages ? end($messages)['message_id'] : 0 ?>;
        
        // Scroll to bottom
        chatHistory.scrollTop = chatHistory.scrollHeight;

        function appendMessage(msg) {
            const div = document.createElement('div');
            div.className = 'msg-bubble ' + (msg.isMe ? 'msg-outgoing' : 'msg-incoming');
            div.innerHTML = `<div>${msg.content}</div><div class="msg-time">${msg.time}</div>`;
            chatHistory.appendChild(div);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }

        // Poll for new messages (ухаалаг polling — tab focus-гүй үед зогсоох)
        let pollInterval = null;

        function pollMessages() {
            fetch(`/school_system1/api/fetch_messages.php?usr=${receiverId}&last_id=${lastMsgId}`)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success' && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            appendMessage(msg);
                            lastMsgId = msg.msg_id;
                        });
                    }
                }).catch(e => console.error(e));
        }

        function startPolling() {
            if (pollInterval) return;
            pollMessages(); // Шууд нэг удаа шалгах
            pollInterval = setInterval(pollMessages, 8000);
        }

        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }

        // Эхлүүлэх
        startPolling();

        // Tab харагдахгүй бол зогсоох, буцаж ирвэл дахин эхлүүлэх
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopPolling();
            } else {
                startPolling();
            }
        });

        // Send message via AJAX
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const content = chatInput.value.trim();
            if(!content) return;
            
            const formData = new FormData();
            formData.append('action', 'send');
            formData.append('csrf', csrfToken);
            formData.append('receiver_id', receiverId);
            formData.append('content', content);
            
            chatInput.value = ''; // clear input
            
            // Post to self
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(() => {
                // Instantly fetch to show the new message
                fetch(`/school_system1/api/fetch_messages.php?usr=${receiverId}&last_id=${lastMsgId}`)
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success' && d.messages.length > 0) {
                            d.messages.forEach(m => {
                                appendMessage(m);
                                lastMsgId = m.msg_id;
                            });
                        }
                    });
            }).catch(e => console.error(e));
        });
      </script>
    <?php endif; ?>
  </div>
</div>

<script>
// Харилцагч хайх (Live Search) - Үргэлж ажиллана
const contactSearch = document.getElementById('contactSearch');
if (contactSearch) {
    contactSearch.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(item => {
            const name = item.getAttribute('data-search-name') || '';
            if (name.includes(term)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Хоосон категорийг нуух
        document.querySelectorAll('.role-group-title').forEach(title => {
            let hasVisible = false;
            let next = title.nextElementSibling;
            while(next && next.classList.contains('chat-item')) {
                if (next.style.display !== 'none') hasVisible = true;
                next = next.nextElementSibling;
            }
            title.style.display = hasVisible ? 'block' : 'none';
        });
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

