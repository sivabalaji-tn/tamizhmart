<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require '../config/db.php';

$page_title    = 'Orders';
$page_subtitle = 'All shop orders · Pick, pack and deliver';

require 'includes/sidebar.php';

$shop_id    = $handler_shop_id;
$handler_id = (int)$_SESSION['handler_id'];

// ── Status update (forward-only) ────────────────────────────────
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $oid        = (int)$_POST['order_id'];
        $new_status = $_POST['status'];

        // Fetch current status
        $cur = $conn->query("SELECT status, payment_method FROM orders WHERE id=$oid AND shop_id=$shop_id")->fetch_assoc();
        if ($cur) {
            $forward_map = [
                'pending'    => 'confirmed',
                'confirmed'  => 'processing',
                'processing' => 'out_for_delivery',
            ];
            // Handler can only go forward — out_for_delivery→delivered requires OTP (handled via AJAX)
            $allowed_next = $forward_map[$cur['status']] ?? null;

            if ($new_status === $allowed_next) {
                $stmt = $conn->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=? AND shop_id=?");
                $stmt->bind_param('sii', $new_status, $oid, $shop_id);
                $stmt->execute();


                // Log action
                $action_name = 'status_update';
                $log = $conn->prepare("INSERT INTO handler_activity_log (handler_id,shop_id,order_id,action,old_value,new_value) VALUES (?,?,?,?,?,?)");
                $log->bind_param('iiisss', $handler_id, $shop_id, $oid, $action_name, $cur['status'], $new_status);
                $log->execute();


                $num = $conn->query("SELECT shop_order_number FROM orders WHERE id=$oid")->fetch_row()[0] ?? $oid;
                $success = 'Order #' . str_pad($num, 4, '0', STR_PAD_LEFT) . ' moved to ' . ucfirst(str_replace('_', ' ', $new_status));
            } else {
                $error = 'Invalid status change. You can only advance orders forward.';
            }
        }
    }
}

// ── Filters ─────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

$where = "o.shop_id = $shop_id";
if ($filter_status !== 'all') $where .= " AND o.status = '" . $conn->real_escape_string($filter_status) . "'";
if ($search) $where .= " AND (u.name LIKE '%" . $conn->real_escape_string($search) . "%' OR o.shop_order_number LIKE '%" . $conn->real_escape_string($search) . "%')";

$total_rows  = $conn->query("SELECT COUNT(*) AS c FROM orders o JOIN users u ON o.user_id=u.id WHERE $where")->fetch_assoc()['c'];
$total_pages = ceil($total_rows / $per_page);

