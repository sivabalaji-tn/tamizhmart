<?php
// Snapshot fields are explicitly selected. Never pass raw requests or password rows into the log.
function saAuditEnsure(mysqli $conn): void
{
    static $ready = false;
    if ($ready) return;
    $conn->query(file_get_contents(__DIR__ . '/../../databasefile/add_superadmin_audit_logs.sql'));
    $conn->query(file_get_contents(__DIR__ . '/../../databasefile/add_superadmin_audit_shop_links.sql'));
    $ready = true;
}

function saAuditRequireAdmin(): void
{
    if (empty($_SESSION['superadmin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function saAuditCsrfField(): string
{
    $_SESSION['sa_csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="sa_csrf" value="' . htmlspecialchars($_SESSION['sa_csrf'], ENT_QUOTES, 'UTF-8') . '">';
}

function saAuditActions(): array
{
    return [
        'shops.suspend' => 'Shop suspended', 'shops.activate' => 'Shop restored', 'shops.delete' => 'Shop deleted',
        'owners.suspend' => 'Owner suspended', 'owners.activate' => 'Owner restored', 'owners.delete' => 'Owner deleted',
        'plans.create' => 'Plan created', 'plans.edit' => 'Plan updated', 'plans.toggle' => 'Plan availability changed', 'plans.delete' => 'Plan deleted',
        'subscriptions.activate' => 'Subscription activated', 'subscriptions.suspend' => 'Subscription suspended',
        'subscriptions.restore' => 'Subscription restored', 'subscriptions.extend' => 'Subscription extended',
        'subscriptions.collect_commission' => 'Commission collected',
        'campaigns.reset_usage' => 'Campaign allowance reset', 'campaigns.update_limit' => 'Campaign limit changed',
        'settings.save_settings' => 'Platform settings changed', 'settings.change_password' => 'Admin password changed',
        'settings.clear_carts' => 'Cart data cleared', 'auth.login' => 'Admin signed in', 'auth.logout' => 'Admin signed out',
    ];
}

function saAuditRows(mysqli $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function saAuditIndexed(array $rows): array
{
    $indexed = [];
    foreach ($rows as $row) $indexed[$row['id']] = $row;
    return $indexed;
}

function saAuditSnapshot(mysqli $conn, array $context): array
{
    $id = $context['entity_id'];
    switch ($context['entity_type']) {
        case 'shop':
            return saAuditRows($conn, 'SELECT id,name,slug,owner_id,is_active,is_suspended FROM shops WHERE id=? FOR UPDATE', [$id])[0] ?? [];
        case 'owner':
            $owner = saAuditRows($conn, 'SELECT id,name,email,is_suspended FROM owners WHERE id=? FOR UPDATE', [$id])[0] ?? [];
            if (!$owner) return [];
            $owner['shops'] = saAuditIndexed(saAuditRows($conn, 'SELECT id,name,is_active,is_suspended FROM shops WHERE owner_id=? ORDER BY id FOR UPDATE', [$id]));
            return $owner;
        case 'plan':
            return saAuditRows($conn, 'SELECT id,name,slug,price,duration_days,product_limit,order_limit,commission_rate,features,is_active,sort_order FROM plans WHERE id=? FOR UPDATE', [$id])[0] ?? [];
        case 'subscription':
            $shop = saAuditRows($conn, 'SELECT id,name,is_active,is_suspended FROM shops WHERE id=? FOR UPDATE', [$context['shop_id']])[0] ?? [];
            if (!$shop) return [];
            return [
                'shop' => $shop,
                'subscriptions' => saAuditIndexed(saAuditRows($conn, 'SELECT id,plan_id,status,started_at,expires_at,grace_until,payment_ref,activated_by FROM shop_subscriptions WHERE shop_id=? ORDER BY id FOR UPDATE', [$context['shop_id']])),
                'collections' => saAuditIndexed(saAuditRows($conn, 'SELECT id,subscription_id,total_revenue,order_count,commission_amount,commission_rate,collected_by FROM commission_collections WHERE shop_id=? ORDER BY id FOR UPDATE', [$context['shop_id']])),
            ];
        case 'campaign_allowance':
            return saAuditRows($conn, 'SELECT shop_id,month_key,monthly_limit,used_count,reset_count,reset_by,reset_at FROM email_campaign_allowances WHERE shop_id=? AND month_key=? FOR UPDATE', [$context['shop_id'], $context['month_key']])[0] ?? [];
        case 'platform':
            $settings = [];
            foreach (saAuditRows($conn, "SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN ('site_name','site_city','contact_email','maintenance_mode','maintenance_message','registration_open') ORDER BY setting_key FOR UPDATE") as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        case 'cart':
            return ['cart_items' => (int)$conn->query('SELECT COUNT(*) FROM cart')->fetch_row()[0]];
        case 'administrator':
            return ['password' => '[Not recorded]'];
    }
    return [];
}

function saAuditStart(mysqli $conn, string $module, array $post): array
{
    saAuditRequireAdmin();
    if (!is_string($post['sa_csrf'] ?? null) || !hash_equals($_SESSION['sa_csrf'] ?? '', $post['sa_csrf']) || empty($_SESSION['sa_csrf'])) {
        throw new InvalidArgumentException('Your form expired. Refresh the page and try again.');
    }
    $action = $module . '.' . ($post['action'] ?? '');
    if (!isset(saAuditActions()[$action]) || $module === 'auth') throw new InvalidArgumentException('Unknown administrator action.');
    saAuditEnsure($conn);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    $context = ['action' => $action, 'entity_id' => null, 'entity_label' => '', 'shop_id' => null, 'shop_name' => null, 'source_page' => $module . '.php'];
    if ($module === 'shops' || $module === 'owners' || $module === 'plans') {
        $context['entity_type'] = ['shops' => 'shop', 'owners' => 'owner', 'plans' => 'plan'][$module];
        $context['entity_id'] = (int)($post[['shops' => 'shop_id', 'owners' => 'owner_id', 'plans' => 'id'][$module]] ?? 0);
        if ($action === 'plans.create') $context['entity_id'] = 0;
        $context['before'] = saAuditSnapshot($conn, $context);
        if (!$context['before'] && $action !== 'plans.create') throw new InvalidArgumentException('The selected record no longer exists.');
        $context['entity_label'] = $context['before']['name'] ?? trim($post['name'] ?? 'New plan');
        if ($module === 'shops') {
            $context['shop_id'] = $context['entity_id'];
            $context['shop_name'] = $context['entity_label'];
        }
    } elseif ($module === 'subscriptions' || $module === 'campaigns') {
        $shopId = (int)($post['shop_id'] ?? 0);
        $subId = (int)($post['sub_id'] ?? 0);
        if ($action === 'subscriptions.extend') {
            $sub = saAuditRows($conn, 'SELECT shop_id FROM shop_subscriptions WHERE id=?', [$subId])[0] ?? null;
            if (!$sub) throw new InvalidArgumentException('Subscription not found.');
            $shopId = (int)$sub['shop_id'];
            if ((int)($post['extend_days'] ?? 30) < 1 || (int)($post['extend_days'] ?? 30) > 365) throw new InvalidArgumentException('Extension must be between 1 and 365 days.');
        }
        $shop = saAuditRows($conn, 'SELECT id,name FROM shops WHERE id=? FOR UPDATE', [$shopId])[0] ?? null;
        if (!$shop) throw new InvalidArgumentException('Shop not found.');
        $context['shop_id'] = $shopId;
        $context['shop_name'] = $shop['name'];
        $context['entity_type'] = $module === 'campaigns' ? 'campaign_allowance' : 'subscription';
        $context['entity_id'] = $subId ?: $shopId;
        $context['entity_label'] = $shop['name'];
        if ($module === 'campaigns') {
            $month = $post['month_key'] ?? '';
            if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new InvalidArgumentException('Select a valid campaign month.');
            $context['month_key'] = $month;
            $context['entity_label'] .= ' / ' . $month;
            $stmt = $conn->prepare('INSERT IGNORE INTO email_campaign_allowances (shop_id,month_key,monthly_limit,used_count) VALUES (?,?,2,0)');
            $stmt->bind_param('is', $shopId, $month);
            $stmt->execute();
        }
        $context['before'] = saAuditSnapshot($conn, $context);
        if ($module === 'subscriptions' && !$subId) {
            $context['entity_id'] = null;
            foreach (array_reverse($context['before']['subscriptions'], true) as $subscription) {
                if (in_array($subscription['status'], ['active', 'trial', 'grace', 'suspended'], true)) {
                    $context['entity_id'] = (int)$subscription['id'];
                    break;
                }
            }
        }
    } else {
        $context['entity_type'] = ['settings.save_settings' => 'platform', 'settings.change_password' => 'administrator', 'settings.clear_carts' => 'cart'][$action];
        $context['entity_label'] = $context['entity_type'] === 'administrator' ? 'Administrator account' : 'TamizhMart platform';
        $context['entity_id'] = $context['entity_type'] === 'administrator' ? (int)$_SESSION['superadmin_id'] : null;
        $context['before'] = saAuditSnapshot($conn, $context);
    }
    return $context;
}

function saAuditWrite(mysqli $conn, array $context, array $before, array $after, string $summary): void
{
    $admin = saAuditRows($conn, 'SELECT id,name,email FROM super_admins WHERE id=?', [(int)($_SESSION['superadmin_id'] ?? 0)])[0] ?? null;
    if (!$admin) throw new RuntimeException('Administrator account is unavailable.');
    $beforeJson = json_encode($before, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    $afterJson = json_encode($after, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
    $stmt = $conn->prepare('INSERT INTO superadmin_audit_logs (occurred_at,admin_id,admin_name,admin_email,action,entity_type,entity_id,entity_label,shop_id,shop_name,summary,before_data,after_data,ip_address,source_page) VALUES (UTC_TIMESTAMP(6),?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('issssisissssss', $admin['id'], $admin['name'], $admin['email'], $context['action'], $context['entity_type'], $context['entity_id'], $context['entity_label'], $context['shop_id'], $context['shop_name'], $summary, $beforeJson, $afterJson, $ip, $context['source_page']);
    $stmt->execute();
    $eventId = (int)$conn->insert_id;
    $shops = $context['shop_id'] ? [$context['shop_id'] => $context['shop_name']] : [];
    if ($context['entity_type'] === 'owner') {
        foreach ($before['shops'] ?? [] as $shop) $shops[$shop['id']] = $shop['name'];
    }
    $link = $conn->prepare('INSERT INTO superadmin_audit_shops (audit_id,shop_id,shop_name) VALUES (?,?,?)');
    foreach ($shops as $shopId => $shopName) {
        $link->bind_param('iis', $eventId, $shopId, $shopName);
        $link->execute();
    }
}

function saAuditFinish(mysqli $conn, array $context, string $success, string $error = '', ?int $createdId = null): void
{
    if ($error !== '' || $success === '') { $conn->rollback(); return; }
    if ($context['action'] === 'plans.create') {
        $context['entity_id'] = $createdId;
        if (!$createdId) throw new RuntimeException('Created plan was not identified.');
    }
    $after = saAuditSnapshot($conn, $context);
    if ($context['action'] === 'subscriptions.activate') $context['entity_id'] = (int)array_key_last($after['subscriptions']);
    if ($context['before'] !== $after || $context['action'] === 'settings.change_password') {
        saAuditWrite($conn, $context, $context['before'], $after, saAuditActions()[$context['action']] . ': ' . $context['entity_label']);
    }
    $conn->commit();
}

function saAuditFailure(mysqli $conn, Throwable $exception): string
{
    $conn->rollback();
    if ($exception instanceof InvalidArgumentException) return $exception->getMessage();
    error_log('Superadmin audited action failed: ' . get_class($exception) . ' [' . $exception->getCode() . ']');
    return 'The change could not be saved with its audit record. No changes were applied. Please try again.';
}

function saAuditAuth(mysqli $conn, string $action): void
{
    saAuditEnsure($conn);
    $context = ['action' => 'auth.' . $action, 'entity_type' => 'administrator', 'entity_id' => (int)$_SESSION['superadmin_id'], 'entity_label' => 'Administrator account', 'shop_id' => null, 'shop_name' => null, 'source_page' => $action . '.php'];
    saAuditWrite($conn, $context, [], [], saAuditActions()[$context['action']]);
}
