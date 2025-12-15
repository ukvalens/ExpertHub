<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: ../../../index.php");
    exit();
}

// Verify user has access to this order
$user_type = $_SESSION['user_type'];
if ($user_type === 'customer') {
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name FROM orders o JOIN service_providers sp ON o.provider_id = sp.id JOIN users u ON sp.user_id = u.id WHERE o.id = ? AND o.customer_id = ?");
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
} else {
    $stmt = $conn->prepare("SELECT sp.id FROM service_providers sp WHERE sp.user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.id = ? AND o.provider_id = ?");
    $stmt->bind_param("ii", $order_id, $provider['id']);
}

$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: ../../../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Call - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a1a; color: white; }
        .video-container { position: relative; width: 100%; height: 70vh; background: #000; border-radius: 10px; overflow: hidden; }
        .local-video { position: absolute; top: 10px; right: 10px; width: 200px; height: 150px; border-radius: 10px; z-index: 10; }
        .remote-video { width: 100%; height: 100%; object-fit: cover; }
        .controls { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); }
        .control-btn { margin: 0 10px; width: 60px; height: 60px; border-radius: 50%; border: none; font-size: 20px; }
        .status-indicator { position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.7); padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid p-3">
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-video me-2"></i>
                        Video Call - Order #<?php echo $order['order_number']; ?>
                    </h5>
                    <span class="badge bg-success">
                        <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="video-container">
                    <div class="status-indicator">
                        <span id="connectionStatus">Connecting...</span>
                    </div>
                    <video id="remoteVideo" class="remote-video" autoplay playsinline></video>
                    <video id="localVideo" class="local-video" autoplay muted playsinline></video>
                </div>
            </div>
        </div>

        <div class="controls">
            <button id="muteBtn" class="control-btn btn-secondary" onclick="toggleMute()">
                <i class="fas fa-microphone"></i>
            </button>
            <button id="videoBtn" class="control-btn btn-secondary" onclick="toggleVideo()">
                <i class="fas fa-video"></i>
            </button>
            <button class="control-btn btn-danger" onclick="endCall()">
                <i class="fas fa-phone-slash"></i>
            </button>
        </div>
    </div>

    <script>
        let localStream;
        let isAudioMuted = false;
        let isVideoMuted = false;

        const localVideo = document.getElementById('localVideo');
        const statusIndicator = document.getElementById('connectionStatus');

        async function initializeCall() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: true
                });
                
                localVideo.srcObject = localStream;
                statusIndicator.textContent = 'Connected';
                statusIndicator.className = 'status-indicator text-success';
                
                // Notify provider of incoming call
                if ('<?php echo $_SESSION['user_type']; ?>' === 'customer') {
                    fetch('../../api/start_call.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order_id: <?php echo $order_id; ?> })
                    });
                }
                
            } catch (error) {
                statusIndicator.textContent = 'Camera/Microphone access denied';
                statusIndicator.className = 'status-indicator text-danger';
            }
        }

        function toggleMute() {
            if (localStream) {
                const audioTrack = localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = !audioTrack.enabled;
                    isAudioMuted = !audioTrack.enabled;
                    
                    const muteBtn = document.getElementById('muteBtn');
                    const icon = muteBtn.querySelector('i');
                    
                    if (isAudioMuted) {
                        muteBtn.className = 'control-btn btn-danger';
                        icon.className = 'fas fa-microphone-slash';
                    } else {
                        muteBtn.className = 'control-btn btn-secondary';
                        icon.className = 'fas fa-microphone';
                    }
                }
            }
        }

        function toggleVideo() {
            if (localStream) {
                const videoTrack = localStream.getVideoTracks()[0];
                if (videoTrack) {
                    videoTrack.enabled = !videoTrack.enabled;
                    isVideoMuted = !videoTrack.enabled;
                    
                    const videoBtn = document.getElementById('videoBtn');
                    const icon = videoBtn.querySelector('i');
                    
                    if (isVideoMuted) {
                        videoBtn.className = 'control-btn btn-danger';
                        icon.className = 'fas fa-video-slash';
                    } else {
                        videoBtn.className = 'control-btn btn-secondary';
                        icon.className = 'fas fa-video';
                    }
                }
            }
        }

        function endCall() {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            
            const userType = '<?php echo $_SESSION['user_type']; ?>';
            const redirectPath = userType === 'customer' ? '../customer/messages.php' : '../provider/messages.php';
            window.location.href = redirectPath + '?order_id=<?php echo $order_id; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>';
        }

        window.addEventListener('load', initializeCall);
    </script>
</body>
</html>