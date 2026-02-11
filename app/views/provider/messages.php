<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php");
    exit();
}

// Get provider ID
$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

if (!$provider) {
    header("Location: ../../../login.php");
    exit();
}

$provider_id = $provider['id'];

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $order_id = $_POST['order_id'];
    $receiver_id = $_POST['receiver_id'];
    $message_content = $_POST['message_content'];
    
    $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_content, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiis", $order_id, $_SESSION['user_id'], $receiver_id, $message_content);
    $stmt->execute();
    
    // Send email notification to customer
    require_once '../../../config/email.php';
    $stmt = $conn->prepare("SELECT o.order_number, o.service_title, cu.email, cu.first_name, cu.last_name, pu.first_name as provider_name FROM orders o JOIN users cu ON o.customer_id = cu.id JOIN service_providers sp ON o.provider_id = sp.id JOIN users pu ON sp.user_id = pu.id WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_info = $stmt->get_result()->fetch_assoc();
    
    if ($order_info) {
        sendNewMessageNotification(
            $order_info['email'],
            $order_info['first_name'] . ' ' . $order_info['last_name'],
            $order_info['provider_name'],
            $order_info['service_title'],
            $message_content,
            $order_id
        );
    }
    
    header("Location: messages.php?order_id=$order_id&lang=" . ($_GET['lang'] ?? 'en'));
    exit();
}

