<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=help&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$lang = $_GET['lang'] ?? 'en';

$faqs = [
    'Getting Started' => [
        ['q' => 'How do I place an order?',
         'a' => 'Browse services, select a provider, fill out your requirements, and proceed to payment. The provider will be notified and can accept your request.'],
        ['q' => 'How do I find the right service provider?',
         'a' => 'Use Browse Services to filter by category, rating, and price. You can compare providers side-by-side and view their portfolios before ordering.'],
        ['q' => 'Can I request a custom service?',
         'a' => 'Yes! Use the Request Service feature to describe your needs. Providers can then send you a custom quote.'],
    ],
    'Orders & Payments' => [
        ['q' => 'What payment methods are accepted?',
         'a' => 'We accept Mobile Money (MTN MoMo, Airtel Money) for secure and convenient payments.'],
        ['q' => 'When is my payment released to the provider?',
         'a' => 'Payments are held in escrow and released only after you confirm the order is completed to your satisfaction.'],
        ['q' => 'How do I cancel an order?',
         'a' => 'You can cancel an order from My Orders while it is still in the "Requested" or "Quoted" status. Once accepted, contact support for assistance.'],
        ['q' => 'How do I get a refund?',
         'a' => 'If an order is cancelled or disputed, refunds are processed back to your wallet within 1–3 business days.'],
    ],
    'Communication' => [
        ['q' => 'How do I communicate with my provider?',
         'a' => 'Once your order is accepted, use the Messages section to chat, send files, voice notes, or start a video call.'],
        ['q' => 'Can I share files with my provider?',
         'a' => 'Yes. You can attach files directly in the chat or use the Shared Files section to manage documents related to your order.'],
    ],
    'Account & Profile' => [
        ['q' => 'How do I update my profile?',
         'a' => 'Go to My Profile from the top-right menu to update your name, photo, phone, and country.'],
        ['q' => 'How do I change my password?',
         'a' => 'Visit My Profile and use the Change Password section. You will need to enter your current password to confirm.'],
        ['q' => 'How do I add a device for tracking?',
         'a' => 'Go to My Devices and click "Add Device". You can track maintenance history per device across all your orders.'],
    ],
];
?>

<div class="content-card">
    <div class="card-header">
        <i class="fas fa-question-circle me-2" style="color:var(--accent-color)"></i>Help Center
    </div>
    <div class="card-body">

        <!-- Hero -->
        <div class="rounded p-4 mb-4 text-center" style="background:linear-gradient(135deg,var(--light-bg),#fff);">
            <i class="fas fa-life-ring fa-2x mb-2" style="color:var(--primary-color)"></i>
            <h5 class="fw-bold mb-1">How can we help you?</h5>
            <p class="text-muted small mb-3">Search our knowledge base or browse topics below</p>
            <div class="input-group mx-auto" style="max-width:420px;">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" class="form-control" id="faqSearch" placeholder="Search questions…">
            </div>
        </div>

        <!-- Quick actions -->
        <div class="row g-3 mb-4">
            <?php foreach ([
                ['icon'=>'fa-ticket-alt',    'color'=>'warning', 'title'=>'Support Tickets',  'desc'=>'Track & manage your tickets',   'page'=>'tickets'],
                ['icon'=>'fa-comments',      'color'=>'success', 'title'=>'Messages',          'desc'=>'Chat with your providers',      'page'=>'messages'],
                ['icon'=>'fa-headset',       'color'=>'primary', 'title'=>'Contact Support',   'desc'=>'Reach our support team',        'page'=>'support'],
            ] as $a): ?>
            <div class="col-md-4">
                <div class="card h-100 text-center border-<?php echo $a['color']; ?> quick-action-card"
                     style="cursor:pointer;transition:transform .15s;"
                     onclick="if(typeof loadPage==='function') loadPage('<?php echo $a['page']; ?>')">
                    <div class="card-body py-3">
                        <i class="fas <?php echo $a['icon']; ?> fa-2x text-<?php echo $a['color']; ?> mb-2"></i>
                        <div class="fw-semibold small"><?php echo $a['title']; ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?php echo $a['desc']; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- FAQ sections -->
        <div id="faqContainer">
            <?php $acc_idx = 0; foreach ($faqs as $section => $items): ?>
            <div class="mb-3 faq-section">
                <h6 class="fw-bold mb-2" style="color:var(--primary-color);">
                    <i class="fas fa-chevron-right me-1" style="font-size:.7rem;"></i><?php echo $section; ?>
                </h6>
                <div class="accordion" id="acc<?php echo $acc_idx; ?>">
                    <?php foreach ($items as $i => $faq):
                        $id = 'faq_' . $acc_idx . '_' . $i;
                    ?>
                    <div class="accordion-item faq-item border mb-1 rounded overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 small fw-semibold"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#<?php echo $id; ?>">
                                <?php echo htmlspecialchars($faq['q']); ?>
                            </button>
                        </h2>
                        <div id="<?php echo $id; ?>" class="accordion-collapse collapse"
                             data-bs-parent="#acc<?php echo $acc_idx; ?>">
                            <div class="accordion-body py-2 small text-muted">
                                <?php echo htmlspecialchars($faq['a']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $acc_idx++; endforeach; ?>
        </div>

        <!-- No results -->
        <div id="faqNoResults" class="text-center py-4 d-none">
            <i class="fas fa-search fa-2x text-muted mb-2"></i>
            <p class="text-muted small">No results found. Try different keywords or <a href="#" class="nav-link-ajax d-inline" data-page="support">contact support</a>.</p>
        </div>

        <!-- Still need help -->
        <div class="rounded p-3 mt-4 text-center" style="background:var(--light-bg);">
            <p class="mb-2 small fw-semibold">Still need help?</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <button class="btn btn-warning btn-sm nav-link-ajax" data-page="tickets">
                    <i class="fas fa-ticket-alt me-1"></i>Open a Ticket
                </button>
                <button class="btn btn-outline-primary btn-sm nav-link-ajax" data-page="messages">
                    <i class="fas fa-comments me-1"></i>Live Chat
                </button>
            </div>
        </div>

    </div>
</div>

<style>
.quick-action-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,119,182,.12); }
.accordion-button:not(.collapsed) { background: var(--light-bg); color: var(--primary-color); box-shadow: none; }
.accordion-button:focus { box-shadow: none; }
</style>

<script>
(function(){
    const input = document.getElementById('faqSearch');
    if (!input) return;

    input.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        const items  = document.querySelectorAll('.faq-item');
        const sections = document.querySelectorAll('.faq-section');
        let anyVisible = false;

        if (!q) {
            items.forEach(el => el.style.display = '');
            sections.forEach(el => el.style.display = '');
            document.getElementById('faqNoResults').classList.add('d-none');
            return;
        }

        sections.forEach(sec => {
            let secVisible = false;
            sec.querySelectorAll('.faq-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                const match = text.includes(q);
                item.style.display = match ? '' : 'none';
                if (match) { secVisible = true; anyVisible = true; }
            });
            sec.style.display = secVisible ? '' : 'none';
        });

        document.getElementById('faqNoResults').classList.toggle('d-none', anyVisible);
    });
})();
</script>
