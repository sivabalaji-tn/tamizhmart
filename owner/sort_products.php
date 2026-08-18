<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
$page_title    = 'Sort Products';
$page_subtitle = 'Drag to reorder how products appear on your storefront';

$shop_id = $_SESSION['shop_id'] ?? 0;

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
    $ids = array_map('intval', explode(',', $_POST['order']));
    foreach ($ids as $pos => $pid) {
        $conn->query("UPDATE products SET sort_order=" . ($pos + 1) . " WHERE id=$pid AND shop_id=$shop_id");
    }
    echo json_encode(['success' => true]);
    exit;
}

require 'includes/sidebar.php';

$products = $conn->query("
    SELECT p.*, c.name as cat_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.shop_id = $shop_id
    ORDER BY p.sort_order ASC, p.created_at DESC
");
?>

<div class="card-glass animate-in" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="font-size:13px;color:var(--text-secondary);">
        Drag the <i class="bi bi-grip-vertical" style="color:var(--primary);"></i> handle to reorder product display priority. Changes save automatically.
    </div>
    <div id="saveStatus" style="font-size:12.5px;color:var(--text-muted);display:flex;align-items:center;gap:6px;font-weight:600;">
        <i class="bi bi-check-circle-fill" style="color:var(--success-text);"></i> All changes saved
    </div>
</div>

<div class="card-glass animate-in d1" style="padding:0;overflow:hidden;">
    <div id="sortList">
        <?php while ($p = $products->fetch_assoc()): ?>
        <div class="sort-row" data-id="<?= $p['id'] ?>"
            style="display:flex;align-items:center;gap:14px;padding:12px 18px;border-bottom:1px solid #F1F5F9;cursor:default;transition:background 0.15s ease-in-out;user-select:none;background:#FFFFFF;">

            <!-- Drag handle -->
            <div class="drag-handle" style="color:var(--text-muted);font-size:18px;cursor:grab;flex-shrink:0;padding:4px;">
                <i class="bi bi-grip-vertical"></i>
            </div>

            <!-- Image -->
            <div style="width:40px;height:40px;border-radius:6px;overflow:hidden;background:#F8FAFC;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid #CBD5E1;">
                <?php
                $prod_img_src = !empty($p['image_url']) ? htmlspecialchars($p['image_url'])
                    : (!empty($p['image']) ? (strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : '../assets/uploads/products/'.htmlspecialchars($p['image'])) : '');
                ?>
                <?php if ($prod_img_src): ?>
                <img src="<?= $prod_img_src ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <i class="bi bi-image" style="color:var(--text-muted);font-size:16px;"></i>
                <?php endif; ?>
            </div>

            <!-- Name & Category -->
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:13.5px;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['name']) ?></div>
                <?php if ($p['cat_name']): ?>
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:1px;"><?= htmlspecialchars($p['cat_name']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Price -->
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-weight:700;font-size:13.5px;color:var(--text-primary);">
                    &#8377;<?= number_format($p['discount_price'] ?: $p['price'], 2) ?>
                </div>
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:1px;">Stock: <?= $p['stock'] ?></div>
            </div>

            <!-- Active badge -->
            <div style="flex-shrink:0;">
                <?php if ($p['is_active']): ?>
                <span class="status-pill pill-active">Active</span>
                <?php else: ?>
                <span class="status-pill pill-inactive">Hidden</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const list       = document.getElementById('sortList');
const saveStatus = document.getElementById('saveStatus');
let saveTimer;

const sortable = Sortable.create(list, {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'sort-ghost',
    onEnd: function() {
        clearTimeout(saveTimer);
        saveStatus.innerHTML = '<i class="bi bi-hourglass-split" style="color:var(--warning-text);"></i> Saving order...';
        saveTimer = setTimeout(saveOrder, 500);
    }
});

function saveOrder() {
    const ids = [...list.querySelectorAll('.sort-row')].map(r => r.dataset.id).join(',');
    fetch('sort_products.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'order=' + ids
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            saveStatus.innerHTML = '<i class="bi bi-check-circle-fill" style="color:var(--success-text);"></i> All changes saved';
        }
    })
    .catch(() => {
        saveStatus.innerHTML = '<i class="bi bi-exclamation-circle-fill" style="color:var(--danger-text);"></i> Save failed';
    });
}
</script>

<style>
.sort-ghost { opacity: 0.5; background: #EFF6FF !important; border: 1px dashed #2563EB !important; }
.sort-row:hover { background: #F8FAFC !important; }
.drag-handle:hover { color: var(--primary) !important; }
</style>