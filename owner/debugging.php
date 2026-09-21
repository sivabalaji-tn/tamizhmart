<?php
session_start();
require_once __DIR__ . '/includes/debug_access.php';
if (!ownerDebugEnabled()) { http_response_code(404); exit('Not found.'); }
if (empty($_SESSION['owner_id']) || empty($_SESSION['shop_id'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/debug_tools.php';
header('Cache-Control: no-store');
$ownerId = (int)$_SESSION['owner_id'];
$shopId = (int)$_SESSION['shop_id'];
try { $debugShop = odShop($conn, $ownerId, $shopId); }
catch (InvalidArgumentException $exception) { http_response_code(403); exit('This shop is unavailable.'); }
$conn->query(file_get_contents(__DIR__ . '/../databasefile/add_owner_debug_logs.sql'));
$_SESSION['owner_debug_csrf'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = odRun($conn, $ownerId, $shopId, $_POST, $_SESSION['owner_debug_csrf']);
        $message = $result['message'];
        if ($result['media']) {
            try {
                $cleanup = odRemoveMedia($conn, $result['media'], __DIR__ . '/../assets/uploads');
                $message .= ' Media: ' . $cleanup['removed'] . ' files removed; ' . $cleanup['retained'] . ' shared or protected files retained.';
            } catch (Throwable $exception) {
                $message .= ' Data changes completed, but uploaded-file cleanup could not finish.';
                error_log('Owner debug media cleanup failed: ' . get_class($exception));
            }
        }
        $_SESSION['owner_debug_flash'] = ['type' => 'success', 'message' => $message];
    } catch (InvalidArgumentException $exception) {
        $_SESSION['owner_debug_flash'] = ['type' => 'error', 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('Owner debug operation failed: ' . get_class($exception) . ' [' . $exception->getCode() . ']');
        $_SESSION['owner_debug_flash'] = ['type' => 'error', 'message' => 'The action failed. Database changes were rolled back.'];
    }
    header('Location: debugging.php', true, 303);
    exit;
}
$flash = $_SESSION['owner_debug_flash'] ?? null;
unset($_SESSION['owner_debug_flash']);
$counts = odCounts($conn, $shopId);
$stock = odQuery($conn, 'SELECT COALESCE(SUM(stock),0) AS units,SUM(stock=0 OR stock IS NULL) AS empty,SUM(stock BETWEEN 1 AND 5) AS low FROM products WHERE shop_id=?', [$shopId])->get_result()->fetch_assoc();
$products = odQuery($conn, 'SELECT id,name FROM products WHERE shop_id=? ORDER BY name', [$shopId])->get_result()->fetch_all(MYSQLI_ASSOC);
$categories = odQuery($conn, 'SELECT id,name FROM categories WHERE shop_id=? ORDER BY name', [$shopId])->get_result()->fetch_all(MYSQLI_ASSOC);
$history = odQuery($conn, 'SELECT action,summary,created_at FROM owner_debug_logs WHERE shop_id=? ORDER BY id DESC LIMIT 15', [$shopId])->get_result()->fetch_all(MYSQLI_ASSOC);
$requestKey = bin2hex(random_bytes(16));
function odEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function odFields(string $action = ''): void {
    global $requestKey;
    echo '<input type="hidden" name="csrf" value="' . odEscape($_SESSION['owner_debug_csrf']) . '">';
    echo '<input type="hidden" name="request_key" value="' . odEscape($requestKey) . '">';
    if ($action !== '') echo '<input type="hidden" name="action" value="' . odEscape($action) . '">';
}
function odScope(string $prefix): void {
    global $products, $categories;
    ?>
    <div class="debug-scope">
        <div><label for="<?= $prefix ?>-scope">Apply to</label><select name="scope" id="<?= $prefix ?>-scope" class="input-custom scope-select"><option value="all">All shop products</option><option value="product">One product</option><option value="category">One category</option></select></div>
        <div class="scope-product"><label for="<?= $prefix ?>-product">Product</label><select id="<?= $prefix ?>-product" name="product_id" class="input-custom"><option value="">Select a product</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= odEscape($product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="scope-category"><label for="<?= $prefix ?>-category">Category</label><select id="<?= $prefix ?>-category" name="category_id" class="input-custom"><option value="">Select a category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= odEscape($category['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <?php
}
$page_title = 'Debugging the Platform';
$page_subtitle = 'Testing workspace';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
html, body { height: auto; min-height: 100%; overflow-x: clip; }
.main-content, .page-body { min-width: 0; }
.debug-workspace { max-width: 1400px; margin: 0 auto; overflow-wrap: anywhere; }
.debug-workspace *, .topbar h1 { letter-spacing: 0; }
.debug-environment { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 12px 16px; background: #fff8e7; border: 1px solid #eddaa9; border-radius: 4px; font-size: 12px; color: #775612; }
.debug-environment > span:first-child { font-weight: 700; display: flex; gap: 8px; align-items: center; }
.debug-environment strong { color: var(--text-primary); }
.debug-tabs { display: flex; gap: 24px; overflow-x: auto; border-bottom: 1px solid var(--card-border); margin: 20px 0; }
.debug-tabs a { padding: 12px 0; color: var(--text-secondary); font-size: 13px; text-decoration: none; white-space: nowrap; }
.debug-tabs a:hover { color: var(--primary); }
.debug-stats { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: 16px; margin: 22px 0 28px; }
.debug-stats > div { border-right: 1px solid var(--card-border); padding-right: 12px; }
.debug-stats > div:last-child { border: 0; }
.debug-stats span { color: var(--text-muted); font-size: 12px; display: block; }
.debug-stats strong { font-size: 25px; display: block; margin-top: 5px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.debug-section { background: white; border-block: 1px solid var(--card-border); padding: 24px; margin-bottom: 24px; scroll-margin-top: 110px; }
.debug-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 22px; }
.debug-heading h2 { font-size: 17px; margin: 0; font-weight: 650; display: flex; align-items: center; gap: 10px; }
.debug-heading > span { font-size: 12px; color: var(--text-muted); }
.debug-workspace h3 { font-size: 14px; font-weight: 650; margin: 0 0 16px; }
.debug-columns, .debug-reset-layout { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 32px; }
.debug-tool { min-width: 0; }
.debug-tool + .debug-tool { border-left: 1px solid var(--card-border); padding-left: 32px; }
.debug-workspace label:not(.debug-check) { display: block; font-size: 12px; font-weight: 600; margin: 0 0 7px; color: var(--text-secondary); }
.debug-workspace .input-custom { width: 100%; min-height: 40px; min-width: 0; border-radius: 4px; font-size: 13px; margin-bottom: 15px; }
.debug-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 4px; }
.debug-workspace button { min-height: 40px; border-radius: 4px; justify-content: center; }
.debug-subtool { margin-top: 22px; padding-top: 22px; border-top: 1px solid var(--card-border); }
.debug-inline-form { display: flex; align-items: end; gap: 12px; flex-wrap: wrap; }
.debug-inline-form .input-custom { margin: 0; }
.debug-inline-form > div { width: 170px; }
.debug-caption { margin: 14px 0 0; color: var(--text-muted); font-size: 12px; line-height: 1.7; }
.debug-quick-actions { display: grid; gap: 0; margin-bottom: 24px; }
.debug-quick-actions > form { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--card-border); }
.debug-quick-actions strong { display: block; font-size: 13px; font-weight: 600; }
.debug-quick-actions span { display: block; font-size: 12px; color: var(--text-muted); margin-top: 4px; }
.debug-quick-actions button { flex-shrink: 0; }
.debug-cleanup-form { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); column-gap: 20px; }
.debug-cleanup-form > .debug-check, .debug-cleanup-form > .debug-actions, .debug-cleanup-form > p { grid-column: 1 / -1; }
.debug-check { display: flex; gap: 10px; align-items: flex-start; font-size: 12px; line-height: 1.6; margin: 12px 0 16px; color: var(--text-secondary); }
.debug-check input { width: 16px; height: 16px; margin-top: 2px; flex-shrink: 0; accent-color: var(--primary); }
.debug-danger { border-top: 2px solid #c94848; }
.debug-danger .debug-heading h2, .debug-danger .debug-heading > span { color: #a42929; }
.debug-inventory { margin: 0; }
.debug-inventory > div { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #edf0f2; padding: 7px 0; font-size: 12px; }
.debug-inventory dt { font-weight: 400; color: var(--text-secondary); }
.debug-inventory dd { margin: 0; font-weight: 600; font-variant-numeric: tabular-nums; }
.debug-retained { display: flex; align-items: flex-start; gap: 8px; margin: 16px 0 0; font-size: 12px; color: #2b6b4e; line-height: 1.7; }
.debug-reset-button { width: 100%; margin-top: 10px; }
.debug-table-scroll { overflow-x: auto; }
.debug-table-scroll table { min-width: 650px; }
.debug-table-scroll td:first-child { white-space: nowrap; }
.debug-table-scroll td:last-child { min-width: 290px; }
.debug-notice { border: 1px solid; padding: 14px 16px; margin-top: 16px; border-radius: 4px; font-size: 13px; }
.debug-notice-error { background: #fff1f1; border-color: #eccaca; color: #922c2c; }
.debug-notice-success { background: #ecf7f0; border-color: #b9dcc7; color: #256441; }
.debug-workspace [hidden] { display: none !important; }
.debug-workspace :focus-visible { outline: 2px solid var(--primary); outline-offset: 3px; }
@media(max-width:1100px) { .debug-columns, .debug-reset-layout { grid-template-columns: minmax(0,1fr); gap: 24px; } .debug-tool + .debug-tool { border-left: 0; padding-left: 0; padding-top: 24px; border-top: 1px solid var(--card-border); } }
@media(max-width:600px) {
    .topbar { gap: 8px; }
    .topbar > div:first-child, .topbar-left { min-width: 0; }
    .topbar h1 { font-size: 17px; white-space: normal; overflow-wrap: anywhere; }
    .topbar-right { flex-shrink: 0; }
    .debug-section { padding: 18px 14px; }
    .debug-stats { grid-template-columns: repeat(3,minmax(0,1fr)); gap: 18px 12px; }
    .debug-stats strong { font-size: 23px; }
    .debug-cleanup-form { grid-template-columns: minmax(0,1fr); }
    .debug-inline-form { align-items: stretch; }
    .debug-inline-form > div { width: 100%; }
    .debug-quick-actions > form { align-items: flex-start; flex-wrap: wrap; }
    .debug-workspace .input-custom { font-size: 16px; }
    .debug-workspace button { max-width: 100%; white-space: normal; }
    .debug-tabs { gap: 20px; }
}
</style>
<div class="debug-workspace">
    <div class="debug-environment"><span><i class="bi bi-tools"></i> Testing enabled</span><strong><?= odEscape($debugShop['name']) ?></strong><span>/<?= odEscape($debugShop['slug']) ?></span></div>
    <?php if ($flash): ?><div class="debug-notice <?= $flash['type'] === 'error' ? 'debug-notice-error' : 'debug-notice-success' ?>" role="status"><?= odEscape($flash['message']) ?></div><?php endif; ?>
    <nav class="debug-tabs" aria-label="Debug sections"><a href="#inventory">Inventory</a><a href="#fixtures">Test data</a><a href="#cleanup">Cleanup</a><a href="#reset">Shop reset</a><a href="#history">Activity</a></nav>
    <div class="debug-stats">
        <div><span>Products</span><strong><?= number_format($counts['products']) ?></strong></div>
        <div><span>Stock units</span><strong><?= number_format((int)$stock['units']) ?></strong></div>
        <div><span>Out of stock</span><strong><?= number_format((int)$stock['empty']) ?></strong></div>
        <div><span>Orders</span><strong><?= number_format($counts['orders']) ?></strong></div>
        <div><span>Customers</span><strong><?= number_format($counts['users']) ?></strong></div>
    </div>
    <section id="inventory" class="debug-section">
        <div class="debug-heading"><h2><i class="bi bi-box-seam"></i> Inventory controls</h2><span><?= (int)$stock['low'] ?> low-stock products</span></div>
        <div class="debug-columns">
            <form method="POST" class="debug-tool">
                <?php odFields(); ?>
                <h3>Product stock</h3>
                <?php odScope('stock'); ?>
                <label for="stock-quantity">Units per product</label><input id="stock-quantity" type="number" name="quantity" value="100" min="0" max="1000000" step="1" required class="input-custom">
                <div class="debug-actions"><button class="btn-primary-custom" name="action" value="stock_set"><i class="bi bi-check2-square"></i> Set exact stock</button><button class="btn-ghost-custom" name="action" value="stock_add"><i class="bi bi-plus-lg"></i> Add to stock</button></div>
            </form>
            <div class="debug-tool">
                <h3>Quick stock presets</h3>
                <form method="POST" class="debug-actions debug-presets"><?php odFields('stock_set'); ?><input type="hidden" name="scope" value="all"><?php foreach ([0,5,100,500] as $preset): ?><button class="btn-ghost-custom" name="quantity" value="<?= $preset ?>"><i class="bi bi-boxes"></i> Set all to <?= $preset ?></button><?php endforeach; ?></form>
                <form method="POST" class="debug-subtool">
                    <?php odFields(); ?><h3>Product visibility &amp; prices</h3><?php odScope('visibility'); ?>
                    <label for="visibility-state">Visibility</label><select id="visibility-state" name="is_active" class="input-custom"><option value="1">Published</option><option value="0">Hidden</option></select>
                    <div class="debug-actions"><button class="btn-primary-custom" name="action" value="visibility"><i class="bi bi-eye"></i> Apply visibility</button><button class="btn-ghost-custom" name="action" value="remove_discounts"><i class="bi bi-tag"></i> Remove discounts</button></div>
                </form>
            </div>
        </div>
    </section>
    <section id="fixtures" class="debug-section">
        <div class="debug-heading"><h2><i class="bi bi-database-add"></i> Sample data</h2><span>Up to 25 records per action</span></div>
        <form method="POST" class="debug-inline-form"><?php odFields(); ?><div><label for="fixture-count">Records to create</label><input id="fixture-count" class="input-custom" type="number" name="count" value="5" min="1" max="25" required></div>
            <button name="action" value="seed_products" class="btn-primary-custom"><i class="bi bi-box-seam"></i> Create test products</button><button name="action" value="seed_customers" class="btn-ghost-custom"><i class="bi bi-people"></i> Create test customers</button><button name="action" value="seed_orders" class="btn-ghost-custom"><i class="bi bi-receipt"></i> Create sample orders</button>
        </form>
        <p class="debug-caption">Sample orders use test products and inactive test customers. No emails, delivery messages, payment requests or stock deductions are triggered.</p>
    </section>
    <section id="cleanup" class="debug-section">
        <div class="debug-heading"><h2><i class="bi bi-arrow-counterclockwise"></i> Reset individual components</h2></div>
        <div class="debug-quick-actions">
            <form method="POST"><?php odFields('clear_carts'); ?><div><strong>Customer carts</strong><span><?= $counts['cart'] ?> cart rows</span></div><button class="btn-ghost-custom"><i class="bi bi-cart-x"></i> Clear carts</button></form>
            <form method="POST"><?php odFields('reset_coupon_usage'); ?><div><strong>Coupon counters</strong><span><?= $counts['coupons'] ?> coupons</span></div><button class="btn-ghost-custom"><i class="bi bi-ticket-perforated"></i> Reset usage</button></form>
            <form method="POST"><?php odFields('reset_campaign_usage'); ?><div><strong>Email campaign usage</strong><span>Current month only; monthly limit stays unchanged</span></div><button class="btn-ghost-custom"><i class="bi bi-envelope"></i> Reset usage</button></form>
        </div>
        <form method="POST" class="debug-cleanup-form">
            <?php odFields(); ?>
            <div><label for="cleanup-action">Data to clear</label><select id="cleanup-action" name="action" class="input-custom">
                <option value="clear_orders">Orders, delivery, commission &amp; COD records</option><option value="clear_catalogue">Products, categories &amp; cart contents</option><option value="clear_customers">Customer accounts &amp; related tokens</option><option value="clear_promotions">Coupons, popups &amp; all campaign data</option><option value="reset_appearance">Theme &amp; announcement defaults</option>
            </select></div>
            <div><label for="cleanup-confirm">Type <?= odEscape($debugShop['slug']) ?> to confirm</label><input id="cleanup-confirm" name="confirmation" class="input-custom" autocomplete="off" required></div>
            <label class="debug-check"><input type="checkbox" name="remove_media" value="1"> Remove unshared uploads belonging to deleted products or popups</label>
            <div class="debug-actions"><button class="btn-danger-custom"><i class="bi bi-trash3"></i> Clear selected data</button></div>
            <p class="debug-caption">Order cleanup also clears delivery OTPs, commission records and COD settlements, and resets COD wallets and coupon counters. Orders must be cleared before removing products or customers.</p>
        </form>
    </section>
    <section id="reset" class="debug-section debug-danger">
        <div class="debug-heading"><h2><i class="bi bi-exclamation-triangle"></i> Full shop reset</h2><span>Permanent deletion</span></div>
        <div class="debug-reset-layout">
            <div><h3>Data to remove</h3><dl class="debug-inventory"><?php foreach (['products'=>'Products','categories'=>'Categories','orders'=>'Orders','users'=>'Customers','cart'=>'Cart rows','shop_handlers'=>'Staff handlers','coupons'=>'Coupons','popups'=>'Popups','email_campaigns'=>'Email campaigns','shop_settings'=>'Shop settings','commission_log'=>'Commission entries'] as $table=>$label): ?><div><dt><?= $label ?></dt><dd><?= number_format($counts[$table]) ?></dd></div><?php endforeach; ?></dl>
                <p class="debug-caption">Also removes delivery OTPs, order items, staff activity, COD settlements, commission collections, campaign recipients, campaign allowances, customer reset tokens, branding and contact settings.</p>
                <p class="debug-retained"><i class="bi bi-shield-check"></i> Keeps your owner account, shop name and URL, other shops, and debug/admin audit history.</p>
            </div>
            <form method="POST" id="full-reset-form">
                <?php odFields('reset_shop'); ?>
                <label for="reset-confirm">Type <?= odEscape($debugShop['slug']) ?> to confirm</label><input id="reset-confirm" name="confirmation" class="input-custom" autocomplete="off" required>
                <label for="reset-password">Current owner password</label><input id="reset-password" type="password" name="password" class="input-custom" autocomplete="current-password" required>
                <label class="debug-check"><input type="checkbox" name="remove_media" value="1" checked> Delete unshared product, category, popup, logo and banner uploads</label>
                <label class="debug-check"><input type="checkbox" name="clear_subscriptions" value="1"> Also delete this shop's subscription history (<?= $counts['shop_subscriptions'] ?> records)</label>
                <label class="debug-check"><input type="checkbox" name="acknowledge" value="1" required> I understand this permanently deletes my shop's test data.</label>
                <button class="btn-danger-custom debug-reset-button"><i class="bi bi-trash3"></i> Reset this shop</button>
            </form>
        </div>
    </section>
    <section id="history" class="debug-section">
        <div class="debug-heading"><h2><i class="bi bi-clock-history"></i> Recent debug activity</h2><span>Latest 15 actions</span></div>
        <?php if (!$history): ?><p class="debug-caption">No debug actions recorded.</p><?php else: ?><div class="debug-table-scroll" tabindex="0" role="region" aria-label="Recent debug actions"><table class="table-glass"><thead><tr><th>Time</th><th>Action</th><th>Result</th></tr></thead><tbody><?php foreach ($history as $entry): ?><tr><td><?= odEscape($entry['created_at']) ?></td><td><?= odEscape(ucwords(str_replace('_',' ',$entry['action']))) ?></td><td><?= odEscape($entry['summary']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</div>
<script>
document.querySelectorAll('.debug-scope').forEach(group => {
    const select = group.querySelector('.scope-select');
    function update() {
        for (const type of ['product', 'category']) {
            const field = group.querySelector('.scope-' + type);
            field.hidden = select.value !== type;
            field.querySelector('select').disabled = select.value !== type;
            field.querySelector('select').required = select.value === type;
        }
    }
    select.addEventListener('change', update);
    update();
});
document.querySelectorAll('.debug-workspace form').forEach(form => {
    form.addEventListener('submit', () => {
        // Preserve the clicked button's name/value while preventing duplicate clicks.
        requestAnimationFrame(() => form.querySelectorAll('button').forEach(button => {
            button.style.pointerEvents = 'none';
            button.setAttribute('aria-disabled', 'true');
        }));
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
