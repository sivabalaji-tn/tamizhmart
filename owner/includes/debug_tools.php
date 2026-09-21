<?php
require_once __DIR__ . '/debug_access.php';

function odQuery(mysqli $conn, string $sql, array $values = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($values) $stmt->bind_param(str_repeat('s', count($values)), ...$values);
    $stmt->execute();
    return $stmt;
}

function odTables(mysqli $conn): array
{
    $rows = odQuery($conn, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->get_result()->fetch_all(MYSQLI_ASSOC);
    return array_column($rows, 'TABLE_NAME');
}

function odShop(mysqli $conn, int $ownerId, int $shopId, bool $lock = false): array
{
    $row = odQuery($conn, 'SELECT s.*,o.password AS owner_password,o.is_suspended AS owner_suspended FROM shops s JOIN owners o ON o.id=s.owner_id WHERE s.id=? AND o.id=?' . ($lock ? ' FOR UPDATE' : ''), [$shopId, $ownerId])->get_result()->fetch_assoc();
    if (!$row || $row['owner_suspended'] || $row['is_suspended']) throw new InvalidArgumentException('This shop is not available to your owner account.');
    return $row;
}

function odCounts(mysqli $conn, int $shopId): array
{
    $counts = [];
    $tables = odTables($conn);
    foreach (['products','categories','orders','users','cart','coupons','popups','email_campaigns','email_campaign_allowances','shop_handlers','shop_settings','shop_subscriptions','commission_log','commission_collections','handler_activity_log','handler_cod_settlements','password_resets'] as $table) {
        $counts[$table] = in_array($table, $tables, true) ? (int)odQuery($conn, "SELECT COUNT(*) FROM `$table` WHERE shop_id=?", [$shopId])->get_result()->fetch_row()[0] : 0;
    }
    return $counts;
}

function odDelete(mysqli $conn, string $table, int $shopId, array $tables): void
{
    $allowed = ['cart','categories','commission_collections','commission_log','coupons','email_campaigns','email_campaign_allowances','handler_activity_log','handler_cod_settlements','orders','password_resets','popups','products','shop_handlers','shop_settings','shop_subscriptions','users'];
    if (!in_array($table, $allowed, true)) throw new LogicException('Unsupported cleanup table.');
    if (in_array($table, $tables, true)) odQuery($conn, "DELETE FROM `$table` WHERE shop_id=?", [$shopId]);
}

function odClearOrders(mysqli $conn, int $shopId, array $tables): void
{
    foreach (['delivery_otps','order_items'] as $table) {
        if (in_array($table, $tables, true)) odQuery($conn, "DELETE d FROM `$table` d JOIN orders o ON o.id=d.order_id WHERE o.shop_id=?", [$shopId]);
    }
    foreach (['handler_activity_log','handler_cod_settlements','commission_log','commission_collections','orders'] as $table) odDelete($conn, $table, $shopId, $tables);
    if (in_array('shop_handlers', $tables, true)) odQuery($conn, 'UPDATE shop_handlers SET cod_wallet=0 WHERE shop_id=?', [$shopId]);
    if (in_array('coupons', $tables, true)) odQuery($conn, 'UPDATE coupons SET used_count=0 WHERE shop_id=?', [$shopId]);
}

function odAssertIsolation(mysqli $conn, int $shopId, array $tables): void
{
    $checks = [
        [['orders','users'], 'SELECT 1 FROM orders a JOIN users b ON b.id=a.user_id WHERE a.shop_id<>b.shop_id AND (a.shop_id=? OR b.shop_id=?) LIMIT 1'],
        [['order_items','orders','products'], 'SELECT 1 FROM order_items d JOIN orders a ON a.id=d.order_id JOIN products b ON b.id=d.product_id WHERE a.shop_id<>b.shop_id AND (a.shop_id=? OR b.shop_id=?) LIMIT 1'],
        [['products','categories'], 'SELECT 1 FROM products a JOIN categories b ON b.id=a.category_id WHERE a.shop_id<>b.shop_id AND (a.shop_id=? OR b.shop_id=?) LIMIT 1'],
        [['cart','products'], 'SELECT 1 FROM cart a JOIN products b ON b.id=a.product_id WHERE a.shop_id<>b.shop_id AND (a.shop_id=? OR b.shop_id=?) LIMIT 1'],
        [['cart','users'], 'SELECT 1 FROM cart a JOIN users b ON b.id=a.user_id WHERE a.shop_id<>b.shop_id AND (a.shop_id=? OR b.shop_id=?) LIMIT 1'],
        [['email_campaign_products','email_campaigns','products'], 'SELECT 1 FROM email_campaign_products d JOIN email_campaigns a ON a.id=d.campaign_id JOIN products b ON b.id=d.product_id WHERE a.shop_id<>b.shop_id AND (a.shop_id=? OR b.shop_id=?) LIMIT 1'],
        [['email_campaign_recipients','email_campaigns','users'], 'SELECT 1 FROM email_campaign_recipients d JOIN email_campaigns a ON a.id=d.campaign_id JOIN users b ON b.id=d.user_id WHERE a.shop_id<>b.shop_id AND (a.shop_id=? OR b.shop_id=?) LIMIT 1'],
    ];
    foreach ($checks as [$required, $sql]) {
        if (!array_diff($required, $tables) && odQuery($conn, $sql, [$shopId,$shopId])->get_result()->num_rows) throw new InvalidArgumentException('Cleanup stopped because this shop has cross-shop data references. Resolve those references before resetting.');
    }
}

function odClearPromotions(mysqli $conn, int $shopId, array $tables): void
{
    foreach (['email_campaign_products','email_campaign_recipients'] as $table) {
        if (in_array($table, $tables, true)) odQuery($conn, "DELETE d FROM `$table` d JOIN email_campaigns c ON c.id=d.campaign_id WHERE c.shop_id=?", [$shopId]);
    }
    foreach (['email_campaigns','email_campaign_allowances','coupons','popups'] as $table) odDelete($conn, $table, $shopId, $tables);
}

function odClearCatalogue(mysqli $conn, int $shopId, array $tables): void
{
    if ((int)odQuery($conn, 'SELECT COUNT(*) FROM orders WHERE shop_id=?', [$shopId])->get_result()->fetch_row()[0]) throw new InvalidArgumentException('Clear orders before deleting the catalogue, or use the full shop reset.');
    if (in_array('email_campaign_products', $tables, true)) odQuery($conn, 'DELETE d FROM email_campaign_products d JOIN products p ON p.id=d.product_id WHERE p.shop_id=?', [$shopId]);
    odDelete($conn, 'cart', $shopId, $tables);
    odDelete($conn, 'products', $shopId, $tables);
    odDelete($conn, 'categories', $shopId, $tables);
}

function odClearCustomers(mysqli $conn, int $shopId, array $tables): void
{
    if ((int)odQuery($conn, 'SELECT COUNT(*) FROM orders WHERE shop_id=?', [$shopId])->get_result()->fetch_row()[0]) throw new InvalidArgumentException('Clear orders before deleting customers, or use the full shop reset.');
    if (in_array('email_campaign_recipients', $tables, true)) odQuery($conn, 'DELETE d FROM email_campaign_recipients d JOIN users u ON u.id=d.user_id WHERE u.shop_id=?', [$shopId]);
    foreach (['cart','password_resets','users'] as $table) odDelete($conn, $table, $shopId, $tables);
}

function odProductScope(mysqli $conn, int $shopId, array $post): array
{
    $scope = $post['scope'] ?? 'all';
    $where = 'shop_id=?'; $values = [$shopId];
    if ($scope === 'product' || $scope === 'category') {
        $id = filter_var($post[$scope . '_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $table = $scope === 'product' ? 'products' : 'categories';
        if (!$id || !odQuery($conn, "SELECT id FROM `$table` WHERE id=? AND shop_id=?", [$id, $shopId])->get_result()->num_rows) throw new InvalidArgumentException('Select a product or category belonging to this shop.');
        $where .= $scope === 'product' ? ' AND id=?' : ' AND category_id=?';
        $values[] = $id;
    } elseif ($scope !== 'all') throw new InvalidArgumentException('Select a valid product scope.');
    return [$where, $values];
}

function odNumber($value, int $min, int $max): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($number === false) throw new InvalidArgumentException("Enter a whole number between $min and $max.");
    return $number;
}

function odMedia(mysqli $conn, int $shopId, array $groups): array
{
    $media = [];
    $sources = ['catalogue' => [['products','image','products'],['categories','image','categories']], 'promotions' => [['popups','image','popups']], 'branding' => [['shops','logo','logos'],['shops','banner','banners']]];
    foreach ($groups as $group) foreach ($sources[$group] as [$table, $column, $folder]) {
        $key = $table === 'shops' ? 'id' : 'shop_id';
        foreach (odQuery($conn, "SELECT `$column` AS filename FROM `$table` WHERE `$key`=?", [$shopId])->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $filename = $row['filename'] ?? '';
            if ($filename !== '' && basename($filename) === $filename && !str_contains($filename, '\\') && !str_contains($filename, ':')) $media[$folder . '/' . $filename] = [$folder, $filename];
        }
    }
    return array_values($media);
}

function odRemoveMedia(mysqli $conn, array $media, string $uploadRoot): array
{
    $removed = 0; $retained = 0;
    $root = realpath($uploadRoot);
    if (!$root) return ['removed' => 0, 'retained' => count($media)];
    foreach ($media as [$folder, $filename]) {
        if (!in_array($folder, ['products','categories','popups','logos','banners'], true) || basename($filename) !== $filename || str_contains($filename, '\\') || str_contains($filename, ':')) { $retained++; continue; }
        $path = $root . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename;
        $resolved = realpath($path);
        if (!$resolved || !is_file($resolved)) continue;
        if (is_link($path) || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) || dirname($resolved) !== $root . DIRECTORY_SEPARATOR . $folder) { $retained++; continue; }
        $shared = false;
        foreach ([['products','image'],['categories','image'],['popups','image'],['shops','logo'],['shops','banner']] as [$table,$column]) {
            if (odQuery($conn, "SELECT 1 FROM `$table` WHERE `$column`=? LIMIT 1", [$filename])->get_result()->num_rows) { $shared = true; break; }
        }
        if (!$shared && odQuery($conn, 'SELECT 1 FROM shop_settings WHERE setting_value LIKE ? LIMIT 1', ['%' . $filename . '%'])->get_result()->num_rows) $shared = true;
        if ($shared || !@unlink($resolved)) $retained++; else $removed++;
    }
    return compact('removed', 'retained');
}

function odSeed(mysqli $conn, int $shopId, string $action, int $count): string
{
    $batch = bin2hex(random_bytes(5));
    if ($action === 'seed_products') {
        odQuery($conn, "INSERT INTO categories (shop_id,name,is_active) VALUES (?, '[TEST] Catalogue',1)", [$shopId]);
        $category = (int)$conn->insert_id;
        $names = ['Cotton T-shirt','Steel Bottle','Notebook','Canvas Bag','Coffee Mug','Desk Organizer','Storage Box','Travel Pouch'];
        for ($i = 0; $i < $count; $i++) {
            $price = 100 + $i * 25;
            $stock = [0,3,100][$i % 3];
            odQuery($conn, 'INSERT INTO products (shop_id,category_id,name,description,price,discount_price,stock,sku,is_active) VALUES (?,?,?,?,?,?,?,?,1)', [$shopId,$category,'[TEST] ' . $names[$i % count($names)] . ' ' . ($i + 1),'Generated test product: ' . $batch,$price,$i % 2 ? $price - 10 : null,$stock,'DEBUG-' . $batch . '-' . $i]);
        }
        return "$count test products created with out-of-stock, low-stock and available stock cases.";
    }
    if ($action === 'seed_customers') {
        for ($i = 0; $i < $count; $i++) {
            $password = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
            odQuery($conn, 'INSERT INTO users (shop_id,name,email,password,address,is_active) VALUES (?,?,?,?,?,0)', [$shopId,'[TEST] Customer ' . ($i + 1),'debug-' . $batch . '-' . $i . '@example.invalid',$password,'Test address']);
        }
        return "$count inactive test customers created using non-deliverable email addresses.";
    }
    $customer = odQuery($conn, "SELECT id FROM users WHERE shop_id=? AND email LIKE 'debug-%@example.invalid' AND is_active=0 ORDER BY id LIMIT 1", [$shopId])->get_result()->fetch_assoc();
    $products = odQuery($conn, "SELECT id,price FROM products WHERE shop_id=? AND sku LIKE 'DEBUG-%' ORDER BY id LIMIT 10", [$shopId])->get_result()->fetch_all(MYSQLI_ASSOC);
    if (!$customer || !$products) throw new InvalidArgumentException('Create test products and test customers before generating sample orders.');
    $nextNumber = (int)odQuery($conn, 'SELECT COALESCE(MAX(shop_order_number),0)+1 FROM orders WHERE shop_id=?', [$shopId])->get_result()->fetch_row()[0];
    for ($i = 0; $i < $count; $i++) {
        $product = $products[$i % count($products)];
        $status = ['pending','processing','delivered','cancelled'][$i % 4];
        odQuery($conn, 'INSERT INTO orders (shop_id,user_id,shop_order_number,total_amount,status,payment_method,payment_status,address,notes,created_at) VALUES (?,?,?,?,?,\'cod\',?,?,?,DATE_SUB(NOW(), INTERVAL ? DAY))', [$shopId,$customer['id'],$nextNumber + $i,$product['price'],$status,$status === 'delivered' ? 'paid' : 'pending','Test address','[DEBUG] Synthetic order; no payment, notification or inventory movement.', $i % 14]);
        $orderId = (int)$conn->insert_id;
        odQuery($conn, 'INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,1,?)', [$orderId,$product['id'],$product['price']]);
    }
    return "$count sample COD orders created. No messages, payments or stock movements were triggered.";
}

function odRun(mysqli $conn, int $ownerId, int $shopId, array $post, string $csrf): array
{
    if (!ownerDebugEnabled()) throw new InvalidArgumentException('Debug tools are disabled in this environment.');
    if ($csrf === '' || !is_string($post['csrf'] ?? null) || !hash_equals($csrf, $post['csrf'])) throw new InvalidArgumentException('The form expired. Refresh the page and try again.');
    $key = $post['request_key'] ?? '';
    if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/', $key)) throw new InvalidArgumentException('Invalid request. Refresh the page.');
    $action = is_string($post['action'] ?? null) ? $post['action'] : '';
    $allowed = ['stock_set','stock_add','visibility','remove_discounts','seed_products','seed_customers','seed_orders','clear_carts','reset_coupon_usage','reset_campaign_usage','clear_orders','clear_catalogue','clear_customers','clear_promotions','reset_appearance','reset_shop'];
    if (!in_array($action, $allowed, true)) throw new InvalidArgumentException('Select a valid debug action.');
    $media = [];
    $conn->begin_transaction();
    try {
        $shop = odShop($conn, $ownerId, $shopId, true);
        $existing = odQuery($conn, 'SELECT summary,action FROM owner_debug_logs WHERE shop_id=? AND request_key=?', [$shopId,$key])->get_result()->fetch_assoc();
        if ($existing) {
            if ($existing['action'] !== $action) throw new InvalidArgumentException('This request has already been used. Refresh the page.');
            $conn->rollback();
            return ['message' => 'Already completed: ' . $existing['summary'], 'media' => []];
        }
        $destructive = ['clear_orders','clear_catalogue','clear_customers','clear_promotions','reset_appearance','reset_shop'];
        if (in_array($action, $destructive, true) && ($post['confirmation'] ?? '') !== $shop['slug']) throw new InvalidArgumentException('Type the exact shop slug to confirm this cleanup.');
        if ($action === 'reset_shop' && (empty($post['acknowledge']) || !is_string($post['password'] ?? null) || !password_verify($post['password'], $shop['owner_password']))) throw new InvalidArgumentException('Confirm the permanent reset and enter your current owner password.');
        $tables = odTables($conn);
        if (in_array($action, ['clear_orders','clear_catalogue','clear_customers','clear_promotions','reset_shop'], true)) odAssertIsolation($conn, $shopId, $tables);
        $before = odCounts($conn, $shopId);
        $details = ['before' => $before];
        if (in_array($action, ['stock_set','stock_add','visibility','remove_discounts'], true)) {
            [$where,$values] = odProductScope($conn, $shopId, $post);
            $matched = (int)odQuery($conn, "SELECT COUNT(*) FROM products WHERE $where", $values)->get_result()->fetch_row()[0];
            if (!$matched) throw new InvalidArgumentException('No products match this selection.');
            if ($action === 'stock_set' || $action === 'stock_add') {
                $quantity = odNumber($post['quantity'] ?? '', $action === 'stock_add' ? 1 : 0, 1000000);
                if ($action === 'stock_add' && odQuery($conn, "SELECT id FROM products WHERE $where AND COALESCE(stock,0)>? LIMIT 1", [...$values,1000000 - $quantity])->get_result()->num_rows) throw new InvalidArgumentException('This addition would exceed the stock limit of 1,000,000.');
                $expression = $action === 'stock_set' ? '?' : 'COALESCE(stock,0)+?';
                odQuery($conn, "UPDATE products SET stock=$expression WHERE $where", [$quantity,...$values]);
                $message = $action === 'stock_set' ? "Stock set to $quantity for $matched products." : "$quantity units added to $matched products.";
                $details['quantity'] = $quantity;
            } elseif ($action === 'visibility') {
                $active = odNumber($post['is_active'] ?? '', 0, 1);
                odQuery($conn, "UPDATE products SET is_active=? WHERE $where", [$active,...$values]);
                $message = "$matched products " . ($active ? 'published.' : 'hidden.');
            } else {
                odQuery($conn, "UPDATE products SET discount_price=NULL WHERE $where", $values);
                $message = "Discount prices removed from $matched products.";
            }
            $details['scope'] = ['type' => $post['scope'] ?? 'all','matched' => $matched,'product_id' => $post['product_id'] ?? null,'category_id' => $post['category_id'] ?? null];
        } elseif (str_starts_with($action, 'seed_')) {
            $count = odNumber($post['count'] ?? 5, 1, 25);
            $message = odSeed($conn, $shopId, $action, $count);
        } elseif ($action === 'clear_carts') {
            odDelete($conn, 'cart', $shopId, $tables); $message = 'Shop carts cleared.';
        } elseif ($action === 'reset_coupon_usage') {
            odQuery($conn, 'UPDATE coupons SET used_count=0 WHERE shop_id=?', [$shopId]); $message = 'Coupon usage counters reset.';
        } elseif ($action === 'reset_campaign_usage') {
            odQuery($conn, "UPDATE email_campaign_allowances SET used_count=0 WHERE shop_id=? AND month_key=DATE_FORMAT(NOW(),'%Y-%m')", [$shopId]); $message = 'Current-month campaign usage reset for testing.';
        } elseif ($action === 'clear_orders') {
            odClearOrders($conn, $shopId, $tables); $message = 'Orders, delivery records, commission records and COD settlements cleared; COD wallets and coupon counters reset.';
        } elseif ($action === 'clear_catalogue') {
            $media = odMedia($conn,$shopId,['catalogue']); odClearCatalogue($conn,$shopId,$tables); $message = 'Products, categories, carts and campaign product links cleared.';
        } elseif ($action === 'clear_customers') {
            odClearCustomers($conn,$shopId,$tables); $message = 'Customer accounts, carts, reset tokens and campaign recipient records cleared.';
        } elseif ($action === 'clear_promotions') {
            $media = odMedia($conn,$shopId,['promotions']); odClearPromotions($conn,$shopId,$tables); $message = 'Coupons, popups, campaigns, recipients and allowances cleared.';
        } elseif ($action === 'reset_appearance') {
            odQuery($conn, "UPDATE shops SET theme_primary=DEFAULT(theme_primary),theme_secondary=DEFAULT(theme_secondary),theme_bg=DEFAULT(theme_bg),theme_text=DEFAULT(theme_text),theme_font=DEFAULT(theme_font),announcement=NULL,announcement_active=0 WHERE id=?", [$shopId]);
            $message = 'Theme colors, font and announcement restored to defaults.';
        } elseif ($action === 'reset_shop') {
            $known = array_merge(array_keys($before), ['shops','superadmin_audit_shops','owner_debug_logs']);
            foreach (odQuery($conn, "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='shop_id'")->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                if (!in_array($row['TABLE_NAME'], $known, true) && $row['TABLE_NAME'] !== 'superadmin_audit_logs') throw new InvalidArgumentException('Reset stopped: unsupported shop data table ' . $row['TABLE_NAME'] . '.');
            }
            $media = odMedia($conn,$shopId,['catalogue','promotions','branding']);
            odClearOrders($conn,$shopId,$tables);
            odClearPromotions($conn,$shopId,$tables);
            odClearCatalogue($conn,$shopId,$tables);
            odClearCustomers($conn,$shopId,$tables);
            foreach (['shop_handlers','shop_settings'] as $table) odDelete($conn,$table,$shopId,$tables);
            if (!empty($post['clear_subscriptions'])) odDelete($conn,'shop_subscriptions',$shopId,$tables);
            odQuery($conn, 'UPDATE shops SET description=NULL,logo=NULL,banner=NULL,announcement=NULL,announcement_active=0,address=NULL,city=NULL,state=NULL,pincode=NULL,theme_primary=DEFAULT(theme_primary),theme_secondary=DEFAULT(theme_secondary),theme_bg=DEFAULT(theme_bg),theme_text=DEFAULT(theme_text),theme_font=DEFAULT(theme_font) WHERE id=?', [$shopId]);
            $message = 'Shop data reset. Owner account, shop identity and audit history retained.' . (empty($post['clear_subscriptions']) ? ' Subscription history retained.' : ' Subscription history cleared.');
        }
        $details['after'] = odCounts($conn, $shopId);
        odQuery($conn, 'INSERT INTO owner_debug_logs (shop_id,owner_id,action,request_key,summary,details) VALUES (?,?,?,?,?,?)', [$shopId,$ownerId,$action,$key,$message,json_encode($details,JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)]);
        $conn->commit();
        return ['message' => $message, 'media' => !empty($post['remove_media']) ? $media : []];
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}