$orders = $conn->query("
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
    FROM orders o JOIN users u ON o.user_id = u.id
    WHERE $where ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset
");

// ── Status counts ────────────────────────────────────────────────
$status_tabs = ['all','pending','confirmed','processing','out_for_delivery','delivered','cancelled'];
$status_counts = [];
foreach ($status_tabs as $s) {
    $w = $s === 'all' ? "shop_id=$shop_id" : "shop_id=$shop_id AND status='$s'";
    $status_counts[$s] = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE $w")->fetch_assoc()['c'];
}
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── Status Tabs ── -->
<div class="animate-in" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
<?php
$tab_labels = ['all'=>'All','pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
foreach ($status_tabs as $s):
    $active = $filter_status === $s;
?>
<a href="?status=<?= $s ?>&q=<?= urlencode($search) ?>"
    style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:6px;font-size:12.5px;text-decoration:none;border:1px solid;transition:all 0.15s;
    <?= $active ? 'background:#1E293B;border-color:#1E293B;color:#FFFFFF;font-weight:600;' : 'background:#FFFFFF;border-color:#CBD5E1;color:var(--text-secondary);' ?>">
    <?= $tab_labels[$s] ?>
    <span style="background:<?= $active ? 'rgba(255,255,255,0.2)' : '#F1F5F9' ?>;color:<?= $active ? '#FFFFFF' : '#475569' ?>;padding:1px 7px;border-radius:4px;font-size:11px;font-weight:700;">
        <?= $status_counts[$s] ?>
    </span>
</a>
<?php endforeach; ?>
</div>

<!-- ── Search ── -->
<div class="card-glass animate-in d2" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
        <div style="position:relative;flex:1;min-width:200px;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by customer name or order number..."
                class="input-custom" style="padding-left:36px;">
        </div>
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Search</button>
        <?php if ($search): ?><a href="?status=<?= $filter_status ?>" class="btn-ghost-custom">Clear</a><?php endif; ?>
    </form>
</div>

<!-- ── Orders ── -->
<div class="card-glass animate-in d3" style="padding:0;overflow:hidden;">
<?php if ($orders->num_rows === 0): ?>
<div class="empty-state">
    <i class="bi bi-bag-x"></i>
    <h4>No Orders Found</h4>
    <p><?= $search ? 'Try a different search term.' : 'No ' . ($filter_status !== 'all' ? str_replace('_',' ',$filter_status) . ' ' : '') . 'orders yet.' ?></p>
</div>
<?php else: ?>

<!-- Desktop Table (hidden on mobile) -->
<div style="overflow-x:auto;" class="d-none d-md-block">
    <table class="table-glass">
        <thead>
            <tr>
                <th style="padding-left:20px;">Order</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date</th>
                <th style="text-align:right;padding-right:20px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $orders_data = [];
        while ($o = $orders->fetch_assoc()) $orders_data[] = $o;
        foreach ($orders_data as $o):
            $num = '#' . str_pad($o['shop_order_number'] ?? $o['id'], 4, '0', STR_PAD_LEFT);
            $is_cod = $o['payment_method'] === 'cod';
        ?>
        <tr>
            <td style="padding-left:20px;">
                <div style="font-weight:700;color:var(--primary);"><?= $num ?></div>
                <div style="font-size:11.5px;color:var(--text-muted);"><?= date('M j', strtotime($o['created_at'])) ?></div>
            </td>
            <td>
                <div style="font-weight:600;"><?= htmlspecialchars($o['customer_name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($o['customer_phone'] ?? $o['customer_email'] ?? '') ?></div>
            </td>
            <td>
                <div style="font-weight:700;">₹<?= number_format($o['total_amount'], 2) ?></div>
                <?php if ($is_cod): ?>
                <span style="font-size:10px;font-weight:700;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;padding:1px 5px;border-radius:3px;">COD</span>
                <?php endif; ?>
            </td>
            <td><span class="status-pill pill-<?= $o['status'] ?>"><?= ucfirst(str_replace('_',' ',$o['status'])) ?></span></td>
            <td style="font-size:11.5px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;"><?= strtoupper($o['payment_method']) ?></td>
            <td style="color:var(--text-muted);font-size:12px;"><?= date('M j, g:i A', strtotime($o['created_at'])) ?></td>
            <td style="text-align:right;padding-right:20px;">
                <div style="display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap;">
                    <a href="order_detail.php?order_id=<?= $o['id'] ?>" class="btn-handler-custom" style="padding:5px 10px;font-size:12px;">
                        <i class="bi bi-eye"></i> Details
                    </a>
                    <?php
                    $next_map = ['pending'=>['label'=>'Confirm','status'=>'confirmed','cls'=>'btn-success-custom'],
                                 'confirmed'=>['label'=>'Process','status'=>'processing','cls'=>'btn-primary-custom'],
                                 'processing'=>['label'=>'Dispatch','status'=>'out_for_delivery','cls'=>'btn-orange-custom']];
                    if (isset($next_map[$o['status']])): $n = $next_map[$o['status']]; ?>
                    <button onclick="quickAdvance(<?= $o['id'] ?>,'<?= $n['status'] ?>')"
                        class="<?= $n['cls'] ?>" style="padding:5px 10px;font-size:12px;">
                        <i class="bi bi-arrow-right-circle"></i> <?= $n['label'] ?>
                    </button>
                    <?php endif; ?>
                    <?php if ($o['status'] === 'out_for_delivery'): ?>
                    <a href="order_detail.php?order_id=<?= $o['id'] ?>#deliver" class="btn-handler-custom" style="padding:5px 10px;font-size:12px;background:#047857;border-color:#047857;">
                        <i class="bi bi-shield-check"></i> Deliver (OTP)
                    </a>
                    <?php endif; ?>
                    <a href="invoice_pdf.php?order_id=<?= $o['id'] ?>" target="_blank" class="btn-ghost-custom" style="padding:5px 10px;font-size:12px;" title="Print Invoice">
                        <i class="bi bi-printer"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Mobile Cards -->
<div class="d-md-none">
    <?php foreach ($orders_data as $o):
        $num = '#' . str_pad($o['shop_order_number'] ?? $o['id'], 4, '0', STR_PAD_LEFT);
        $is_cod = $o['payment_method'] === 'cod';
    ?>
    <div style="padding:14px 16px;border-bottom:1px solid #F1F5F9;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:8px;">
            <div>
                <div style="font-weight:800;font-size:15px;color:var(--primary);"><?= $num ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= date('M j, g:i A', strtotime($o['created_at'])) ?></div>
            </div>
            <span class="status-pill pill-<?= $o['status'] ?>"><?= ucfirst(str_replace('_',' ',$o['status'])) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <div>
                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($o['customer_name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($o['customer_phone'] ?? '') ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:800;font-size:16px;color:var(--text-primary);">₹<?= number_format($o['total_amount'], 2) ?></div>
                <?php if ($is_cod): ?>
                <span style="font-size:10px;font-weight:700;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;padding:1px 5px;border-radius:3px;">COD</span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="order_detail.php?order_id=<?= $o['id'] ?>" class="btn-handler-custom" style="flex:1;justify-content:center;padding:9px;font-size:13px;">
                <i class="bi bi-eye"></i> Details
            </a>
            <?php
            $next_map = ['pending'=>['label'=>'Confirm','cls'=>'btn-success-custom','status'=>'confirmed'],
                         'confirmed'=>['label'=>'Process','cls'=>'btn-primary-custom','status'=>'processing'],
                         'processing'=>['label'=>'Dispatch','cls'=>'btn-orange-custom','status'=>'out_for_delivery']];
            if (isset($next_map[$o['status']])): $n = $next_map[$o['status']]; ?>
            <button onclick="quickAdvance(<?= $o['id'] ?>,'<?= $n['status'] ?>')" class="<?= $n['cls'] ?>" style="flex:1;justify-content:center;padding:9px;font-size:13px;">
                <i class="bi bi-arrow-right-circle"></i> <?= $n['label'] ?>
            </button>
            <?php endif; ?>
            <?php if ($o['status'] === 'out_for_delivery'): ?>
            <a href="order_detail.php?order_id=<?= $o['id'] ?>#deliver" class="btn-handler-custom" style="flex:1;justify-content:center;padding:9px;font-size:13px;background:#047857;border-color:#047857;">
                <i class="bi bi-shield-check"></i> Deliver (OTP)
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="padding:14px 20px;border-top:1px solid var(--card-border);background:#F8FAFC;display:flex;align-items:center;justify-content:space-between;">
    <span style="font-size:12.5px;color:var(--text-muted);">Showing <?= $offset+1 ?>–<?= min($offset+$per_page,$total_rows) ?> of <?= $total_rows ?></span>
    <div style="display:flex;gap:4px;">
        <?php for ($p = 1; $p <= min($total_pages, 10); $p++): ?>
        <a href="?status=<?= $filter_status ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"
            style="width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12.5px;text-decoration:none;border:1px solid;
            <?= $p == $page ? 'background:var(--handler-accent);border-color:var(--handler-accent);color:#FFFFFF;font-weight:700;' : 'background:#FFFFFF;border-color:#CBD5E1;color:var(--text-secondary);' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
</div>

<!-- Quick Advance Form -->
<form id="quickForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="order_id" id="qf_order_id">
    <input type="hidden" name="status" id="qf_status">
</form>

<?php
$extra_scripts = <<<JS
<script>
function quickAdvance(orderId, newStatus) {
    const labels = {confirmed:'Confirm',processing:'start Processing',out_for_delivery:'mark as Out for Delivery'};
    if (!confirm('Move this order to: ' + (labels[newStatus] || newStatus) + '?')) return;
    document.getElementById('qf_order_id').value = orderId;
    document.getElementById('qf_status').value   = newStatus;
    document.getElementById('quickForm').submit();
}
</script>
JS;
require 'includes/footer.php';
?>
