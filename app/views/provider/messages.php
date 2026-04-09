<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    $oid  = isset($_GET['order_id']) ? '&order_id='.(int)$_GET['order_id'] : '';
    header("Location: ../dashboard/index.php?page=provider-messages&lang=$lang$oid"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
if (!$provider) { echo '<div class="alert alert-danger">Provider not found.</div>'; return; }
$provider_id = $provider['id'];

// AJAX: send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send') {
        $order_id   = (int)$_POST['order_id'];
        $receiver_id= (int)$_POST['receiver_id'];
        $content    = trim($_POST['content'] ?? '');
        if ($content && $order_id) {
            $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_type, message_content) VALUES (?, ?, ?, 'text', ?)");
            $stmt->bind_param("iiis", $order_id, $user_id, $receiver_id, $content);
            $stmt->execute();
        }
        echo json_encode(['ok' => true]); exit;
    }
    // AJAX: upload file
    if ($_POST['action'] === 'upload_file' && !empty($_FILES['file']['tmp_name'])) {
        $order_id    = (int)$_POST['order_id'];
        $receiver_id = (int)$_POST['receiver_id'];
        $ext         = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed     = ['jpg','jpeg','png','gif','pdf','doc','docx','txt','zip'];
        if (in_array($ext, $allowed) && $_FILES['file']['size'] <= 10*1024*1024) {
            $fname = uniqid().'_'.time().'.'.$ext;
            $dir   = '../../../uploads/messages/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dir.$fname)) {
                $content = 'Sent a file: '.$_FILES['file']['name'];
                $attach  = json_encode([['path'=>$fname,'name'=>$_FILES['file']['name'],'size'=>$_FILES['file']['size']]]);
                $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_type, message_content, file_attachments) VALUES (?, ?, ?, 'file', ?, ?)");
                $stmt->bind_param("iiiss", $order_id, $user_id, $receiver_id, $content, $attach);
                $stmt->execute();
            }
        }
        echo json_encode(['ok' => true]); exit;
    }
    // AJAX: poll new messages
    if ($_POST['action'] === 'poll') {
        $order_id  = (int)$_POST['order_id'];
        $after     = $_POST['after'] ?? '2000-01-01';
        $stmt = $conn->prepare("SELECT m.id, m.sender_id, m.message_type, m.message_content, m.file_attachments, m.created_at, u.first_name
            FROM messages m JOIN users u ON m.sender_id = u.id
            WHERE m.order_id = ? AND m.created_at > ?
            ORDER BY m.created_at ASC");
        $stmt->bind_param("is", $order_id, $after);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        // mark read
        $stmt = $conn->prepare("UPDATE messages SET is_read=1 WHERE order_id=? AND receiver_id=?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        echo json_encode($rows); exit;
    }
}

// Conversations
$stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status, o.customer_id,
    u.first_name, u.last_name, u.profile_image,
    (SELECT COUNT(*) FROM messages WHERE order_id=o.id AND receiver_id=? AND is_read=0) as unread,
    (SELECT message_content FROM messages WHERE order_id=o.id ORDER BY created_at DESC LIMIT 1) as last_msg,
    (SELECT created_at FROM messages WHERE order_id=o.id ORDER BY created_at DESC LIMIT 1) as last_time
    FROM orders o JOIN users u ON o.customer_id=u.id
    WHERE o.provider_id=? AND o.status IN ('accepted','in_progress','completed','requested')
    ORDER BY last_time DESC, o.created_at DESC");
$stmt->bind_param("ii", $user_id, $provider_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Selected conversation
$sel_id = (int)($_GET['order_id'] ?? 0);
$sel    = null;
$messages = [];

if ($sel_id) {
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name, u.id as customer_user_id
        FROM orders o JOIN users u ON o.customer_id=u.id
        WHERE o.id=? AND o.provider_id=?");
    $stmt->bind_param("ii", $sel_id, $provider_id);
    $stmt->execute();
    $sel = $stmt->get_result()->fetch_assoc();

    if ($sel) {
        $stmt = $conn->prepare("SELECT m.*, u.first_name FROM messages m JOIN users u ON m.sender_id=u.id
            WHERE m.order_id=? ORDER BY m.created_at ASC");
        $stmt->bind_param("i", $sel_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare("UPDATE messages SET is_read=1 WHERE order_id=? AND receiver_id=?");
        $stmt->bind_param("ii", $sel_id, $user_id);
        $stmt->execute();
    }
}
?>

<style>
.msg-layout { display:flex; gap:0; height:calc(100vh - 200px); min-height:400px; }
.msg-sidebar { width:280px; flex-shrink:0; border-right:1px solid #eee; overflow-y:auto; }
.msg-main { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.msg-body { flex:1; overflow-y:auto; padding:1rem; }
.msg-footer { padding:.75rem; border-top:1px solid #eee; background:#fff; }
.conv-item { padding:.6rem .75rem; border-bottom:1px solid #f5f5f5; cursor:pointer; transition:background .15s; }
.conv-item:hover, .conv-item.active { background:var(--light-bg); }
.conv-item .name { font-size:.83rem; font-weight:600; }
.conv-item .preview { font-size:.75rem; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bubble { max-width:70%; padding:.45rem .75rem; border-radius:12px; font-size:.84rem; word-break:break-word; }
.bubble.mine { background:var(--primary-color); color:#fff; border-bottom-right-radius:3px; }
.bubble.theirs { background:#f0f0f0; color:#333; border-bottom-left-radius:3px; }
.msg-time { font-size:.68rem; color:#aaa; margin-top:2px; }
@media(max-width:600px){ .msg-sidebar{width:100%;} .msg-layout{flex-direction:column;} }
</style>

<div class="content-card" style="overflow:hidden;">
    <div class="card-header"><i class="fas fa-comments me-2" style="color:var(--accent-color)"></i>Messages</div>
    <div class="msg-layout">

        <!-- Sidebar -->
        <div class="msg-sidebar">
            <?php if (empty($conversations)): ?>
                <div class="text-center p-3">
                    <i class="fas fa-comment-slash fa-2x text-muted mb-2"></i>
                    <p class="text-muted small">No conversations yet. Accept orders to start chatting.</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                <div class="conv-item <?php echo $sel_id == $c['id'] ? 'active' : ''; ?>"
                     onclick="selectConv(<?php echo $c['id']; ?>)">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="name">
                            <?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?>
                            <?php if ($c['unread'] > 0): ?>
                                <span class="badge bg-danger ms-1" style="font-size:.65rem;"><?php echo $c['unread']; ?></span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted" style="font-size:.68rem;">
                            <?php echo $c['last_time'] ? date('M j', strtotime($c['last_time'])) : ''; ?>
                        </small>
                    </div>
                    <div class="preview"><?php echo htmlspecialchars($c['service_title']); ?></div>
                    <?php if ($c['last_msg']): ?>
                        <div class="preview"><?php echo htmlspecialchars(mb_strimwidth($c['last_msg'], 0, 45, '...')); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Chat area -->
        <div class="msg-main">
            <?php if ($sel): ?>
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <div>
                        <strong style="font-size:.88rem;"><?php echo htmlspecialchars($sel['first_name'].' '.$sel['last_name']); ?></strong>
                        <small class="text-muted d-block" style="font-size:.75rem;">
                            #<?php echo $sel['order_number']; ?> · <?php echo htmlspecialchars($sel['service_title']); ?>
                        </small>
                    </div>
                    <a href="../shared/video-call.php?order_id=<?php echo $sel['id']; ?>&lang=<?php echo $lang; ?>"
                       class="btn btn-sm btn-outline-primary" title="Video Call">
                        <i class="fas fa-video"></i>
                    </a>
                </div>

                <!-- Messages -->
                <div class="msg-body" id="msgBody">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-4 text-muted small">No messages yet. Say hello!</div>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                        <div class="d-flex <?php echo $m['sender_id'] == $user_id ? 'justify-content-end' : 'justify-content-start'; ?> mb-2"
                             data-msg-id="<?php echo $m['id']; ?>">
                            <div>
                                <?php if ($m['message_type'] === 'file' && $m['file_attachments']): ?>
                                    <?php $att = json_decode($m['file_attachments'], true)[0] ?? null; ?>
                                    <div class="bubble <?php echo $m['sender_id'] == $user_id ? 'mine' : 'theirs'; ?>">
                                        <i class="fas fa-file me-1"></i>
                                        <?php if ($att): ?>
                                            <a href="/ExpertHUB/uploads/messages/<?php echo htmlspecialchars($att['path']); ?>"
                                               target="_blank" class="<?php echo $m['sender_id'] == $user_id ? 'text-white' : ''; ?>">
                                                <?php echo htmlspecialchars($att['name']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($m['message_content']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="bubble <?php echo $m['sender_id'] == $user_id ? 'mine' : 'theirs'; ?>">
                                        <?php echo nl2br(htmlspecialchars($m['message_content'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="msg-time <?php echo $m['sender_id'] == $user_id ? 'text-end' : ''; ?>">
                                    <?php echo $m['sender_id'] == $user_id ? 'You' : htmlspecialchars($m['first_name']); ?>
                                    · <?php echo date('g:i A', strtotime($m['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input -->
                <div class="msg-footer">
                    <div class="d-flex gap-2 align-items-end">
                        <textarea id="msgInput" class="form-control form-control-sm" rows="2"
                                  placeholder="Type a message..." style="resize:none;"></textarea>
                        <div class="d-flex flex-column gap-1">
                            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('fileUpload').click()" title="Attach file">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button class="btn btn-sm btn-success" id="sendBtn" onclick="sendMsg()">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    <input type="file" id="fileUpload" style="display:none" onchange="uploadFile(this)">
                </div>

            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100 text-center p-4">
                    <div>
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">Select a conversation</h6>
                        <p class="text-muted small">Choose a customer from the left to start messaging.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($sel): ?>
<script>
(function(){
    const orderId    = <?php echo $sel['id']; ?>;
    const receiverId = <?php echo $sel['customer_user_id']; ?>;
    const lang       = '<?php echo $lang; ?>';
    const url        = 'index.php?page=provider-messages&lang=' + lang + '&order_id=' + orderId;
    let lastTime     = '<?php echo !empty($messages) ? end($messages)['created_at'] : date('Y-m-d H:i:s', strtotime('-1 second')); ?>';

    const body = document.getElementById('msgBody');
    const input= document.getElementById('msgInput');

    function scrollBottom() { body.scrollTop = body.scrollHeight; }
    scrollBottom();

    function appendBubble(m) {
        const mine = m.sender_id == <?php echo $user_id; ?>;
        const div  = document.createElement('div');
        div.className = 'd-flex ' + (mine ? 'justify-content-end' : 'justify-content-start') + ' mb-2';
        div.dataset.msgId = m.id;
        let content = '';
        if (m.message_type === 'file' && m.file_attachments) {
            const att = JSON.parse(m.file_attachments)[0];
            content = `<i class="fas fa-file me-1"></i><a href="/ExpertHUB/uploads/messages/${att.path}" target="_blank" class="${mine?'text-white':''}">${att.name}</a>`;
        } else {
            content = m.message_content.replace(/\n/g,'<br>');
        }
        div.innerHTML = `<div><div class="bubble ${mine?'mine':'theirs'}">${content}</div>
            <div class="msg-time ${mine?'text-end':''}">${mine?'You':m.first_name} · ${new Date(m.created_at).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})}</div></div>`;
        body.appendChild(div);
        scrollBottom();
    }

    // Send message
    window.sendMsg = function() {
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        const data = new FormData();
        data.append('action','send');
        data.append('order_id', orderId);
        data.append('receiver_id', receiverId);
        data.append('content', text);
        fetch(url, { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'} });
    };

    input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); } });

    // File upload
    window.uploadFile = function(inp) {
        if (!inp.files[0]) return;
        const data = new FormData();
        data.append('action','upload_file');
        data.append('order_id', orderId);
        data.append('receiver_id', receiverId);
        data.append('file', inp.files[0]);
        fetch(url, { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'} });
        inp.value = '';
    };

    // Poll for new messages
    function poll() {
        const data = new FormData();
        data.append('action','poll');
        data.append('order_id', orderId);
        data.append('after', lastTime);
        fetch(url, { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(rows => {
                rows.forEach(m => {
                    if (!document.querySelector('[data-msg-id="'+m.id+'"]')) {
                        appendBubble(m);
                        lastTime = m.created_at;
                    }
                });
            });
    }
    setInterval(poll, 4000);
})();
</script>
<?php endif; ?>

<script>
window.selectConv = function(id) {
    if (typeof loadPage === 'function') {
        history.pushState({page:'provider-messages',extra:{}}, '', 'index.php?page=provider-messages&lang=<?php echo $lang; ?>&order_id='+id);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?page=provider-messages&lang=<?php echo $lang; ?>&order_id='+id, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    }
};
</script>
