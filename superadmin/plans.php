<?php
/**
 * TamizhMart Super Admin — Plans Management
 * Create, edit, delete subscription plans
 */
session_start();
require '../config/db.php';
if (empty($_SESSION['superadmin_id'])) { header('Location: login.php'); exit; }

$success = ''; $error = '';

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $id               = intval($_POST['id'] ?? 0);
        $name             = trim($_POST['name'] ?? '');
        $slug             = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($_POST['slug'] ?? '')));
        $price            = floatval($_POST['price'] ?? 0);
        $duration         = intval($_POST['duration_days'] ?? 30);
        $product_limit    = $_POST['product_limit'] === '' ? null : intval($_POST['product_limit']);
        $order_limit      = $_POST['order_limit']   === '' ? null : intval($_POST['order_limit']);
        $commission       = floatval($_POST['commission_rate'] ?? 0);
        $is_active        = isset($_POST['is_active']) ? 1 : 0;
        $sort_order       = intval($_POST['sort_order'] ?? 0);
        $features_raw     = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
        $features_json    = json_encode(array_values($features_raw));

        if (!$name || !$slug) {
            $error = 'Name and slug are required.';
        } else {
            if ($action === 'create') {
                $st = $conn->prepare("INSERT INTO plans (name,slug,price,duration_days,product_limit,order_limit,commission_rate,features,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('ssdiiidsii', $name,$slug,$price,$duration,$product_limit,$order_limit,$commission,$features_json,$is_active,$sort_order);
                $st->execute();
                $success = "Plan \"$name\" created.";
            } else {
                $st = $conn->prepare("UPDATE plans SET name=?,slug=?,price=?,duration_days=?,product_limit=?,order_limit=?,commission_rate=?,features=?,is_active=?,sort_order=? WHERE id=?");
                $st->bind_param('ssdiiidsiii', $name,$slug,$price,$duration,$product_limit,$order_limit,$commission,$features_json,$is_active,$sort_order,$id);
                $st->execute();
                $success = "Plan \"$name\" updated.";
            }
        }
    }

    if ($action === 'toggle') {
        $id  = intval($_POST['id']);
        $cur = intval($_POST['current']);
        $conn->query("UPDATE plans SET is_active=" . ($cur ? 0 : 1) . " WHERE id=$id");
        $success = "Plan status updated.";
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        // Check no active subscriptions on this plan
        $used = $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE plan_id=$id AND status IN ('trial','active','grace')")->fetch_row()[0];
        if ($used > 0) {
            $error = "Cannot delete — $used active subscription(s) on this plan.";
        } else {
            $conn->query("DELETE FROM plans WHERE id=$id");
            $success = "Plan deleted.";
        }
    }
}

