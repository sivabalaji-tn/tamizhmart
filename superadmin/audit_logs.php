<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/includes/audit.php';
saAuditRequireAdmin();
saAuditEnsure($conn);

$page_title = 'Audit trail';
$page_subtitle = 'Administrator activity and changes across your platform';
$actions = saAuditActions();
$timezone = new DateTimeZone('Asia/Kolkata');
$utc = new DateTimeZone('UTC');
$today = new DateTimeImmutable('today', $timezone);
function auditEscape($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function auditInput(string $key, string $default = ''): string { return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : $default; }
function auditDate(string $value, DateTimeZone $timezone): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
}
function auditTime(string $value): string {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i:s A');
}
function auditFlat(array $data, string $prefix = ''): array {
    $flat = [];
    foreach ($data as $key => $value) {
        $name = $prefix . ($prefix !== '' ? ' / ' : '') . (is_numeric($key) ? '#' . $key : ucwords(str_replace('_', ' ', $key)));
        if (is_array($value) && $value) $flat += auditFlat($value, $name);
        else $flat[$name] = is_array($value) ? 'None' : ($value === null ? 'Not set' : (string)$value);
    }
    return $flat;
}
function auditLink(array $overrides = []): string {
    $values = array_intersect_key($_GET, array_flip(['q', 'action', 'admin', 'shop', 'from', 'to', 'page']));
    $values = array_filter($values, 'is_scalar');
    return 'audit_logs.php?' . http_build_query(array_merge($values, $overrides));
}

$eventId = max(0, (int)auditInput('event', '0'));
if ($eventId) {
    $event = saAuditRows($conn, 'SELECT * FROM superadmin_audit_logs WHERE id=?', [$eventId])[0] ?? null;
    if (!$event) http_response_code(404);
    $page_title = $event ? 'Audit event #' . $eventId : 'Event not found';
    $page_subtitle = $event ? ($actions[$event['action']] ?? $event['action']) : 'This audit record is unavailable';
    require __DIR__ . '/includes/sidebar.php';
    ?>
    <link rel="stylesheet" href="assets/audit.css?v=1">
    <a class="btn-ghost-custom audit-back" href="<?= auditEscape(auditLink()) ?>"><i class="bi bi-arrow-left"></i> Back to audit trail</a>
    <?php if ($event):
        $before = auditFlat(json_decode($event['before_data'], true, 512, JSON_THROW_ON_ERROR));
        $after = auditFlat(json_decode($event['after_data'], true, 512, JSON_THROW_ON_ERROR));
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changed = array_filter($keys, fn($key) => ($before[$key] ?? null) !== ($after[$key] ?? null));
        $affected = saAuditRows($conn, 'SELECT shop_id,shop_name FROM superadmin_audit_shops WHERE audit_id=? ORDER BY shop_id', [$eventId]);
    ?>
    <section class="card-glass audit-record">
        <h2 class="section-title"><?= auditEscape($event['summary']) ?></h2>
        <dl class="audit-metadata">
            <div><dt>Administrator</dt><dd><?= auditEscape($event['admin_name']) ?><span><?= auditEscape($event['admin_email']) ?></span></dd></div>
            <div><dt>Recorded at (IST)</dt><dd><?= auditEscape(auditTime($event['occurred_at'])) ?></dd></div>
            <div><dt>Record</dt><dd><?= auditEscape($event['entity_label']) ?><span><?= auditEscape(ucwords(str_replace('_', ' ', $event['entity_type']))) ?><?= $event['entity_id'] ? ' #' . (int)$event['entity_id'] : '' ?></span></dd></div>
            <div><dt>IP address</dt><dd><?= auditEscape($event['ip_address'] ?: 'Unavailable') ?></dd></div>
            <div><dt>Source</dt><dd><?= auditEscape($event['source_page']) ?></dd></div>
            <div><dt>Affected shops</dt><dd><?php if (!$affected): ?>Not applicable<?php else: foreach ($affected as $shop): ?><span class="audit-shop-name"><?= auditEscape($shop['shop_name']) ?> <small>#<?= (int)$shop['shop_id'] ?></small></span><?php endforeach; endif; ?></dd></div>
        </dl>
    </section>
    <section class="card-glass audit-changes">
        <h2 class="section-title">Changes <span class="nav-badge"><?= count($changed) ?></span></h2>
        <?php if ($changed): ?>
        <div class="table-scroll" role="region" tabindex="0" aria-label="Before and after changes">
            <table class="table-custom audit-diff"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>
            <?php foreach ($changed as $key): ?>
            <tr><td><?= auditEscape($key) ?></td><td class="audit-before"><?= auditEscape($before[$key] ?? 'Not present') ?></td><td class="audit-after"><?= auditEscape($after[$key] ?? 'Removed') ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php else: ?>
        <p class="audit-note"><?= $event['action'] === 'settings.change_password' ? 'Password changed successfully. Password values are not recorded.' : auditEscape($actions[$event['action']] ?? 'Activity recorded') . '.' ?></p>
        <?php endif; ?>
    </section>
    <?php endif;
    require __DIR__ . '/includes/footer.php';
    exit;
}

