<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require '../config/db.php';

$page_title    = 'Staff Handlers';
$page_subtitle = 'Create and manage your delivery handlers';

require 'includes/sidebar.php';

$shop_id  = (int)$_SESSION['shop_id'];
$owner_id = (int)$_SESSION['owner_id'];
$success = $error = '';

// ── Handle POST actions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Create handler ─────────────────────────────────────────
    if ($action === 'create_handler') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $phone    = trim($_POST['phone']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$name || !$email || !$password) {
            $error = 'Name, email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check email uniqueness
            $chk = $conn->prepare('SELECT id FROM shop_handlers WHERE email=?');
            $chk->bind_param('s', $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'A handler with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins  = $conn->prepare('INSERT INTO shop_handlers (shop_id, name, email, phone, password) VALUES (?,?,?,?,?)');
                $ins->bind_param('issss', $shop_id, $name, $email, $phone, $hash);
                if ($ins->execute()) {
                    $success = "Handler '{$name}' created successfully. They can now log in at /handler/login.php";
                } else {
                    $error = 'Failed to create handler. Please try again.';
                }
            }
        }
    }

    // ── Toggle active/inactive ─────────────────────────────────
    if ($action === 'toggle_active') {
        $hid    = (int)$_POST['handler_id'];
        $active = (int)$_POST['is_active'];
        $new    = $active ? 0 : 1;
        $upd = $conn->prepare('UPDATE shop_handlers SET is_active=? WHERE id=? AND shop_id=?');
        $upd->bind_param('iii', $new, $hid, $shop_id);
        $upd->execute();
        $success = 'Handler status updated.';
    }

    // ── Settle / Reset wallet ──────────────────────────────────
    if ($action === 'settle_wallet') {
        $hid    = (int)$_POST['handler_id'];
        $note   = trim($_POST['note'] ?? '');

        // Get current wallet
        $w_stmt = $conn->prepare('SELECT cod_wallet, name FROM shop_handlers WHERE id=? AND shop_id=?');
        $w_stmt->bind_param('ii', $hid, $shop_id);
        $w_stmt->execute();
        $h = $w_stmt->get_result()->fetch_assoc();

        if ($h && $h['cod_wallet'] > 0) {
            $amount = (float)$h['cod_wallet'];
            $conn->begin_transaction();
            try {
                // Reset wallet
                $rst = $conn->prepare('UPDATE shop_handlers SET cod_wallet=0 WHERE id=? AND shop_id=?');
                $rst->bind_param('ii', $hid, $shop_id);
                $rst->execute();


                // Log settlement
                $ins = $conn->prepare('INSERT INTO handler_cod_settlements (handler_id, shop_id, amount, settled_by, note) VALUES (?,?,?,?,?)');
                $ins->bind_param('iidis', $hid, $shop_id, $amount, $owner_id, $note);
                $ins->execute();

                $conn->commit();
                $success = "₹" . number_format($amount, 2) . " collected from {$h['name']} and wallet reset to ₹0.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Settlement failed. Please try again.';
            }
        } else {
            $error = 'Handler wallet is already empty.';
        }
    }

    // ── Delete handler ─────────────────────────────────────────
    if ($action === 'delete_handler') {
        $hid = (int)$_POST['handler_id'];
        // Only allow if wallet is 0
        $w = $conn->query("SELECT cod_wallet FROM shop_handlers WHERE id=$hid AND shop_id=$shop_id")->fetch_assoc();
        if ($w && $w['cod_wallet'] > 0) {
            $error = 'Cannot delete handler with unsettled COD balance. Settle first.';
        } else {
            $conn->prepare('DELETE FROM shop_handlers WHERE id=? AND shop_id=?')->bind_param('ii', $hid, $shop_id) ?: null;
            $del = $conn->prepare('DELETE FROM shop_handlers WHERE id=? AND shop_id=?');
            $del->bind_param('ii', $hid, $shop_id);
            $del->execute();
            $success = 'Handler removed successfully.';
        }
    }
}

