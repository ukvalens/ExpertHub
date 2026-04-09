<?php
/**
 * Shared portfolio viewer — include with $view_provider_id set
 * Used by: customer/browse-services, admin/providers
 */
if (!isset($view_provider_id) || !isset($conn)) return;

$stmt = $conn->prepare("SELECT sp.portfolio, u.first_name, u.last_name
    FROM service_providers sp JOIN users u ON sp.user_id = u.id
    WHERE sp.id = ?");
$stmt->bind_param("i", $view_provider_id);
$stmt->execute();
$pdata     = $stmt->get_result()->fetch_assoc();
$portfolio = json_decode($pdata['portfolio'] ?? '[]', true) ?: [];
$pname     = htmlspecialchars(($pdata['first_name'] ?? '') . ' ' . ($pdata['last_name'] ?? ''));
?>

<?php if (empty($portfolio)): ?>
    <div class="text-center py-3">
        <i class="fas fa-images fa-2x text-muted mb-2"></i>
        <p class="text-muted small mb-0">No portfolio items yet.</p>
    </div>
<?php else: ?>
    <div class="row g-2">
        <?php foreach (array_reverse($portfolio) as $item): ?>
        <div class="col-6 col-md-4">
            <div class="card h-100" style="border-radius:8px;overflow:hidden;">
                <?php if (!empty($item['image'])): ?>
                    <img src="/ExpertHUB/<?php echo htmlspecialchars($item['image']); ?>"
                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                         style="width:100%;height:120px;object-fit:cover;">
                <?php else: ?>
                    <div style="width:100%;height:120px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:1.5rem;">
                        <i class="fas fa-image"></i>
                    </div>
                <?php endif; ?>
                <div class="p-2">
                    <div style="font-size:.82rem;font-weight:600;"><?php echo htmlspecialchars($item['title']); ?></div>
                    <?php if (!empty($item['description'])): ?>
                        <div style="font-size:.75rem;color:#777;"><?php echo htmlspecialchars(mb_strimwidth($item['description'], 0, 60, '...')); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['tags'])): ?>
                        <div class="mt-1">
                            <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                <span style="font-size:.68rem;background:#e8f4f8;color:#0077B6;border-radius:20px;padding:1px 7px;margin-right:2px;"><?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