$search = substr(auditInput('q'), 0, 160);
$action = auditInput('action');
$adminId = max(0, (int)auditInput('admin', '0'));
$shopId = max(0, (int)auditInput('shop', '0'));
$from = auditInput('from', $today->modify('-29 days')->format('Y-m-d'));
$to = auditInput('to', $today->format('Y-m-d'));
$fromDate = auditDate($from, $timezone);
$toDate = auditDate($to, $timezone);
$filterError = '';
$where = []; $params = [];
if (!$fromDate || !$toDate || $fromDate > $toDate) {
    $filterError = 'Select a valid date range. The end date must be on or after the start date.';
    $where[] = '1=0';
} else {
    $where[] = 'l.occurred_at >= ? AND l.occurred_at < ?';
    $params[] = $fromDate->setTimezone($utc)->format('Y-m-d H:i:s');
    $params[] = $toDate->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
}
if ($search !== '') {
    $where[] = '(l.admin_name LIKE ? OR l.admin_email LIKE ? OR l.entity_label LIKE ? OR l.summary LIKE ? OR l.ip_address LIKE ?)';
    for ($i = 0; $i < 5; $i++) $params[] = '%' . $search . '%';
}
if ($action !== '') { $where[] = 'l.action=?'; $params[] = $action; }
if ($adminId) { $where[] = 'l.admin_id=?'; $params[] = $adminId; }
if ($shopId) {
    $where[] = 'EXISTS (SELECT 1 FROM superadmin_audit_shops s WHERE s.audit_id=l.id AND s.shop_id=?)';
    $params[] = $shopId;
}
$whereSql = implode(' AND ', $where);
$stats = saAuditRows($conn, "SELECT COUNT(*) AS events, COUNT(DISTINCT l.admin_id) AS administrators, SUM(l.action IN ('shops.delete','owners.delete','plans.delete','settings.clear_carts')) AS deletions FROM superadmin_audit_logs l WHERE $whereSql", $params)[0];
$total = (int)$stats['events'];
$perPage = 25;
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)auditInput('page', '1')));
$offset = ($page - 1) * $perPage;
$events = saAuditRows($conn, "SELECT id,occurred_at,admin_name,admin_email,action,entity_type,entity_label,ip_address FROM superadmin_audit_logs l WHERE $whereSql ORDER BY occurred_at DESC,id DESC LIMIT $perPage OFFSET $offset", $params);
$admins = saAuditRows($conn, 'SELECT l.admin_id,l.admin_name FROM superadmin_audit_logs l JOIN (SELECT admin_id,MAX(id) AS last_id FROM superadmin_audit_logs GROUP BY admin_id) latest ON l.id=latest.last_id ORDER BY l.admin_name');
$shops = saAuditRows($conn, 'SELECT s.shop_id,s.shop_name FROM superadmin_audit_shops s JOIN (SELECT shop_id,MAX(audit_id) AS last_id FROM superadmin_audit_shops GROUP BY shop_id) latest ON s.shop_id=latest.shop_id AND s.audit_id=latest.last_id ORDER BY s.shop_name');
require __DIR__ . '/includes/sidebar.php';
?>
<link rel="stylesheet" href="assets/audit.css?v=1">
<form method="GET" class="card-glass audit-filters">
    <div class="audit-search"><label class="input-label" for="audit-search">Search activity</label><input id="audit-search" class="input-custom" type="search" name="q" value="<?= auditEscape($search) ?>" placeholder="Administrator, record or IP address" maxlength="160"></div>
    <div><label class="input-label" for="audit-action">Action</label><select id="audit-action" name="action" class="input-custom"><option value="">All actions</option><?php foreach ($actions as $key => $label): ?><option value="<?= auditEscape($key) ?>" <?= $action === $key ? 'selected' : '' ?>><?= auditEscape($label) ?></option><?php endforeach; ?></select></div>
    <div><label class="input-label" for="audit-admin">Administrator</label><select id="audit-admin" name="admin" class="input-custom"><option value="0">All administrators</option><?php foreach ($admins as $admin): ?><option value="<?= (int)$admin['admin_id'] ?>" <?= $adminId === (int)$admin['admin_id'] ? 'selected' : '' ?>><?= auditEscape($admin['admin_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="input-label" for="audit-shop">Affected shop</label><select id="audit-shop" name="shop" class="input-custom"><option value="0">All shops</option><?php foreach ($shops as $shop): ?><option value="<?= (int)$shop['shop_id'] ?>" <?= $shopId === (int)$shop['shop_id'] ? 'selected' : '' ?>><?= auditEscape($shop['shop_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="input-label" for="audit-from">From (IST)</label><input id="audit-from" type="date" name="from" class="input-custom" value="<?= auditEscape($from) ?>" required></div>
    <div><label class="input-label" for="audit-to">To (IST)</label><input id="audit-to" type="date" name="to" class="input-custom" value="<?= auditEscape($to) ?>" required></div>
    <div class="audit-filter-actions"><button class="btn-primary-custom" type="submit"><i class="bi bi-search"></i> Apply</button><a href="audit_logs.php" class="btn-ghost-custom"><i class="bi bi-arrow-counterclockwise"></i> Reset</a></div>
</form>
<?php if ($filterError): ?><div class="alert-error" role="alert"><i class="bi bi-exclamation-circle"></i><?= auditEscape($filterError) ?></div><?php endif; ?>
<div class="audit-stats">
    <div><span>Events in period</span><strong><?= number_format($total) ?></strong></div>
    <div><span>Administrators</span><strong><?= number_format((int)$stats['administrators']) ?></strong></div>
    <div><span>Deletion actions</span><strong><?= number_format((int)$stats['deletions']) ?></strong></div>
</div>
<section class="card-glass audit-list">
    <div class="audit-list-heading"><h2 class="section-title">Activity history</h2><span class="section-sub">Newest first &middot; IST</span></div>
    <?php if (!$events): ?>
    <div class="audit-empty"><i class="bi bi-clock-history" aria-hidden="true"></i><h2>No audit events in this period</h2></div>
    <?php else: ?>
    <div class="table-scroll" role="region" tabindex="0" aria-label="Administrator audit history">
        <table class="table-custom"><thead><tr><th>Recorded at</th><th>Administrator</th><th>Action</th><th>Record</th><th>IP address</th><th></th></tr></thead><tbody>
        <?php foreach ($events as $event): ?>
            <tr><td><time><?= auditEscape(auditTime($event['occurred_at'])) ?></time><small>#<?= (int)$event['id'] ?></small></td>
                <td><strong><?= auditEscape($event['admin_name']) ?></strong><small><?= auditEscape($event['admin_email']) ?></small></td>
                <td><span class="audit-action <?= in_array($event['action'], ['shops.delete','owners.delete','plans.delete','settings.clear_carts'], true) ? 'audit-action-danger' : '' ?>"><?= auditEscape($actions[$event['action']] ?? $event['action']) ?></span></td>
                <td><?= auditEscape($event['entity_label']) ?><small><?= auditEscape(ucwords(str_replace('_', ' ', $event['entity_type']))) ?></small></td>
                <td><?= auditEscape($event['ip_address'] ?: 'Unavailable') ?></td>
                <td><a href="<?= auditEscape(auditLink(['event' => $event['id']])) ?>" class="btn-ghost-custom" aria-label="View audit event <?= (int)$event['id'] ?>"><i class="bi bi-arrow-up-right"></i> Details</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>
    <div class="audit-pagination"><span><?= $total ? number_format($offset + 1) . '-' . number_format(min($offset + $perPage, $total)) : '0' ?> of <?= number_format($total) ?> events</span><nav aria-label="Audit pagination">
        <?php if ($page > 1): ?><a class="icon-button" href="<?= auditEscape(auditLink(['page' => $page - 1])) ?>" title="Previous page" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="icon-button" href="<?= auditEscape(auditLink(['page' => $page + 1])) ?>" title="Next page" aria-label="Next page"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
    </nav></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
