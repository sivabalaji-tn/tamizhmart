<?php
/**
 * TamizhMart — Coupon Management (Owner Dashboard)
 * Shop owners can create/delete up to 2 coupons per shop.
 */
session_start();
require '../config/db.php';

$page_title    = 'Coupons';
$page_subtitle = 'Create discount codes for your customers';
$topbar_action_label   = 'New Coupon';
$topbar_action_icon    = 'plus-lg';
$topbar_action_onclick = "openCreateModal()";

require 'includes/sidebar.php';

$shop_id = (int)$_SESSION['shop_id'];
$success = $error = '';

// ── Active coupon count ───────────────────────────────────────
$active_count = (int)$conn->query(
    "SELECT COUNT(*) FROM coupons WHERE shop_id=$shop_id AND is_active=1"
)->fetch_row()[0];

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Create coupon ─────────────────────────────────────────
    if ($action === 'create') {
        // Re-fetch active count fresh (race protection)
        $fresh_active = (int)$conn->query(
            "SELECT COUNT(*) FROM coupons WHERE shop_id=$shop_id AND is_active=1"
        )->fetch_row()[0];

        if ($fresh_active >= 2) {
            $error = 'You can only have 2 active coupons at a time. Deactivate one to create a new one.';
        } else {
            $code      = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($_POST['code'] ?? '')));
            $type      = in_array($_POST['type'] ?? '', ['flat','percent']) ? $_POST['type'] : 'flat';
            $value     = abs(floatval($_POST['value'] ?? 0));
            $min_order = abs(floatval($_POST['min_order'] ?? 0));
            $max_uses  = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
            $expires   = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

            if (!$code)           $error = 'Coupon code cannot be empty.';
            elseif (strlen($code) < 3) $error = 'Coupon code must be at least 3 characters.';
            elseif ($value <= 0)  $error = 'Discount value must be greater than 0.';
            elseif ($type === 'percent' && $value > 100) $error = 'Percentage discount cannot exceed 100%.';
            else {
                // Check for duplicate code in this shop
                $dup = $conn->prepare("SELECT id FROM coupons WHERE shop_id=? AND code=? LIMIT 1");
                $dup->bind_param('is', $shop_id, $code);
                $dup->execute();
                if ($dup->get_result()->num_rows > 0) {
                    $error = "A coupon with the code \"{$code}\" already exists in your shop.";
                } else {
                    $ins = $conn->prepare(
                        "INSERT INTO coupons (shop_id, code, type, value, min_order, max_uses, expires_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $ins->bind_param('issddis', $shop_id, $code, $type, $value, $min_order, $max_uses, $expires);
                    if ($ins->execute()) {
                        $success = "Coupon \"{$code}\" created successfully!";
                        $active_count++;
                    } else {
                        $error = 'Could not create coupon. Please try again.';
                    }
                }
            }
        }
    }

    // ── Toggle active ─────────────────────────────────────────
    if ($action === 'toggle') {
        $cid       = (int)($_POST['coupon_id'] ?? 0);
        $new_state = (int)($_POST['new_state']  ?? 0);

        // If activating, enforce 2-coupon limit
        if ($new_state === 1) {
            $fresh_active = (int)$conn->query(
                "SELECT COUNT(*) FROM coupons WHERE shop_id=$shop_id AND is_active=1"
            )->fetch_row()[0];
            if ($fresh_active >= 2) {
                $error = 'You can only have 2 active coupons. Deactivate another one first.';
                goto render;
            }
        }

        $stmt = $conn->prepare(
            "UPDATE coupons SET is_active=? WHERE id=? AND shop_id=?"
        );
        $stmt->bind_param('iii', $new_state, $cid, $shop_id);
        $stmt->execute();
        $success = 'Coupon ' . ($new_state ? 'activated' : 'deactivated') . '.';
        $active_count = (int)$conn->query(
            "SELECT COUNT(*) FROM coupons WHERE shop_id=$shop_id AND is_active=1"
        )->fetch_row()[0];
    }

    // ── Delete coupon ─────────────────────────────────────────
    if ($action === 'delete') {
        $cid  = (int)($_POST['coupon_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM coupons WHERE id=? AND shop_id=?");
        $stmt->bind_param('ii', $cid, $shop_id);
        $stmt->execute();
        $success = 'Coupon deleted.';
        $active_count = (int)$conn->query(
            "SELECT COUNT(*) FROM coupons WHERE shop_id=$shop_id AND is_active=1"
        )->fetch_row()[0];
    }
}

render:
// ── Fetch all coupons ─────────────────────────────────────────
$coupons = $conn->query(
    "SELECT * FROM coupons WHERE shop_id=$shop_id ORDER BY created_at DESC"
);
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-flash alert-flash-error animate-in">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ── Coupon limit banner ────────────────────────────── -->
<?php if ($active_count >= 2): ?>
<div class="animate-in" style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:16px;">
    <i class="bi bi-exclamation-triangle-fill" style="color:#B45309;font-size:18px;flex-shrink:0;"></i>
    <div>
        <div style="font-weight:700;font-size:13.5px;color:#92400E;">Active coupon limit reached (2/2)</div>
        <div style="font-size:12.5px;color:#B45309;margin-top:2px;">Deactivate or delete an existing coupon to create a new one.</div>
    </div>
</div>
<?php else: ?>
<div class="animate-in" style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:16px;">
    <i class="bi bi-ticket-perforated-fill" style="color:#0369A1;font-size:18px;flex-shrink:0;"></i>
    <div>
        <div style="font-weight:700;font-size:13.5px;color:#0369A1;">
            <?= $active_count ?>/2 active coupons used
        </div>
        <div style="font-size:12.5px;color:#0284C7;margin-top:2px;">
            You can create <?= 2 - $active_count ?> more active coupon<?= (2 - $active_count) !== 1 ? 's' : '' ?>.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Coupons table ─────────────────────────────────── -->
<div class="card-glass animate-in d2" style="padding:0;overflow:hidden;">
    <?php if ($coupons->num_rows === 0): ?>
    <div class="empty-state">
        <i class="bi bi-ticket-perforated"></i>
        <h4>No Coupons Yet</h4>
        <p>Create your first discount code and share it with your customers.</p>
        <button class="btn-primary-custom" style="margin-top:16px;" onclick="openCreateModal()">
            <i class="bi bi-plus-lg"></i> Create First Coupon
        </button>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table-glass">
            <thead>
                <tr>
                    <th style="padding-left:20px;">Code</th>
                    <th>Discount</th>
                    <th>Min. Order</th>
                    <th>Usage</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th style="text-align:right;padding-right:20px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($c = $coupons->fetch_assoc()):
                    $today    = date('Y-m-d');
                    $expired  = $c['expires_at'] && $c['expires_at'] !== '0000-00-00' && $c['expires_at'] < $today;
                    $maxed    = $c['max_uses'] !== null && $c['used_count'] >= (int)$c['max_uses'];
                    $eff_active = $c['is_active'] && !$expired && !$maxed;
                ?>
                <tr>
                    <td style="padding-left:20px;">
                        <div style="font-family:monospace;font-weight:800;font-size:14px;letter-spacing:1px;color:var(--primary);">
                            <?= htmlspecialchars($c['code']) ?>
                        </div>
                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                            Created <?= date('M j, Y', strtotime($c['created_at'])) ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($c['type'] === 'percent'): ?>
                        <span style="font-weight:700;font-size:14px;color:var(--success-text);">
                            <?= number_format($c['value'], 0) ?>% OFF
                        </span>
                        <?php else: ?>
                        <span style="font-weight:700;font-size:14px;color:var(--success-text);">
                            &#8377;<?= number_format($c['value'], 2) ?> OFF
                        </span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-secondary);font-size:13px;">
                        <?= $c['min_order'] > 0 ? '&#8377;' . number_format($c['min_order'], 2) : '<span style="color:var(--text-muted);">None</span>' ?>
                    </td>
                    <td>
                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);">
                            <?= $c['used_count'] ?>
                        </span>
                        <span style="font-size:12px;color:var(--text-muted);">
                            / <?= $c['max_uses'] !== null ? $c['max_uses'] : '∞' ?>
                        </span>
                        <?php if ($maxed): ?>
                        <span style="display:inline-block;background:var(--danger-bg);color:var(--danger-text);border:1px solid var(--danger-border);padding:1px 7px;border-radius:4px;font-size:10.5px;font-weight:700;margin-left:4px;">MAXED</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;color:<?= $expired ? 'var(--danger-text)' : 'var(--text-secondary)' ?>;">
                        <?php if (!$c['expires_at'] || $c['expires_at'] === '0000-00-00'): ?>
                        <span style="color:var(--text-muted);">Never</span>
                        <?php else: ?>
                        <?= date('M j, Y', strtotime($c['expires_at'])) ?>
                        <?php if ($expired): ?> <span style="font-weight:700;">(Expired)</span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($eff_active): ?>
                        <span class="status-pill pill-active">Active</span>
                        <?php elseif ($expired): ?>
                        <span class="status-pill pill-cancelled">Expired</span>
                        <?php elseif ($maxed): ?>
                        <span class="status-pill pill-cancelled">Maxed Out</span>
                        <?php else: ?>
                        <span class="status-pill pill-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;padding-right:20px;">
                        <div style="display:flex;justify-content:flex-end;gap:6px;">
                            <!-- Toggle active -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="new_state" value="<?= $c['is_active'] ? 0 : 1 ?>">
                                <?php if ($c['is_active']): ?>
                                <button type="submit" class="btn-ghost-custom" style="padding:4px 10px;font-size:12px;"
                                    title="Deactivate coupon">
                                    <i class="bi bi-pause-circle"></i> Deactivate
                                </button>
                                <?php else: ?>
                                <button type="submit" class="btn-success-custom" style="padding:4px 10px;font-size:12px;"
                                    title="Activate coupon"
                                    <?= $active_count >= 2 ? 'disabled title="Active coupon limit reached"' : '' ?>>
                                    <i class="bi bi-play-circle"></i> Activate
                                </button>
                                <?php endif; ?>
                            </form>
                            <!-- Delete -->
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('Delete coupon <?= htmlspecialchars($c['code']) ?>? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn-danger-custom" style="padding:4px 10px;font-size:12px;">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Create Coupon Modal ────────────────────────────── -->
<div class="modal-backdrop-custom" id="createModal">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-ticket-perforated" style="color:var(--primary);margin-right:6px;"></i>New Coupon</div>
            <button class="modal-close" onclick="closeModal('createModal')"><i class="bi bi-x-lg"></i></button>
        </div>

        <?php if ($active_count >= 2): ?>
        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:14px 16px;font-size:13.5px;color:#92400E;font-weight:600;margin-bottom:16px;">
            <i class="bi bi-exclamation-triangle-fill" style="margin-right:6px;"></i>
            You've reached the limit of 2 active coupons. Please deactivate one first.
        </div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="create">

            <!-- Code -->
            <div style="margin-bottom:16px;">
                <label class="form-label-custom">Coupon Code <span style="color:#ef4444;">*</span></label>
                <input type="text" name="code" class="input-custom"
                    placeholder="e.g. SAVE50, DIWALI20"
                    maxlength="30"
                    pattern="[A-Za-z0-9]+"
                    required
                    oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')"
                    style="font-family:monospace;font-size:15px;letter-spacing:1.5px;font-weight:700;">
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:5px;">
                    Letters and numbers only. Automatically uppercased.
                </div>
            </div>

            <!-- Type + Value row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label class="form-label-custom">Discount Type <span style="color:#ef4444;">*</span></label>
                    <select name="type" id="discType" class="input-custom" onchange="toggleTypeHint()">
                        <option value="flat">Flat (₹ fixed amount)</option>
                        <option value="percent">Percentage (%)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-custom" id="valueLabel">Discount Amount (₹) <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="value" id="discValue" class="input-custom"
                        min="1" step="0.01" placeholder="0.00" required>
                    <div id="typeHint" style="font-size:11.5px;color:var(--text-muted);margin-top:4px;"></div>
                </div>
            </div>

            <!-- Min order + Max uses -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label class="form-label-custom">Minimum Order (₹)</label>
                    <input type="number" name="min_order" class="input-custom"
                        min="0" step="0.01" placeholder="0 = no minimum">
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Optional</div>
                </div>
                <div>
                    <label class="form-label-custom">Max Uses</label>
                    <input type="number" name="max_uses" class="input-custom"
                        min="1" step="1" placeholder="Leave blank = unlimited">
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Optional</div>
                </div>
            </div>

            <!-- Expiry date -->
            <div style="margin-bottom:24px;">
                <label class="form-label-custom">Expiry Date</label>
                <input type="date" name="expires_at" class="input-custom"
                    min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Optional — leave blank for no expiry</div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;">
                    <i class="bi bi-ticket-perforated-fill"></i> Create Coupon
                </button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('createModal')" style="padding:9px 16px;">Cancel</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>

<script>
function openCreateModal() {
    <?php if ($active_count >= 2): ?>
    // Show warning instead of opening form
    openModal('createModal');
    <?php else: ?>
    openModal('createModal');
    <?php endif; ?>
}

function toggleTypeHint() {
    const type  = document.getElementById('discType').value;
    const label = document.getElementById('valueLabel');
    const hint  = document.getElementById('typeHint');
    const input = document.getElementById('discValue');
    if (type === 'percent') {
        label.innerHTML = 'Discount % <span style="color:#ef4444;">*</span>';
        input.max = 100;
        input.placeholder = 'e.g. 10 for 10%';
        hint.textContent  = 'Enter 1–100. E.g. "10" = 10% off.';
    } else {
        label.innerHTML = 'Discount Amount (₹) <span style="color:#ef4444;">*</span>';
        input.removeAttribute('max');
        input.placeholder = '0.00';
        hint.textContent  = 'Fixed ₹ amount off the cart total.';
    }
}

// openModal / closeModal utilities (shared across owner pages)
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close on backdrop click
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('createModal');
});

// Auto-open modal if there was a create error so the user doesn't lose their work
<?php if ($error && str_contains($error, 'coupon')): ?>
// don't auto-open on limit/duplicate errors (just show the alert)
<?php endif; ?>
</script>
