<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require '../config/db.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { header('Location: orders.php'); exit; }

require 'includes/sidebar.php';

$shop_id    = $handler_shop_id;
$handler_id = (int)$_SESSION['handler_id'];

// ── Fetch order ────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
           s.name AS shop_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN shops s ON o.shop_id = s.id
    WHERE o.id = ? AND o.shop_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $order_id, $shop_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo '<div style="padding:40px;text-align:center;color:#EF4444;">Order not found or access denied.</div>';
    require 'includes/footer.php'; exit;
}

// ── Fetch activity log for this order ─────────────────────────
$logs = $conn->query("
    SELECT hal.*, sh.name AS handler_name
    FROM handler_activity_log hal
    JOIN shop_handlers sh ON hal.handler_id = sh.id
    WHERE hal.order_id = $order_id AND hal.shop_id = $shop_id
    ORDER BY hal.created_at ASC
");

// ── Check if OTP already sent (pending) ───────────────────────
$otp_row = null;
if ($order['status'] === 'out_for_delivery') {
    $otp_q = $conn->prepare("SELECT * FROM delivery_otps WHERE order_id=? AND verified_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $otp_q->bind_param('i', $order_id);
    $otp_q->execute();
    $otp_row = $otp_q->get_result()->fetch_assoc();
}

$num     = '#' . str_pad($order['shop_order_number'] ?? $order['id'], 4, '0', STR_PAD_LEFT);
$is_cod  = $order['payment_method'] === 'cod';
$status  = $order['status'];

// Status forward map for handler
$forward_map = [
    'pending'    => ['label' => 'Confirm Order',      'status' => 'confirmed',        'cls' => 'btn-success-custom', 'icon' => 'check-circle-fill'],
    'confirmed'  => ['label' => 'Start Processing',   'status' => 'processing',       'cls' => 'btn-primary-custom', 'icon' => 'gear-fill'],
    'processing' => ['label' => 'Dispatch (Out for Delivery)', 'status' => 'out_for_delivery', 'cls' => 'btn-orange-custom',  'icon' => 'truck'],
];

$page_title    = 'Order ' . $num;
$page_subtitle = ucfirst(str_replace('_', ' ', $status)) . ' · ' . date('M j, Y, g:i A', strtotime($order['created_at']));

$status_colors = ['pending'=>'#F59E0B','confirmed'=>'#10B981','processing'=>'#0EA5E9','out_for_delivery'=>'#7C3AED','delivered'=>'#047857','cancelled'=>'#EF4444'];
?>

<div style="display:grid;grid-template-columns:1fr;gap:16px;max-width:900px;">

    <!-- ── Breadcrumb ── -->
    <div class="animate-in" style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);">
        <a href="orders.php" style="color:var(--text-muted);text-decoration:none;display:flex;align-items:center;gap:4px;"><i class="bi bi-arrow-left"></i> Orders</a>
        <span>/</span>
        <span style="color:var(--text-primary);font-weight:600;"><?= $num ?></span>
    </div>

    <!-- ── Header card ── -->
    <div class="card-glass animate-in d1">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-weight:800;font-size:24px;color:var(--primary);letter-spacing:-0.5px;"><?= $num ?></div>
                <div style="font-size:13px;color:var(--text-muted);margin-top:3px;">
                    <?= date('l, F j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span class="status-pill pill-<?= $status ?>" style="font-size:13px;padding:5px 12px;">
                    <?= ucfirst(str_replace('_',' ',$status)) ?>
                </span>
                <a href="invoice_pdf.php?order_id=<?= $order_id ?>" target="_blank" class="btn-ghost-custom" style="padding:6px 12px;font-size:12.5px;">
                    <i class="bi bi-printer"></i> Print Invoice
                </a>
            </div>
        </div>
    </div>

    <!-- ── Two column grid on desktop ── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="animate-in d2 responsive-cols">

        <!-- Customer Info -->
        <div class="card-glass">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:14px;">
                <i class="bi bi-person-fill" style="margin-right:5px;color:var(--primary);"></i> Customer
            </div>
            <div style="font-weight:700;font-size:15px;color:var(--text-primary);margin-bottom:4px;"><?= htmlspecialchars($order['customer_name']) ?></div>
            <?php if ($order['customer_phone']): ?>
            <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>" style="display:flex;align-items:center;gap:6px;color:var(--primary);text-decoration:none;font-size:13.5px;margin-bottom:4px;">
                <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($order['customer_phone']) ?>
            </a>
            <?php endif; ?>
            <div style="font-size:12.5px;color:var(--text-muted);"><?= htmlspecialchars($order['customer_email']) ?></div>
        </div>

        <!-- Payment & Amount -->
        <div class="card-glass">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:14px;">
                <i class="bi bi-credit-card-fill" style="margin-right:5px;color:var(--primary);"></i> Payment
            </div>
            <div style="font-weight:800;font-size:26px;color:var(--text-primary);letter-spacing:-0.5px;">
                ₹<?= number_format($order['total_amount'], 2) ?>
            </div>
            <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                <?php if ($is_cod): ?>
                <span style="background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:700;">
                    <i class="bi bi-cash-coin"></i> Cash on Delivery
                </span>
                <?php else: ?>
                <span style="background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:700;">
                    <i class="bi bi-shield-check"></i> Online — Paid
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Delivery Address ── -->
    <div class="card-glass animate-in d2">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px;">
            <i class="bi bi-geo-alt-fill" style="margin-right:5px;color:var(--action-orange);"></i> Delivery Address
        </div>
        <div style="font-size:14px;line-height:1.65;color:var(--text-primary);"><?= nl2br(htmlspecialchars($order['address'])) ?></div>
        <?php if ($order['notes']): ?>
        <div style="margin-top:10px;padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;color:var(--text-secondary);">
            <i class="bi bi-sticky"></i> <strong>Note:</strong> <?= htmlspecialchars($order['notes']) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Pick List ── -->
    <div class="card-glass animate-in d3">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">
                <i class="bi bi-boxes" style="margin-right:5px;color:var(--primary);"></i> Items to Pack
            </div>
            <div id="pickProgress" style="font-size:12px;font-weight:600;color:var(--text-muted);"></div>
        </div>
        <div id="picklistItems" style="display:flex;flex-direction:column;gap:8px;">
            <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">
                <i class="bi bi-hourglass-split" style="font-size:20px;display:block;margin-bottom:6px;"></i>Loading items...
            </div>
        </div>
    </div>

    <!-- ── Action Panel ── -->
    <?php if (!in_array($status, ['delivered', 'cancelled'])): ?>
    <div class="card-glass animate-in d4" id="actionPanel">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:16px;">
            <i class="bi bi-lightning-fill" style="margin-right:5px;color:var(--action-orange);"></i> Quick Actions
        </div>

        <?php if (isset($forward_map[$status])): $next = $forward_map[$status]; ?>
        <!-- Forward status button -->
        <form method="POST" action="orders.php" style="margin-bottom:<?= $status === 'processing' ? '0' : '12px' ?>;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <input type="hidden" name="status" value="<?= $next['status'] ?>">
            <button type="submit" class="<?= $next['cls'] ?>" style="width:100%;justify-content:center;padding:12px;font-size:14px;" onclick="return confirm('Move order to: <?= $next['label'] ?>?')">
                <i class="bi bi-<?= $next['icon'] ?>"></i> <?= $next['label'] ?>
            </button>
        </form>
        <?php endif; ?>

        <!-- OTP Deliver section — only for out_for_delivery -->
        <?php if ($status === 'out_for_delivery'): ?>
        <div id="deliverSection" style="margin-top:0;">
            <div style="height:1px;background:var(--card-border);margin:16px 0;"></div>
            <div style="text-align:center;margin-bottom:16px;">
                <div style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:4px;">Ready to Deliver?</div>
                <div style="font-size:13px;color:var(--text-muted);">Send OTP to customer's email, then ask them for it at the door.</div>
            </div>

            <?php if ($otp_row): ?>
            <!-- OTP already sent — show entry form directly -->
            <div style="background:#F5F3FF;border:1px solid #DDD6FE;border-radius:8px;padding:14px 16px;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
                <i class="bi bi-envelope-check-fill" style="color:#7C3AED;font-size:18px;"></i>
                <div>
                    <div style="font-weight:600;font-size:13px;color:#4C1D95;">OTP already sent</div>
                    <div style="font-size:12px;color:#6D28D9;">Expires at <?= date('g:i A', strtotime($otp_row['expires_at'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Swipe-to-Deliver button (or standard button on desktop) -->
            <div id="sendOtpArea">
                <?php if (!$otp_row): ?>
                <!-- Swipe button -->
                <div class="swipe-container" id="swipeContainer">
                    <div class="swipe-track">
                        <div class="swipe-fill" id="swipeFill"></div>
                        <div class="swipe-thumb" id="swipeThumb">
                            <i class="bi bi-chevron-double-right"></i>
                        </div>
                        <div class="swipe-label" id="swipeLabel">Swipe to Send OTP & Deliver</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- OTP Entry area -->
            <div id="otpEntryArea" style="display:<?= $otp_row ? 'block' : 'none' ?>;">
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="bi bi-key-fill"></i> Enter OTP from Customer
                    </label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="otpInput" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                            placeholder="_ _ _ _ _ _"
                            style="flex:1;padding:14px 16px;border:2px solid #DDD6FE;border-radius:8px;font-size:22px;font-weight:800;letter-spacing:10px;text-align:center;font-family:monospace;outline:none;background:#F5F3FF;color:#1E293B;transition:border-color 0.15s;"
                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)"
                            onfocus="this.style.borderColor='#7C3AED'"
                            onblur="this.style.borderColor='#DDD6FE'">
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button onclick="verifyOtp()" id="verifyBtn" class="btn-handler-custom" style="flex:1;justify-content:center;padding:12px;font-size:14px;">
                        <i class="bi bi-shield-check"></i> Verify & Mark Delivered
                    </button>
                    <button onclick="resendOtp()" class="btn-ghost-custom" style="padding:12px 14px;" title="Resend OTP">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
                <div id="otpMessage" style="margin-top:10px;display:none;"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif ($status === 'delivered'): ?>
    <div class="card-glass animate-in d4" style="border-color:var(--success-border);background:var(--success-bg);">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:44px;height:44px;background:#FFFFFF;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--success);">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div style="font-weight:700;font-size:15px;color:var(--success-text);">Order Delivered Successfully</div>
                <div style="font-size:12.5px;color:#065F46;">
                    <?= $is_cod ? '₹' . number_format($order['total_amount'], 2) . ' COD collected · added to your wallet' : 'Online payment — no cash required' ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Activity Timeline ── -->
    <?php if ($logs->num_rows > 0): ?>
    <div class="card-glass animate-in d5">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:16px;">
            <i class="bi bi-clock-history" style="margin-right:5px;color:var(--primary);"></i> Activity Timeline
        </div>
        <div style="display:flex;flex-direction:column;gap:0;">
            <?php while ($log = $logs->fetch_assoc()):
                $icons = ['status_update'=>'arrow-right-circle','otp_sent'=>'envelope','otp_verified'=>'shield-check','cod_collected'=>'cash-coin'];
                $icon = $icons[$log['action']] ?? 'dot';
                $colors = ['status_update'=>'var(--primary)','otp_sent'=>'#7C3AED','otp_verified'=>'var(--success)','cod_collected'=>'#D97706'];
                $clr = $colors[$log['action']] ?? 'var(--text-muted)';
            ?>
            <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #F1F5F9;align-items:flex-start;">
                <div style="width:28px;height:28px;border-radius:50%;background:<?= $clr ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                    <i class="bi bi-<?= $icon ?>" style="color:<?= $clr ?>;font-size:13px;"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-primary);">
                        <?php if ($log['action'] === 'status_update'): ?>
                            Status: <?= ucfirst(str_replace('_',' ',$log['old_value'])) ?> → <?= ucfirst(str_replace('_',' ',$log['new_value'])) ?>
                        <?php elseif ($log['action'] === 'otp_sent'): ?>
                            OTP sent to customer
                        <?php elseif ($log['action'] === 'otp_verified'): ?>
                            OTP verified — Delivered
                        <?php elseif ($log['action'] === 'cod_collected'): ?>
                            COD collected: ₹<?= htmlspecialchars($log['new_value']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($log['action']) ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                        <?= htmlspecialchars($log['handler_name']) ?> · <?= date('M j, g:i A', strtotime($log['created_at'])) ?>
                        <?php if ($log['note']): ?> · <?= htmlspecialchars($log['note']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /grid -->

<style>
@media (max-width: 640px) {
    .responsive-cols { grid-template-columns: 1fr !important; }
}

/* Swipe-to-Deliver */
.swipe-container { margin-bottom: 14px; }
.swipe-track {
    position: relative;
    background: linear-gradient(90deg, #F5F3FF, #EDE9FE);
    border: 2px solid #DDD6FE;
    border-radius: 50px;
    height: 56px;
    overflow: hidden;
    cursor: pointer;
    user-select: none;
    -webkit-user-select: none;
}
.swipe-fill {
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 0; background: linear-gradient(90deg, #7C3AED, #6D28D9);
    border-radius: 50px; transition: width 0.05s linear;
}
.swipe-thumb {
    position: absolute; left: 4px; top: 4px;
    width: 44px; height: 44px;
    background: #7C3AED;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #FFFFFF; font-size: 18px;
    box-shadow: 0 2px 8px rgba(124,58,237,0.4);
    transition: left 0.05s linear, background 0.2s;
    cursor: grab;
    touch-action: none;
}
.swipe-thumb:active { cursor: grabbing; }
.swipe-label {
    position: absolute; left: 60px; right: 0; top: 0; bottom: 0;
    display: flex; align-items: center;
    font-size: 14px; font-weight: 700; color: #6D28D9;
    pointer-events: none; padding-right: 16px;
    transition: opacity 0.2s;
}
.swipe-track.swiped .swipe-thumb { background: #047857; }
</style>

<?php
// Inject order_id as a JS constant, then use nowdoc (no PHP interpolation) for the rest
echo '<script>const ORDER_ID = ' . (int)$order_id . ';</script>';
$extra_scripts = <<<'JS'
<script>

// ── Load pick list via AJAX ──────────────────────────────────
fetch(`includes/order_items_ajax.php?order_id=${ORDER_ID}`)
    .then(r => r.json())
    .then(items => {
        const container = document.getElementById('picklistItems');
        const progress  = document.getElementById('pickProgress');
        if (!container) return;
        if (!items.length) {
            container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">No items found.</div>';
            return;
        }
        const updateProg = () => {
            const checked = container.querySelectorAll('input[type=checkbox]:checked').length;
            progress.textContent = checked + ' / ' + items.length + ' picked';
            progress.style.color = checked === items.length ? 'var(--success-text)' : 'var(--text-muted)';
        };
        container.innerHTML = items.map((item, idx) => {
            const imgSrc = item.image_url
                ? item.image_url
                : (item.image ? (item.image.startsWith('http') ? item.image : `../assets/uploads/products/${item.image}`) : null);
            return `<label id="pr_${idx}" style="display:flex;align-items:center;gap:12px;padding:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;cursor:pointer;transition:all 0.15s;">
                <input type="checkbox" onchange="handlePick(this,${idx})" style="width:20px;height:20px;accent-color:var(--success);flex-shrink:0;cursor:pointer;">
                <div style="width:42px;height:42px;border-radius:7px;overflow:hidden;background:#FFFFFF;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #CBD5E1;">
                    ${imgSrc ? `<img src="${imgSrc}" style="width:100%;height:100%;object-fit:cover;">` : `<i class="bi bi-image" style="color:var(--text-muted);font-size:16px;"></i>`}
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13.5px;color:var(--text-primary);">${item.name}</div>
                    ${item.cat_name ? `<div style="font-size:12px;color:var(--text-muted);">${item.cat_name}</div>` : ''}
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div style="font-weight:700;font-size:13.5px;">₹${parseFloat(item.price).toLocaleString('en-IN',{minimumFractionDigits:2})}</div>
                    <div style="font-size:12px;color:var(--text-muted);">Qty: <strong>${item.quantity}</strong></div>
                </div>
                <div id="pb_${idx}" style="display:none;font-size:11px;font-weight:700;color:var(--success-text);background:var(--success-bg);border:1px solid var(--success-border);padding:2px 8px;border-radius:4px;flex-shrink:0;align-items:center;gap:4px;"><i class="bi bi-check2"></i> Picked</div>
            </label>`;
        }).join('');
        updateProg();
        items.forEach((_, idx) => document.getElementById(`pr_${idx}`)?.querySelector('input')?.addEventListener('change', updateProg));
    })
    .catch(() => { document.getElementById('picklistItems').innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger-text);">Failed to load items.</div>'; });

function handlePick(cb, idx) {
    const row = document.getElementById('pr_' + idx);
    const badge = document.getElementById('pb_' + idx);
    if (cb.checked) { row.style.borderColor = 'var(--success-border)'; row.style.background = '#F0FDF4'; badge.style.display = 'inline-flex'; }
    else { row.style.borderColor = '#E2E8F0'; row.style.background = '#F8FAFC'; badge.style.display = 'none'; }
}

// ── Swipe-to-Deliver logic ────────────────────────────────────
const track  = document.getElementById('swipeContainer');
const thumb  = document.getElementById('swipeThumb');
const fill   = document.getElementById('swipeFill');
const label  = document.getElementById('swipeLabel');

if (track && thumb) {
    let dragging = false, startX = 0, currentX = 0;
    const THRESHOLD = 0.75; // 75% swipe = trigger

    function getX(e) { return e.touches ? e.touches[0].clientX : e.clientX; }
    function onStart(e) { dragging = true; startX = getX(e); e.preventDefault(); }
    function onMove(e) {
        if (!dragging) return;
        const trackW = track.querySelector('.swipe-track').offsetWidth;
        const maxX   = trackW - 48 - 8;
        currentX = Math.max(0, Math.min(getX(e) - startX, maxX));
        thumb.style.left = (4 + currentX) + 'px';
        fill.style.width = (currentX + 48) + 'px';
        label.style.opacity = Math.max(0, 1 - currentX / (maxX * 0.5));
        if (currentX / maxX >= THRESHOLD) {
            thumb.style.background = '#047857';
            fill.style.background  = '#047857';
        } else {
            thumb.style.background = '#7C3AED';
            fill.style.background  = 'linear-gradient(90deg,#7C3AED,#6D28D9)';
        }
    }
    function onEnd(e) {
        if (!dragging) return;
        dragging = false;
        const trackW = track.querySelector('.swipe-track').offsetWidth;
        const maxX   = trackW - 48 - 8;
        if (currentX / maxX >= THRESHOLD) {
            // Trigger OTP send
            thumb.style.left = (4 + maxX) + 'px';
            fill.style.width = '100%';
            label.textContent = 'Sending OTP...';
            label.style.opacity = '1';
            label.style.color = '#FFFFFF';
            sendOtp();
        } else {
            // Snap back
            thumb.style.transition = 'left 0.3s ease';
            fill.style.transition  = 'width 0.3s ease';
            thumb.style.left = '4px';
            fill.style.width = '0';
            label.style.opacity = '1';
            setTimeout(() => { thumb.style.transition = ''; fill.style.transition = ''; }, 300);
        }
        currentX = 0;
    }

    thumb.addEventListener('mousedown',  onStart);
    thumb.addEventListener('touchstart', onStart, { passive: false });
    document.addEventListener('mousemove',  onMove);
    document.addEventListener('touchmove',  onMove, { passive: false });
    document.addEventListener('mouseup',  onEnd);
    document.addEventListener('touchend', onEnd);
}

// ── Send OTP ──────────────────────────────────────────────────
function sendOtp() {
    fetch('includes/send_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + ORDER_ID
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('sendOtpArea').style.display = 'none';
            document.getElementById('otpEntryArea').style.display = 'block';

            if (data.dev_otp) {
                // ── DEV / LOCALHOST MODE — show OTP directly ──
                showOtpMsg('🛠️ DEV MODE — Email skipped. OTP: ' + data.dev_otp, 'info');
                // Pre-fill the OTP input for faster testing
                const input = document.getElementById('otpInput');
                if (input) { input.value = data.dev_otp; input.focus(); }
            } else if (data.smtp_warning) {
                // ── SMTP failed on production ──
                showOtpMsg('⚠️ ' + data.smtp_warning, 'error');
                document.getElementById('otpInput')?.focus();
            } else {
                // ── Normal production flow ──
                showOtpMsg('📧 OTP sent to ' + data.masked_email + '. Ask the customer for it.', 'info');
                document.getElementById('otpInput')?.focus();
            }
        } else {
            alert('Failed to send OTP: ' + (data.error || 'Unknown error'));
            // Reset swipe
            if (document.getElementById('swipeThumb')) {
                document.getElementById('swipeThumb').style.left = '4px';
                document.getElementById('swipeFill').style.width = '0';
                document.getElementById('swipeLabel').textContent = 'Swipe to Send OTP & Deliver';
                document.getElementById('swipeLabel').style.opacity = '1';
                document.getElementById('swipeLabel').style.color = '#6D28D9';
            }
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}


// ── Verify OTP ────────────────────────────────────────────────
function verifyOtp() {
    const otp = (document.getElementById('otpInput')?.value || '').trim();
    if (otp.length < 6) { showOtpMsg('Please enter the full 6-digit OTP.', 'error'); return; }

    const btn = document.getElementById('verifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Verifying...';

    fetch('includes/verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + ORDER_ID + '&otp_code=' + encodeURIComponent(otp)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showOtpMsg(data.message, 'success');
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Delivered!';
            btn.style.background = '#047857';
            btn.style.borderColor = '#047857';
            // Refresh after 2.5 seconds
            setTimeout(() => location.reload(), 2500);
        } else {
            showOtpMsg(data.error || 'Invalid OTP', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-check"></i> Verify & Mark Delivered';
            document.getElementById('otpInput').value = '';
            document.getElementById('otpInput').focus();
        }
    })
    .catch(() => {
        showOtpMsg('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-shield-check"></i> Verify & Mark Delivered';
    });
}

// ── Resend OTP ────────────────────────────────────────────────
function resendOtp() {
    showOtpMsg('Resending OTP...', 'info');
    fetch('includes/send_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + ORDER_ID
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showOtpMsg('✅ OTP resent to ' + data.masked_email, 'success');
        else showOtpMsg('❌ ' + (data.error || 'Failed to resend'), 'error');
    });
}

function showOtpMsg(msg, type) {
    const el = document.getElementById('otpMessage');
    const colors = { success: 'var(--success-bg),var(--success-border),var(--success-text)', error: 'var(--danger-bg),var(--danger-border),var(--danger-text)', info: 'var(--info-bg),var(--info-border),var(--info-text)' };
    const [bg, border, color] = colors[type].split(',');
    el.style.cssText = `display:flex;align-items:flex-start;gap:8px;padding:10px 14px;border-radius:8px;font-size:13.5px;font-weight:500;background:${bg};border:1px solid ${border};color:${color};`;
    el.textContent = msg;
}

// Scroll to deliver section if hash present
if (window.location.hash === '#deliver') {
    setTimeout(() => document.getElementById('deliverSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
}

// OTP input — allow Enter key
document.getElementById('otpInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') verifyOtp(); });
</script>
JS;

require 'includes/footer.php';
?>
