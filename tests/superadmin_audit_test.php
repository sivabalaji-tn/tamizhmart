<?php
// Run with: docker exec -w /var/www/html tamizhmart_php php tests/superadmin_audit_test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../superadmin/includes/audit.php';

if (($argv[1] ?? '') === 'endpoint') {
    $database = $argv[2] ?? '';
    if (!preg_match('/^tamizhmart_audit_test_[a-f0-9]{12}$/', $database)) exit(2);
    $payload = json_decode(base64_decode($argv[4] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    $conn->select_db($database);
    session_id('audit-test-' . bin2hex(random_bytes(10)));
    session_start();
    $_SESSION = $payload['anonymous'] ?? false ? [] : ['superadmin_id' => 1, 'superadmin_name' => 'Audit Tester', 'superadmin_email' => 'audit@example.test', 'sa_csrf' => 'test-csrf'];
    session_write_close();
    $_POST = $payload['post'] ?? [];
    $_GET = $payload['get'] ?? [];
    $_SERVER['REQUEST_METHOD'] = isset($payload['post']) ? 'POST' : 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['PHP_SELF'] = '/superadmin/' . $argv[3] . '.php';
    chdir(__DIR__ . '/../superadmin');
    ob_start();
    register_shutdown_function(function () {
        $html = ob_get_clean();
        echo $html;
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    });
    require $argv[3] . '.php';
    exit;
}

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function endpoint(string $database, string $page, array $payload): string {
    $command = [PHP_BINARY, __FILE__, 'endpoint', $database, $page, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0 || preg_match('/Fatal error|Parse error|Warning:|Notice:/', $body . $error)) throw new RuntimeException("Endpoint $page failed: $error " . substr($body, 0, 500));
    return $body;
}
function postAction(string $database, string $page, array $post): string {
    return endpoint($database, $page, ['post' => ['sa_csrf' => 'test-csrf'] + $post]);
}
function lastEvent(mysqli $conn): array { return $conn->query('SELECT * FROM superadmin_audit_logs ORDER BY id DESC LIMIT 1')->fetch_assoc(); }
function eventCount(mysqli $conn): int { return (int)$conn->query('SELECT COUNT(*) FROM superadmin_audit_logs')->fetch_row()[0]; }

$sourceDatabase = DB_NAME;
$database = 'tamizhmart_audit_test_' . bin2hex(random_bytes(6));
$created = false;
try {
    $conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
    $created = true;
    $tables = saAuditRows($conn, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'", [$sourceDatabase]);
    foreach ($tables as $table) {
        $name = $table['TABLE_NAME'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new RuntimeException('Unexpected table identifier.');
        $conn->query("CREATE TABLE `$database`.`$name` LIKE `$sourceDatabase`.`$name`");
    }
    $conn->select_db($database);
    saAuditEnsure($conn);
    $hash = password_hash('AuditTestPassword123!', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO super_admins (id,name,email,password) VALUES (1,'Audit Tester','audit@example.test',?)");
    $stmt->bind_param('s', $hash); $stmt->execute();
    $conn->query("INSERT INTO owners (id,name,email,password) VALUES (1,'Test Owner','owner@example.test','unused')");
    $conn->query("INSERT INTO shops (id,owner_id,name,slug) VALUES (1,1,'Test Shop','audit-test-shop'),(2,1,'Second Shop','audit-second-shop')");
    $conn->query("INSERT INTO plans (id,name,slug,price,duration_days,commission_rate,is_active,features) VALUES (1,'Test Plan','test-plan',100,30,2,1,'[]')");
    $conn->query("INSERT INTO platform_settings (setting_key,setting_value) VALUES ('maintenance_mode','0'),('site_name','Test Platform')");
    $conn->query("INSERT INTO email_campaign_allowances (shop_id,month_key,monthly_limit,used_count) VALUES (1,'2026-09',2,2)");

    postAction($database, 'shops', ['action' => 'suspend', 'shop_id' => 1]);
    $event = lastEvent($conn);
    check($event['action'] === 'shops.suspend' && (int)$event['admin_id'] === 1, 'Shop suspension records the actor and action');
    check(json_decode($event['before_data'], true)['is_active'] === 1 && json_decode($event['after_data'], true)['is_active'] === 0, 'Shop before/after values match the change');
    $count = eventCount($conn);
    postAction($database, 'shops', ['action' => 'suspend', 'shop_id' => 1]);
    check(eventCount($conn) === $count, 'No-op requests do not create misleading change events');
    postAction($database, 'shops', ['action' => 'activate', 'shop_id' => 1]);

    $count = eventCount($conn);
    endpoint($database, 'shops', ['post' => ['action' => 'suspend', 'shop_id' => 1], 'anonymous' => true]);
    endpoint($database, 'shops', ['post' => ['action' => 'suspend', 'shop_id' => 1, 'sa_csrf' => 'wrong']]);
    check(eventCount($conn) === $count && (int)$conn->query('SELECT is_active FROM shops WHERE id=1')->fetch_row()[0] === 1, 'Anonymous and forged requests cannot mutate records');

    postAction($database, 'owners', ['action' => 'suspend', 'owner_id' => 1]);
    $ownerEvent = lastEvent($conn);
    check(count(json_decode($ownerEvent['after_data'], true)['shops']) === 2, 'Owner changes include all affected shops');
    check((int)$conn->query('SELECT COUNT(*) FROM superadmin_audit_shops WHERE audit_id=' . (int)$ownerEvent['id'])->fetch_row()[0] === 2, 'Owner events are searchable by either affected shop');
    postAction($database, 'owners', ['action' => 'activate', 'owner_id' => 1]);

    postAction($database, 'campaigns', ['action' => 'reset_usage', 'shop_id' => 1, 'month_key' => '2026-09']);
    $event = lastEvent($conn);
    check($event['action'] === 'campaigns.reset_usage' && json_decode($event['before_data'], true)['used_count'] === 2 && json_decode($event['after_data'], true)['used_count'] === 0, 'Campaign reset records month and previous usage');
    postAction($database, 'campaigns', ['action' => 'update_limit', 'shop_id' => 1, 'month_key' => '2026-09', 'monthly_limit' => 5]);
    check(json_decode(lastEvent($conn)['after_data'], true)['monthly_limit'] === 5, 'Campaign limit edits are audited');

    $planData = ['name' => 'New <script>plan</script>', 'slug' => 'new-test-plan', 'price' => 250, 'duration_days' => 30, 'product_limit' => '', 'order_limit' => '', 'commission_rate' => 3, 'features' => 'Tracking', 'is_active' => 1];
    postAction($database, 'plans', ['action' => 'create'] + $planData);
    $newPlan = (int)lastEvent($conn)['entity_id'];
    check($newPlan > 1 && lastEvent($conn)['action'] === 'plans.create', 'New plan events use the actual created record ID');
    postAction($database, 'plans', ['action' => 'edit', 'id' => $newPlan, 'price' => 350] + $planData);
    check((float)json_decode(lastEvent($conn)['after_data'], true)['price'] === 350.0, 'Plan edits capture changed values');
    postAction($database, 'plans', ['action' => 'toggle', 'id' => $newPlan, 'current' => 0]);
    check((int)$conn->query("SELECT is_active FROM plans WHERE id=$newPlan")->fetch_row()[0] === 0, 'Plan toggles use the database state instead of a forged current value');
    postAction($database, 'plans', ['action' => 'delete', 'id' => $newPlan]);
    $deletedPlanEvent = lastEvent($conn);
    check($deletedPlanEvent['entity_label'] === $planData['name'] && json_decode($deletedPlanEvent['after_data'], true) === [], 'Deleted plans remain identifiable in history');

    $start = date('Y-m-d');
    postAction($database, 'subscriptions', ['action' => 'activate', 'shop_id' => 1, 'plan_id' => 1, 'start_date' => $start]);
    $subscriptionId = (int)$conn->query('SELECT MAX(id) FROM shop_subscriptions')->fetch_row()[0];
    check(lastEvent($conn)['action'] === 'subscriptions.activate' && (int)lastEvent($conn)['entity_id'] === $subscriptionId, 'Subscription activation records its subscription ID');
    postAction($database, 'subscriptions', ['action' => 'extend', 'sub_id' => $subscriptionId, 'extend_days' => 7]);
    check(lastEvent($conn)['action'] === 'subscriptions.extend', 'Subscription extension is audited');
    postAction($database, 'subscriptions', ['action' => 'suspend', 'shop_id' => 1]);
    postAction($database, 'subscriptions', ['action' => 'restore', 'shop_id' => 1]);
    check(lastEvent($conn)['action'] === 'subscriptions.restore', 'Subscription suspension and restoration are audited');
    $conn->query("INSERT INTO commission_log (shop_id,order_id,order_amount,commission_rate,commission_amount) VALUES (1,1,100,2,2)");
    postAction($database, 'subscriptions', ['action' => 'collect_commission', 'shop_id' => 1, 'sub_id' => $subscriptionId, 'note' => 'Collected']);
    check(lastEvent($conn)['action'] === 'subscriptions.collect_commission' && (int)$conn->query('SELECT collected FROM commission_log WHERE id=1')->fetch_row()[0] === 1, 'Commission collection and its audit record commit together');

    postAction($database, 'settings', ['action' => 'save_settings', 'site_name' => 'Updated Platform', 'site_city' => 'Madurai', 'contact_email' => 'test@example.test', 'registration_open' => 1]);
    check(lastEvent($conn)['action'] === 'settings.save_settings', 'Platform setting changes are audited');
    postAction($database, 'settings', ['action' => 'change_password', 'current_password' => 'AuditTestPassword123!', 'new_password' => 'ChangedPassword456!', 'confirm_password' => 'ChangedPassword456!']);
    $passwordEvent = lastEvent($conn);
    check($passwordEvent['action'] === 'settings.change_password' && !str_contains(json_encode($passwordEvent), 'ChangedPassword') && !str_contains(json_encode($passwordEvent), '$2y$'), 'Password events exclude passwords and hashes');
    $conn->query('INSERT INTO cart (user_id,shop_id,product_id,quantity) VALUES (1,1,1,2)');
    postAction($database, 'settings', ['action' => 'clear_carts']);
    check(json_decode(lastEvent($conn)['before_data'], true)['cart_items'] === 1 && json_decode(lastEvent($conn)['after_data'], true)['cart_items'] === 0, 'Cart clearing records the removed item count');

    $count = eventCount($conn);
    $conn->query("CREATE TRIGGER audit_test_reject BEFORE INSERT ON superadmin_audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test audit failure'");
    $body = postAction($database, 'shops', ['action' => 'suspend', 'shop_id' => 1]);
    check(str_contains($body, 'No changes were applied') && eventCount($conn) === $count && (int)$conn->query('SELECT is_suspended FROM shops WHERE id=1')->fetch_row()[0] === 0, 'Audit write failure rolls back the business operation');
    $conn->query('DROP TRIGGER audit_test_reject');
    $count = eventCount($conn);
    postAction($database, 'subscriptions', ['action' => 'activate', 'shop_id' => 1, 'plan_id' => 999, 'start_date' => $start]);
    postAction($database, 'shops', ['action' => 'suspend', 'shop_id' => 999]);
    check(eventCount($conn) === $count, 'Rejected actions do not appear as successful changes');

    endpoint($database, 'login', ['anonymous' => true, 'post' => ['email' => 'audit@example.test', 'password' => 'ChangedPassword456!']]);
    check(lastEvent($conn)['action'] === 'auth.login', 'Successful sign-in is recorded');
    endpoint($database, 'logout', []);
    check(lastEvent($conn)['action'] === 'auth.logout', 'Sign-out is recorded');
    $list = endpoint($database, 'audit_logs', ['get' => ['shop' => 2, 'action' => 'owners.suspend']]);
    check(str_contains($list, 'View audit event ' . $ownerEvent['id']), 'Affected-shop filter includes owner actions');
    $detail = endpoint($database, 'audit_logs', ['get' => ['event' => $deletedPlanEvent['id']]]);
    check(str_contains($detail, '&lt;script&gt;') && !str_contains($detail, '<script>plan</script>'), 'Audit values are HTML escaped');
    check(str_contains(endpoint($database, 'audit_logs', ['get' => ['q' => 'no-matching-audit-event']]), 'No audit events'), 'Search has a usable empty state');
    check(str_contains(endpoint($database, 'audit_logs', ['get' => ['from' => '2026-02-30']]), 'Select a valid date range'), 'Invalid dates are rejected');
    check(!str_contains(endpoint($database, 'audit_logs', ['anonymous' => true]), 'Activity history'), 'Audit history is restricted to administrators');
    $count = eventCount($conn);
    endpoint($database, 'reset', ['anonymous' => true]);
    check(eventCount($conn) === $count, 'Legacy reset endpoint cannot bypass the audited password flow');
    postAction($database, 'owners', ['action' => 'delete', 'owner_id' => 1]);
    check(lastEvent($conn)['action'] === 'owners.delete' && eventCount($conn) > $count, 'Owner deletion preserves the audit history');
    postAction($database, 'shops', ['action' => 'delete', 'shop_id' => 2]);
    check(lastEvent($conn)['action'] === 'shops.delete', 'Shop deletion is recorded');
    $deletedShopCount = (int)$conn->query('SELECT COUNT(*) FROM superadmin_audit_shops WHERE shop_id=2')->fetch_row()[0];
    check($deletedShopCount > 0, 'Affected-shop references survive shop deletion');
    $_SESSION = ['superadmin_id' => 1];
    $context = ['action' => 'auth.login', 'entity_type' => 'administrator', 'entity_id' => 1, 'entity_label' => 'Administrator account', 'shop_id' => null, 'shop_name' => null, 'source_page' => 'login.php'];
    for ($i = 0; $i < 28; $i++) saAuditWrite($conn, $context, [], [], 'Pagination fixture');
    $pagination = endpoint($database, 'audit_logs', []);
    check(str_contains($pagination, 'Next page'), 'History is paginated');
    check(str_contains(endpoint($database, 'audit_logs', ['get' => ['page' => '2']]), 'Previous page'), 'Older events remain reachable');
    saAuditWrite($conn, $context, [], [], 'Boundary outside');
    $boundaryOutside = (int)lastEvent($conn)['id'];
    $conn->query("UPDATE superadmin_audit_logs SET occurred_at='2026-09-10 18:29:59' WHERE id=$boundaryOutside");
    saAuditWrite($conn, $context, [], [], 'Boundary inside');
    $boundaryInside = (int)lastEvent($conn)['id'];
    $conn->query("UPDATE superadmin_audit_logs SET occurred_at='2026-09-10 18:30:00' WHERE id=$boundaryInside");
    $boundaryPage = endpoint($database, 'audit_logs', ['get' => ['from' => '2026-09-11', 'to' => '2026-09-11', 'q' => 'Boundary']]);
    check(str_contains($boundaryPage, 'View audit event ' . $boundaryInside) && !str_contains($boundaryPage, 'View audit event ' . $boundaryOutside), 'Date filtering uses IST day boundaries over UTC storage');
    if (in_array('--render-fixtures', $argv, true)) {
        $directory = sys_get_temp_dir() . '/tamizhmart-audit-preview';
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        file_put_contents($directory . '/list.html', $pagination);
        file_put_contents($directory . '/detail.html', endpoint($database, 'audit_logs', ['get' => ['event' => $ownerEvent['id']]]));
        file_put_contents($directory . '/empty.html', endpoint($database, 'audit_logs', ['get' => ['q' => 'no-matching-audit-event']]));
        file_put_contents($directory . '/deleted.html', $detail);
        echo "Synthetic preview fixtures: $directory\n";
    }
    echo "All audit integration checks passed.\n";
} finally {
    if ($created && preg_match('/^tamizhmart_audit_test_[a-f0-9]{12}$/', $database)) {
        $conn->select_db($sourceDatabase);
        $conn->query("DROP DATABASE `$database`");
        echo "Temporary test database removed.\n";
    }
}