// Get conversations (orders with messages)
$stmt = $conn->prepare("SELECT DISTINCT o.id, o.order_number, o.service_title, o.status,
                       u.first_name, u.last_name, o.customer_id,
                       (SELECT COUNT(*) FROM messages WHERE order_id = o.id AND receiver_id = ? AND is_read = 0) as unread_count,
                       (SELECT message_content FROM messages WHERE order_id = o.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages WHERE order_id = o.id ORDER BY created_at DESC LIMIT 1) as last_message_time
                       FROM orders o 
                       JOIN users u ON o.customer_id = u.id 
                       WHERE o.provider_id = ? AND o.status IN ('accepted', 'in_progress', 'completed')
                       ORDER BY CASE WHEN last_message_time IS NULL THEN o.created_at ELSE last_message_time END DESC");
$stmt->bind_param("ii", $_SESSION['user_id'], $provider_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get messages for selected conversation
$selected_order_id = $_GET['order_id'] ?? null;
$messages = [];
$selected_order = null;

if ($selected_order_id) {
    // Get order details
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name, o.customer_id 
                           FROM orders o 
                           JOIN users u ON o.customer_id = u.id 
                           WHERE o.id = ? AND o.provider_id = ?");
    $stmt->bind_param("ii", $selected_order_id, $provider_id);
    $stmt->execute();
    $selected_order = $stmt->get_result()->fetch_assoc();
    
    if ($selected_order) {
        // Get messages
        $stmt = $conn->prepare("SELECT m.*, u.first_name, u.last_name 
                               FROM messages m 
                               JOIN users u ON m.sender_id = u.id 
                               WHERE m.order_id = ? 
                               ORDER BY m.created_at ASC");
        $stmt->bind_param("i", $selected_order_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Mark messages as read
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE order_id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $selected_order_id, $_SESSION['user_id']);
        $stmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - ExpertHub Provider</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">
                <i class="fas fa-users-cog me-2"></i>ExpertHub
            </a>
            <div class="navbar-nav mx-auto">
                <a class="nav-link" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Dashboard</a>
                <a class="nav-link" href="my-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Orders</a>
                <a class="nav-link active" href="messages.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Messages</a>
                <a class="nav-link" href="support.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Support</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-tie me-1"></i>Provider
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-danger" href="../../../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Conversations List -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-comments me-2"></i>Customer Conversations</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($conversations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                <h6>No Active Conversations</h6>
                                <p class="text-muted px-3">Accept customer orders to start conversations.</p>
                                <small class="text-muted">New Orders → <strong>Accept</strong> → Chat Available</small>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($conversations as $conv): ?>
                                    <a href="messages.php?order_id=<?php echo $conv['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                                       class="list-group-item list-group-item-action <?php echo $selected_order_id == $conv['id'] ? 'active' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                        <span class="badge bg-danger ms-2"><?php echo $conv['unread_count']; ?></span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="mb-1 text-muted small">
                                                    <i class="fas fa-briefcase me-1"></i>
                                                    <?php echo htmlspecialchars($conv['service_title']); ?>
                                                </p>
                                                <?php if ($conv['last_message']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)) . '...'; ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $conv['last_message_time'] ? date('M j', strtotime($conv['last_message_time'])) : ''; ?>
                                            </small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="col-md-8">
                <div class="card h-100">
                    <?php if ($selected_order): ?>
                        <!-- Chat Header -->
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($selected_order['first_name'] . ' ' . $selected_order['last_name']); ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="fas fa-briefcase me-1"></i>
                                    Order #<?php echo $selected_order['order_number']; ?> - <?php echo htmlspecialchars($selected_order['service_title']); ?>
                                </small>
                            </div>
                            <div class="btn-group">
                                <a href="../shared/video-call.php?order_id=<?php echo $selected_order['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-video"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-success" onclick="toggleVoiceNote()" id="voiceBtn">
                                    <i class="fas fa-microphone"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <input type="file" id="fileInput" style="display: none;" onchange="uploadFile()">
                                <a href="contact-customer.php?order_id=<?php echo $selected_order['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="card-body" style="height: 400px; overflow-y: auto;" id="messagesContainer">
                            <?php if (empty($messages)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-comment fa-3x text-muted mb-3"></i>
                                    <h6>Start Conversation</h6>
                                    <p class="text-muted">Send your first message to the customer.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message mb-3 <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'text-end' : ''; ?>">
                                        <div class="d-inline-block p-2 rounded <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'bg-success text-white' : 'bg-light'; ?>" style="max-width: 70%;">
                                            <?php if (isset($message['message_type']) && $message['message_type'] === 'voice'): ?>
                                                <div class="voice-message">
                                                    <i class="fas fa-microphone me-2"></i>
                                                    <audio controls style="max-width: 200px;">
                                                        <source src="../../../uploads/voice_notes/<?php echo $message['file_path']; ?>" type="audio/wav">
                                                        Voice message
                                                    </audio>
                                                </div>
                                            <?php elseif (isset($message['message_type']) && $message['message_type'] === 'file'): ?>
                                                <div class="file-message">
                                                    <i class="fas fa-file me-2"></i>
                                                    <a href="/ExpertHUB/uploads/messages/<?php echo $message['file_path']; ?>" target="_blank" class="text-decoration-none">
                                                        <?php echo nl2br(htmlspecialchars($message['message_content'])); ?>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <?php echo nl2br(htmlspecialchars($message['message_content'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'You' : htmlspecialchars($message['first_name']); ?>
                                            • <?php echo date('M j, g:i A', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Voice Note Controls -->
                        <div class="card-footer border-top-0 bg-light" id="voiceControls" style="display: none;">
                            <div class="text-center">
                                <div class="mb-2">
                                    <span id="recordingTimer" class="badge bg-danger" style="display: none;">00:00</span>
                                </div>
                                <button class="btn btn-danger me-2" id="recordBtn" onclick="startRecording()">
                                    <i class="fas fa-circle"></i> Record
                                </button>
                                <button class="btn btn-success me-2" id="stopBtn" onclick="stopRecording()" disabled>
                                    <i class="fas fa-stop"></i> Stop
                                </button>
                                <button class="btn btn-primary" id="sendVoiceBtn" onclick="sendVoiceNote()" disabled>
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                                <div class="mt-2">
                                    <audio id="audioPlayback" controls style="display: none;"></audio>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Message Input -->
                        <div class="card-footer">
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $selected_order['id']; ?>">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_order['customer_id']; ?>">
                                <div class="d-flex align-items-end">
                                    <textarea class="form-control me-2" name="message_content" rows="2" placeholder="Type your message..." required style="resize: none;"></textarea>
                                    <button type="submit" name="send_message" class="btn btn-success">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                                <h5>Select a Customer</h5>
                                <p class="text-muted">Choose a conversation from the left to start messaging with your customers.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of messages
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Auto-refresh messages every 10 seconds
        <?php if ($selected_order_id): ?>
        setInterval(function() {
            location.reload();
        }, 10000);
        <?php endif; ?>
        
        // Check for incoming video calls
        function checkIncomingCalls() {
            fetch('../../api/check_calls.php')
                .then(response => response.json())
                .then(data => {
                    if (data.has_call) {
                        showIncomingCallModal(data);
                    }
                })
                .catch(error => console.log('Call check failed'));
        }
        
        function showIncomingCallModal(callData) {
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade show" style="display: block; background: rgba(0,0,0,0.8);">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5><i class="fas fa-video me-2"></i>Incoming Video Call</h5>
                            </div>
                            <div class="modal-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-user-circle fa-4x text-primary mb-3"></i>
                                    <h4>${callData.caller_name}</h4>
                                    <p class="text-muted">${callData.service_title}</p>
                                    <small>Order #${callData.order_number}</small>
                                </div>
                                <div class="d-flex justify-content-center gap-3">
                                    <button class="btn btn-success btn-lg" onclick="acceptCall(${callData.order_id})">
                                        <i class="fas fa-video me-2"></i>Accept
                                    </button>
                                    <button class="btn btn-danger btn-lg" onclick="declineCall()">
                                        <i class="fas fa-phone-slash me-2"></i>Decline
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        function acceptCall(orderId) {
            window.location.href = `../shared/video-call.php?order_id=${orderId}&lang=<?php echo $_GET['lang'] ?? 'en'; ?>`;
        }
        
        function declineCall() {
            document.querySelector('.modal').remove();
        }
        
        // Check for calls every 5 seconds
        setInterval(checkIncomingCalls, 5000);
        
        // Voice Note Functionality
        let mediaRecorder;
        let audioChunks = [];
        let recordedBlob;
        let recordingTimer;
        let recordingStartTime;
        let currentStream;
        
        function toggleVoiceNote() {
            const voiceControls = document.getElementById('voiceControls');
            const voiceBtn = document.getElementById('voiceBtn');
            
            if (voiceControls.style.display === 'none') {
                voiceControls.style.display = 'block';
                voiceBtn.classList.add('active');
                voiceBtn.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                voiceControls.style.display = 'none';
                voiceBtn.classList.remove('active');
                voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                resetRecording();
            }
        }
        
        function resetRecording() {
            if (recordingTimer) clearInterval(recordingTimer);
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
            }
            document.getElementById('recordingTimer').style.display = 'none';
            document.getElementById('audioPlayback').style.display = 'none';
            document.getElementById('recordBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('sendVoiceBtn').disabled = true;
            recordedBlob = null;
        }
        
        function updateTimer() {
            const elapsed = Date.now() - recordingStartTime;
            const minutes = Math.floor(elapsed / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            document.getElementById('recordingTimer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        async function startRecording() {
            try {
                currentStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(currentStream);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = event => {
                    if (event.data.size > 0) {
                        audioChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    recordedBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const audioUrl = URL.createObjectURL(recordedBlob);
                    const audioPlayback = document.getElementById('audioPlayback');
                    audioPlayback.src = audioUrl;
                    audioPlayback.style.display = 'block';
                    document.getElementById('sendVoiceBtn').disabled = false;
                    
                    // Stop timer
                    clearInterval(recordingTimer);
                    document.getElementById('recordingTimer').style.display = 'none';
                    
                    // Stop stream tracks
                    currentStream.getTracks().forEach(track => track.stop());
                };
                
                mediaRecorder.start();
                
                // Start timer
                recordingStartTime = Date.now();
                document.getElementById('recordingTimer').style.display = 'inline-block';
                recordingTimer = setInterval(updateTimer, 1000);
                
                document.getElementById('recordBtn').disabled = true;
                document.getElementById('stopBtn').disabled = false;
                
            } catch (error) {
                alert('Microphone access denied');
            }
        }
        
        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
                document.getElementById('recordBtn').disabled = false;
                document.getElementById('stopBtn').disabled = true;
            }
        }
        
        function sendVoiceNote() {
            if (recordedBlob) {
                const formData = new FormData();
                formData.append('voice_note', recordedBlob, 'voice_note.wav');
                formData.append('order_id', '<?php echo $selected_order['id']; ?>');
                
                document.getElementById('sendVoiceBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                document.getElementById('sendVoiceBtn').disabled = true;
                
                fetch('../../api/upload_voice_provider.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        toggleVoiceNote();
                        document.getElementById('audioPlayback').style.display = 'none';
                        location.reload();
                    } else {
                        alert('Failed to send voice note: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error sending voice note');
                })
                .finally(() => {
                    document.getElementById('sendVoiceBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                    document.getElementById('sendVoiceBtn').disabled = true;
                });
            }
        }
        
        // File Upload
        function uploadFile() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            
            if (!file) return;
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('order_id', '<?php echo $selected_order['id']; ?>');
            formData.append('receiver_id', '<?php echo $selected_order['customer_id']; ?>');
            
            fetch('../../api/upload_file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Upload failed: ' + data.message);
                }
            })
            .catch(() => alert('Upload error'));
        }
    </script>
</body>
</html>