// ── Fetch all plans ───────────────────────────────────────────
$plans = $conn->query("
    SELECT p.*,
        (SELECT COUNT(*) FROM shop_subscriptions ss WHERE ss.plan_id=p.id AND ss.status IN ('trial','active','grace')) AS active_count
    FROM plans p ORDER BY p.sort_order, p.id
")->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/includes/sidebar.php';
?>
<style>
.plan-card {
    background:var(--card-bg);
    border:1px solid var(--card-border);
    border-radius:var(--radius);
    padding:24px;
    margin-bottom:16px;
    transition:transform .15s;
}
.plan-card:hover { transform:translateY(-2px); }
.plan-badge {
    display:inline-flex;align-items:center;gap:5px;
    padding:3px 10px;border-radius:99px;
    font-size:11px;font-weight:700;
}
.badge-trial   { background:rgba(251,191,36,0.15);color:#d97706; }
.badge-elite   { background:rgba(139,92,246,0.15);color:#7c3aed; }
.badge-premium { background:rgba(16,185,129,0.15);color:#059669; }
.badge-custom  { background:rgba(99,102,241,0.1);color:#6366f1; }
.feature-pill {
    display:inline-flex;align-items:center;gap:4px;
    background:rgba(var(--accent-rgb,99,102,241),0.08);
    color:var(--accent);
    padding:2px 8px;border-radius:99px;
    font-size:11px;font-weight:500;
    margin:2px;
}
.plan-price {
    font-family:'Syne',sans-serif;
    font-weight:800;font-size:28px;
    color:var(--accent);
}
.plan-price span { font-size:13px;font-weight:400;color:var(--muted); }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Subscription Plans</h1>
        <p class="page-sub">Manage Trial, Elite and Premium plans</p>
    </div>
    <button class="btn-primary-custom" onclick="openModal('createModal')">
        <i class="bi bi-plus-lg"></i> New Plan
    </button>
</div>

<?php if ($success): ?>
<div class="alert-success animate-in"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error animate-in"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Plan cards -->
<div class="animate-in d1">
<?php foreach ($plans as $plan):
    $features = json_decode($plan['features'] ?? '[]', true) ?: [];
    $badge_cls = match($plan['slug']) {
        'trial'   => 'badge-trial',
        'elite'   => 'badge-elite',
        'premium' => 'badge-premium',
        default   => 'badge-custom'
    };
    $icon = match($plan['slug']) {
        'trial'   => 'hourglass-split',
        'elite'   => 'star-fill',
        'premium' => 'gem',
        default   => 'box'
    };
?>
<div class="plan-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <i class="bi bi-<?= $icon ?>" style="font-size:20px;color:var(--accent);"></i>
                <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:18px;"><?= htmlspecialchars($plan['name']) ?></div>
                <span class="plan-badge <?= $badge_cls ?>"><?= htmlspecialchars($plan['slug']) ?></span>
                <?php if (!$plan['is_active']): ?>
                <span class="plan-badge" style="background:rgba(239,68,68,0.1);color:#dc2626;">Inactive</span>
                <?php endif; ?>
            </div>

            <div class="plan-price">
                <?= $plan['price'] > 0 ? '₹' . number_format($plan['price'], 0) : 'Free' ?>
                <span>/ <?= $plan['duration_days'] ?> days</span>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:16px;margin:12px 0;font-size:13px;color:var(--muted);">
                <span><i class="bi bi-box-seam"></i> <?= $plan['product_limit'] ?? 'Unlimited' ?> products</span>
                <span><i class="bi bi-cart3"></i> <?= $plan['order_limit'] ?? 'Unlimited' ?> orders/mo</span>
                <span><i class="bi bi-percent"></i> <?= $plan['commission_rate'] ?>% commission</span>
                <span><i class="bi bi-people-fill"></i> <?= $plan['active_count'] ?> active shops</span>
            </div>

            <div style="margin-top:8px;">
                <?php foreach ($features as $f): ?>
                <span class="feature-pill"><i class="bi bi-check-lg"></i><?= htmlspecialchars($f) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;">
            <button class="btn-primary-custom" style="padding:8px 16px;font-size:13px;"
                onclick="editPlan(<?= htmlspecialchars(json_encode($plan)) ?>)">
                <i class="bi bi-pencil"></i> Edit
            </button>
            <form method="POST">
                <input type="hidden" name="action"  value="toggle">
                <input type="hidden" name="id"      value="<?= $plan['id'] ?>">
                <input type="hidden" name="current" value="<?= $plan['is_active'] ?>">
                <button class="btn-ghost-custom" style="width:100%;padding:7px 16px;font-size:13px;">
                    <i class="bi bi-<?= $plan['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                    <?= $plan['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
            <?php if ($plan['active_count'] == 0): ?>
            <form method="POST" onsubmit="return confirm('Delete this plan?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= $plan['id'] ?>">
                <button class="btn-danger-custom" style="width:100%;padding:7px 16px;font-size:13px;">
                    <i class="bi bi-trash3"></i> Delete
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ══ CREATE MODAL ══════════════════════════════════════════ -->
<div id="createModal" class="modal-overlay" style="display:none;">
<div class="modal-box" style="max-width:560px;">
    <div class="modal-header">
        <div class="modal-title"><i class="bi bi-plus-circle"></i> Create New Plan</div>
        <button onclick="closeModal('createModal')" class="modal-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="create">
    <div class="modal-body" style="display:grid;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label class="input-label">Plan Name *</label>
                <input type="text" name="name" class="input-custom" placeholder="e.g. Elite" required>
            </div>
            <div>
                <label class="input-label">Slug *</label>
                <input type="text" name="slug" class="input-custom" placeholder="e.g. elite" required>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label class="input-label">Price (₹/month)</label>
                <input type="number" name="price" class="input-custom" placeholder="3000" min="0" step="0.01">
            </div>
            <div>
                <label class="input-label">Duration (days)</label>
                <input type="number" name="duration_days" class="input-custom" value="30" min="1">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div>
                <label class="input-label">Product Limit</label>
                <input type="number" name="product_limit" class="input-custom" placeholder="blank = unlimited" min="0">
            </div>
            <div>
                <label class="input-label">Order Limit/mo</label>
                <input type="number" name="order_limit" class="input-custom" placeholder="blank = unlimited" min="0">
            </div>
            <div>
                <label class="input-label">Commission %</label>
                <input type="number" name="commission_rate" class="input-custom" value="0" min="0" max="50" step="0.5">
            </div>
        </div>
        <div>
            <label class="input-label">Features (one per line)</label>
            <textarea name="features" class="input-custom" rows="5" placeholder="100 products&#10;500 orders/month&#10;Email notifications&#10;Razorpay payments"></textarea>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <label class="input-label" style="margin:0;">Sort Order</label>
            <input type="number" name="sort_order" class="input-custom" value="0" style="width:80px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;margin-left:16px;">
                <input type="checkbox" name="is_active" value="1" checked style="accent-color:var(--accent);">
                <span style="font-size:13.5px;">Active</span>
            </label>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Create Plan</button>
        <button type="button" onclick="closeModal('createModal')" class="btn-ghost-custom">Cancel</button>
    </div>
    </form>
</div>
</div>

<!-- ══ EDIT MODAL ═══════════════════════════════════════════ -->
<div id="editModal" class="modal-overlay" style="display:none;">
<div class="modal-box" style="max-width:560px;">
    <div class="modal-header">
        <div class="modal-title"><i class="bi bi-pencil"></i> Edit Plan</div>
        <button onclick="closeModal('editModal')" class="modal-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id"     id="editId">
    <div class="modal-body" style="display:grid;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label class="input-label">Plan Name *</label>
                <input type="text" name="name" id="editName" class="input-custom" required>
            </div>
            <div>
                <label class="input-label">Slug *</label>
                <input type="text" name="slug" id="editSlug" class="input-custom" required>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label class="input-label">Price (₹/month)</label>
                <input type="number" name="price" id="editPrice" class="input-custom" min="0" step="0.01">
            </div>
            <div>
                <label class="input-label">Duration (days)</label>
                <input type="number" name="duration_days" id="editDuration" class="input-custom" min="1">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div>
                <label class="input-label">Product Limit</label>
                <input type="number" name="product_limit" id="editProdLimit" class="input-custom" placeholder="blank = unlimited" min="0">
            </div>
            <div>
                <label class="input-label">Order Limit/mo</label>
                <input type="number" name="order_limit" id="editOrdLimit" class="input-custom" placeholder="blank = unlimited" min="0">
            </div>
            <div>
                <label class="input-label">Commission %</label>
                <input type="number" name="commission_rate" id="editCommission" class="input-custom" min="0" max="50" step="0.5">
            </div>
        </div>
        <div>
            <label class="input-label">Features (one per line)</label>
            <textarea name="features" id="editFeatures" class="input-custom" rows="5"></textarea>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <label class="input-label" style="margin:0;">Sort Order</label>
            <input type="number" name="sort_order" id="editSort" class="input-custom" style="width:80px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;margin-left:16px;">
                <input type="checkbox" name="is_active" id="editActive" value="1" style="accent-color:var(--accent);">
                <span style="font-size:13.5px;">Active</span>
            </label>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Save Changes</button>
        <button type="button" onclick="closeModal('editModal')" class="btn-ghost-custom">Cancel</button>
    </div>
    </form>
</div>
</div>

<script>
function editPlan(p) {
    document.getElementById('editId').value         = p.id;
    document.getElementById('editName').value       = p.name;
    document.getElementById('editSlug').value       = p.slug;
    document.getElementById('editPrice').value      = p.price;
    document.getElementById('editDuration').value   = p.duration_days;
    document.getElementById('editProdLimit').value  = p.product_limit ?? '';
    document.getElementById('editOrdLimit').value   = p.order_limit   ?? '';
    document.getElementById('editCommission').value = p.commission_rate;
    document.getElementById('editSort').value       = p.sort_order;
    document.getElementById('editActive').checked   = p.is_active == 1;
    // Features
    const features = JSON.parse(p.features || '[]');
    document.getElementById('editFeatures').value   = features.join('\n');
    openModal('editModal');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