// ── Fetch all handlers for this shop ──────────────────────────
$handlers = $conn->query("
    SELECT sh.*,
        (SELECT COUNT(*) FROM handler_activity_log WHERE handler_id=sh.id AND action='cod_collected') AS total_cod_deliveries,
        (SELECT COUNT(*) FROM handler_activity_log WHERE handler_id=sh.id AND action='otp_verified') AS total_deliveries
    FROM shop_handlers sh
    WHERE sh.shop_id = $shop_id
    ORDER BY sh.created_at DESC
");
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── Header & Create button ── -->
<div class="section-head animate-in">
    <div>
        <div class="section-title">Delivery Staff</div>
        <div class="section-sub">Handlers log in at <strong>/handler/login.php</strong> using their credentials</div>
    </div>
    <button onclick="openModal('createModal')" class="btn-primary-custom">
        <i class="bi bi-person-plus-fill"></i> Add Handler
    </button>
</div>

<!-- ── Handler Cards ── -->
<?php if ($handlers->num_rows === 0): ?>
<div class="card-glass animate-in d1">
    <div class="empty-state" style="padding:48px;">
        <i class="bi bi-person-badge"></i>
        <h4>No Handlers Yet</h4>
        <p>Create your first delivery handler to delegate order fulfilment.</p>
        <button onclick="openModal('createModal')" class="btn-primary-custom" style="margin-top:16px;">
            <i class="bi bi-person-plus-fill"></i> Create First Handler
        </button>
    </div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px;">
<?php while ($h = $handlers->fetch_assoc()): ?>
<div class="card-glass animate-in d1">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">

        <!-- Avatar + Info -->
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;border-radius:12px;background:<?= $h['is_active'] ? 'linear-gradient(135deg,#7C3AED,#5B21B6)' : '#E2E8F0' ?>;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:<?= $h['is_active'] ? '#FFFFFF' : '#94A3B8' ?>;flex-shrink:0;">
                <?= strtoupper(substr($h['name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight:700;font-size:15.5px;color:var(--text-primary);display:flex;align-items:center;gap:8px;">
                    <?= htmlspecialchars($h['name']) ?>
                    <?php if ($h['is_active']): ?>
                    <span style="font-size:11px;font-weight:600;background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;padding:2px 8px;border-radius:4px;">Active</span>
                    <?php else: ?>
                    <span style="font-size:11px;font-weight:600;background:#F1F5F9;color:#94A3B8;border:1px solid #E2E8F0;padding:2px 8px;border-radius:4px;">Inactive</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($h['email']) ?></div>
                <?php if ($h['phone']): ?>
                <div style="font-size:12.5px;color:var(--text-muted);"><?= htmlspecialchars($h['phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <div style="text-align:center;">
                <div style="font-weight:800;font-size:18px;color:var(--text-primary);"><?= $h['total_deliveries'] ?></div>
                <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Deliveries</div>
            </div>
            <div style="text-align:center;">
                <div style="font-weight:800;font-size:18px;color:<?= $h['cod_wallet'] > 0 ? '#D97706' : 'var(--text-primary)' ?>;">
                    ₹<?= number_format($h['cod_wallet'], 2) ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">COD Wallet</div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <!-- Settle wallet -->
            <?php if ($h['cod_wallet'] > 0): ?>
            <button onclick="openSettleModal(<?= $h['id'] ?>, '<?= htmlspecialchars(addslashes($h['name'])) ?>', '<?= number_format($h['cod_wallet'], 2) ?>')"
                class="btn-success-custom" style="padding:7px 12px;font-size:12.5px;">
                <i class="bi bi-cash-coin"></i> Settle ₹<?= number_format($h['cod_wallet'], 2) ?>
            </button>
            <?php endif; ?>

            <!-- Toggle active -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="handler_id" value="<?= $h['id'] ?>">
                <input type="hidden" name="is_active" value="<?= $h['is_active'] ?>">
                <button type="submit" class="btn-ghost-custom" style="padding:7px 12px;font-size:12.5px;" title="<?= $h['is_active'] ? 'Deactivate' : 'Activate' ?>">
                    <i class="bi bi-<?= $h['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                    <?= $h['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>

            <!-- Delete -->
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this handler? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_handler">
                <input type="hidden" name="handler_id" value="<?= $h['id'] ?>">
                <button type="submit" style="display:inline-flex;align-items:center;gap:5px;padding:7px 10px;background:var(--danger-bg);border:1px solid var(--danger-border);border-radius:var(--radius-sm);color:var(--danger-text);font-size:12.5px;font-weight:600;cursor:pointer;transition:var(--transition);" title="Delete Handler">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Created date -->
    <div style="margin-top:12px;padding-top:10px;border-top:1px solid #F1F5F9;font-size:11.5px;color:var(--text-muted);">
        Created <?= date('M j, Y', strtotime($h['created_at'])) ?>
        &nbsp;·&nbsp; <?= $h['total_cod_deliveries'] ?> COD collection<?= $h['total_cod_deliveries'] != 1 ? 's' : '' ?>
        &nbsp;·&nbsp; Handler login: <code style="background:#F1F5F9;padding:1px 5px;border-radius:3px;font-size:11px;">/handler/login.php</code>
    </div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>

<!-- ── Create Handler Modal ── -->
<div class="modal-backdrop-custom" id="createModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-person-plus-fill" style="color:#7C3AED;margin-right:8px;"></i>Add New Handler</div>
            <button class="modal-close" onclick="closeModal('createModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_handler">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label-custom">Full Name *</label>
                    <input type="text" name="name" class="input-custom" placeholder="e.g. Ravi Kumar" required>
                </div>
                <div>
                    <label class="form-label-custom">Email Address *</label>
                    <input type="email" name="email" class="input-custom" placeholder="handler@email.com" required>
                </div>
                <div>
                    <label class="form-label-custom">Phone Number</label>
                    <input type="tel" name="phone" class="input-custom" placeholder="+91 98765 43210">
                </div>
                <div>
                    <label class="form-label-custom">Password * <span style="font-size:11px;font-weight:400;color:var(--text-muted);">(min. 6 characters)</span></label>
                    <div style="position:relative;">
                        <input type="password" name="password" id="newPw" class="input-custom" placeholder="Set a secure password" required minlength="6">
                        <button type="button" onclick="toggleNewPw()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                            <i class="bi bi-eye" id="newPwEye"></i>
                        </button>
                    </div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;"><i class="bi bi-info-circle"></i> Share these credentials securely with the handler.</div>
                </div>
                <div style="display:flex;gap:10px;margin-top:4px;">
                    <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;">
                        <i class="bi bi-person-check-fill"></i> Create Handler
                    </button>
                    <button type="button" onclick="closeModal('createModal')" class="btn-ghost-custom" style="padding:9px 16px;">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Settle Wallet Modal ── -->
<div class="modal-backdrop-custom" id="settleModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-cash-coin" style="color:#D97706;margin-right:8px;"></i>Settle COD Wallet</div>
            <button class="modal-close" onclick="closeModal('settleModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" id="settleForm">
            <input type="hidden" name="action" value="settle_wallet">
            <input type="hidden" name="handler_id" id="settle_handler_id">
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;padding:14px 16px;">
                    <div style="font-size:13px;font-weight:600;color:#92400E;" id="settle_summary"></div>
                </div>
                <div>
                    <label class="form-label-custom">Settlement Note (optional)</label>
                    <input type="text" name="note" class="input-custom" placeholder="e.g. Evening collection 17 Sep">
                </div>
                <div style="background:var(--info-bg);border:1px solid var(--info-border);border-radius:8px;padding:12px 14px;font-size:13px;color:var(--info-text);">
                    <i class="bi bi-info-circle-fill"></i>
                    This will reset the handler's wallet to <strong>₹0.00</strong>. Make sure you have physically collected the cash.
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn-success-custom" style="flex:1;justify-content:center;padding:11px;">
                        <i class="bi bi-check-circle-fill"></i> Confirm Settlement
                    </button>
                    <button type="button" onclick="closeModal('settleModal')" class="btn-ghost-custom" style="padding:11px 16px;">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extra_scripts = <<<JS
<script>
function openSettleModal(handlerId, name, amount) {
    document.getElementById('settle_handler_id').value = handlerId;
    document.getElementById('settle_summary').textContent = 'You are settling ₹' + amount + ' from ' + name + "'s wallet.";
    openModal('settleModal');
}
function toggleNewPw() {
    const pw  = document.getElementById('newPw');
    const eye = document.getElementById('newPwEye');
    if (pw.type === 'password') { pw.type = 'text'; eye.className = 'bi bi-eye-slash'; }
    else { pw.type = 'password'; eye.className = 'bi bi-eye'; }
}
</script>
JS;

require 'includes/footer.php';
?>
