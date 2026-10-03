<?php
// Shared customer-account operations. All records are scoped to both customer and shop.
function caQuery(mysqli $db, string $sql, array $args = []): mysqli_stmt {
    $st = $db->prepare($sql);
    if ($args) $st->bind_param(str_repeat('s', count($args)), ...$args);
    $st->execute();
    return $st;
}
function caEnsure(mysqli $db): void {
    $definitions = [
        'customer_accounts' => "user_id INT NOT NULL, shop_id INT NOT NULL, marketing_email TINYINT NOT NULL DEFAULT 0, session_version INT NOT NULL DEFAULT 0, photo MEDIUMBLOB NULL, photo_updated DATETIME NULL, password_changed DATETIME NULL, pending_email VARCHAR(190) NULL, email_code VARCHAR(255) NULL, email_expires DATETIME NULL, email_sent DATETIME NULL, email_attempts INT NOT NULL DEFAULT 0, PRIMARY KEY(user_id,shop_id), KEY(shop_id)",
        'customer_addresses' => "id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, shop_id INT NOT NULL, label VARCHAR(40) NOT NULL, recipient VARCHAR(100) NOT NULL, phone VARCHAR(25) NOT NULL, line1 VARCHAR(200) NOT NULL, line2 VARCHAR(200) NOT NULL DEFAULT '', city VARCHAR(100) NOT NULL, state VARCHAR(100) NOT NULL, pincode VARCHAR(12) NOT NULL, is_default TINYINT NOT NULL DEFAULT 0, KEY(user_id,shop_id), KEY(shop_id)",
        'customer_wishlist' => "user_id INT NOT NULL, shop_id INT NOT NULL, product_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id,shop_id,product_id), KEY(shop_id)",
        'customer_support' => "id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, shop_id INT NOT NULL, order_id INT NULL, kind VARCHAR(20) NOT NULL, subject VARCHAR(160) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'open', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(user_id,shop_id), KEY(shop_id,status)",
        'customer_support_messages' => "id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, user_id INT NOT NULL, shop_id INT NOT NULL, author VARCHAR(10) NOT NULL, message TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(ticket_id), KEY(shop_id)"
    ];
    $existing = array_column($db->query('SHOW TABLES')->fetch_all(), 0);
    foreach ($definitions as $table => $definition) {
        if (!in_array($table, $existing, true)) $db->query("CREATE TABLE IF NOT EXISTS `$table` ($definition) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
function caEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function caDeleteShopData(mysqli $db, int $sid): void {
    $existing=array_column($db->query('SHOW TABLES')->fetch_all(),0);
    foreach (['customer_support_messages','customer_support','customer_wishlist','customer_addresses','customer_accounts'] as $table) {
        if (in_array($table,$existing,true)) caQuery($db,"DELETE FROM `$table` WHERE shop_id=?",[$sid]);
    }
}
function caText(array $post, string $key, int $max, bool $required = true): string {
    $text = is_string($post[$key] ?? null) ? trim($post[$key]) : '';
    if (($required && $text === '') || mb_strlen($text) > $max) throw new InvalidArgumentException('Check the ' . str_replace('_', ' ', $key) . ' field.');
    return $text;
}
function caCsrf(): string { return $_SESSION['customer_csrf'] ??= bin2hex(random_bytes(32)); }
function caCheckCsrf(array $post): void {
    if (!is_string($post['csrf'] ?? null) || !hash_equals(caCsrf(), $post['csrf'])) throw new InvalidArgumentException('Your form expired. Refresh the page and try again.');
}
function caFields(string $action): void {
    echo '<input type="hidden" name="csrf" value="'.caEscape(caCsrf()).'"><input type="hidden" name="action" value="'.caEscape($action).'">';
}
function caClearCustomer(): void {
    unset($GLOBALS['customer_nav_photo']);
    foreach (['user_id','user_name','customer_session_version','customer_csrf','auth_provider'] as $key) unset($_SESSION[$key]);
}
function caValidateSession(mysqli $db): void {
    if (empty($_SESSION['user_id'])) return;
    $user = caQuery($db, 'SELECT id,is_active FROM users WHERE id=?', [(int)$_SESSION['user_id']])->get_result()->fetch_assoc();
    if (!$user || !$user['is_active']) { caClearCustomer(); return; }
    if (!$db->query("SHOW TABLES LIKE 'customer_accounts'")->num_rows) return;
    $row = caQuery($db, 'SELECT session_version,shop_id,photo_updated FROM customer_accounts WHERE user_id=?', [$user['id']])->get_result()->fetch_assoc();
    if ($row && (int)$row['session_version'] !== (int)($_SESSION['customer_session_version'] ?? 0)) caClearCustomer();
    else $GLOBALS['customer_nav_photo']=$row;
}
function caLogin(mysqli $db, int $userId, int $shopId): void {
    caEnsure($db);
    caQuery($db, 'INSERT IGNORE INTO customer_accounts(user_id,shop_id) VALUES (?,?)', [$userId,$shopId]);
    $version = caQuery($db, 'SELECT session_version FROM customer_accounts WHERE user_id=? AND shop_id=?', [$userId,$shopId])->get_result()->fetch_row()[0];
    session_regenerate_id(true);
    unset($_SESSION['customer_csrf']);
    $_SESSION['customer_session_version'] = (int)$version;
}
function caRequireCustomer(mysqli $db, array $shop): array {
    caValidateSession($db);
    $user = caQuery($db, 'SELECT * FROM users WHERE id=? AND shop_id=? AND is_active=1', [(int)($_SESSION['user_id'] ?? 0),$shop['id']])->get_result()->fetch_assoc();
    if (!$user) {
        caClearCustomer();
        header('Location: ../auth/login.php?shop='.rawurlencode($shop['slug']));
        exit;
    }
    return $user;
}
function caAccount(mysqli $db, int $uid, int $sid): array {
    caQuery($db, 'INSERT IGNORE INTO customer_accounts(user_id,shop_id) VALUES (?,?)', [$uid,$sid]);
    return caQuery($db, 'SELECT user_id,shop_id,marketing_email,session_version,photo_updated,password_changed,pending_email,email_sent,email_expires,email_attempts FROM customer_accounts WHERE user_id=? AND shop_id=?', [$uid,$sid])->get_result()->fetch_assoc();
}
function caAddressText(array $a): string {
    return $a['recipient'].' | '.$a['phone']."\n".$a['line1'].($a['line2'] ? "\n".$a['line2'] : '')."\n".$a['city'].', '.$a['state'].' '.$a['pincode'];
}
function caOrder(mysqli $db, int $uid, int $sid, int $id): array {
    $order = caQuery($db, 'SELECT * FROM orders WHERE id=? AND user_id=? AND shop_id=?', [$id,$uid,$sid])->get_result()->fetch_assoc();
    if (!$order) throw new InvalidArgumentException('Order not found.');
    return $order;
}
function caMarketingAllowed(mysqli $db, int $uid, int $sid, ?string $email = null): bool {
    $row = caQuery($db, 'SELECT a.marketing_email FROM customer_accounts a JOIN users u ON u.id=a.user_id AND u.shop_id=a.shop_id WHERE a.user_id=? AND a.shop_id=? AND u.is_active=1'.($email!==null?' AND u.email=?':''), $email!==null?[$uid,$sid,$email]:[$uid,$sid])->get_result()->fetch_row();
    return $row && (int)$row[0] === 1;
}
function caCampaignRecipients(mysqli $db, int $sid, string $audience): array {
    $where='';
    if ($audience==='buyers') $where=' AND EXISTS (SELECT 1 FROM orders o WHERE o.user_id=u.id AND o.shop_id=u.shop_id)';
    elseif ($audience==='recent_buyers') $where=' AND EXISTS (SELECT 1 FROM orders o WHERE o.user_id=u.id AND o.shop_id=u.shop_id AND o.created_at>=DATE_SUB(NOW(),INTERVAL 90 DAY))';
    elseif ($audience==='inactive_customers') $where=' AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id=u.id AND o.shop_id=u.shop_id AND o.created_at>=DATE_SUB(NOW(),INTERVAL 60 DAY))';
    return caQuery($db,"SELECT u.id,u.name,u.email FROM users u JOIN customer_accounts a ON a.user_id=u.id AND a.shop_id=u.shop_id WHERE u.shop_id=? AND u.is_active=1 AND u.email<>'' AND a.marketing_email=1".$where,[$sid])->get_result()->fetch_all(MYSQLI_ASSOC);
}
function caOwnerReply(mysqli $db, int $ownerId, int $sid, array $post): void {
    caCheckCsrf($post);
    if (!caQuery($db,'SELECT s.id FROM shops s JOIN owners o ON o.id=s.owner_id WHERE s.id=? AND o.id=? AND s.is_suspended=0 AND o.is_suspended=0',[$sid,$ownerId])->get_result()->num_rows) throw new InvalidArgumentException('Shop access denied.');
    $status=caText($post,'status',30); $body=caText($post,'message',3000,false); $tid=(int)($post['ticket_id']??0);
    if (!in_array($status,['open','in_progress','awaiting_customer','approved','rejected','resolved','closed'],true)) throw new InvalidArgumentException('Invalid request status.');
    $db->begin_transaction();
    try {
        $ticket=caQuery($db,'SELECT * FROM customer_support WHERE id=? AND shop_id=? FOR UPDATE',[$tid,$sid])->get_result()->fetch_assoc();
        if (!$ticket) throw new InvalidArgumentException('Request not found.');
        if (in_array($status,['approved','rejected'],true) && $ticket['kind']!=='return') throw new InvalidArgumentException('Approval and rejection are for return requests only.');
        if ($body==='' && $status===$ticket['status']) throw new InvalidArgumentException('Enter a reply or change the status.');
        $text=($body!==''?$body."\n\n":'').'Status: '.str_replace('_',' ',$status).'.';
        caQuery($db,"INSERT INTO customer_support_messages(ticket_id,user_id,shop_id,author,message) VALUES (?,?,?,'owner',?)",[$tid,$ticket['user_id'],$sid,$text]);
        caQuery($db,'UPDATE customer_support SET status=?,updated_at=NOW() WHERE id=? AND shop_id=?',[$status,$tid,$sid]);
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
function caPhoto(string $path): string {
    if (!function_exists('imagecreatefromstring')) throw new InvalidArgumentException('Photo uploads need the PHP GD extension. Please contact the shop owner.');
    if (!is_file($path) || filesize($path) > 5 * 1024 * 1024) throw new InvalidArgumentException('Choose a JPG, PNG or WebP image under 5 MB.');
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $size = @getimagesize($path);
    if (!in_array($type, ['image/jpeg','image/png','image/webp'], true) || !$size || $size[0] < 64 || $size[1] < 64 || $size[0] > 4096 || $size[1] > 4096) throw new InvalidArgumentException('Use a JPG, PNG or WebP photo between 64 and 4096 pixels on each side.');
    $source = @imagecreatefromstring(file_get_contents($path));
    if (!$source) throw new InvalidArgumentException('This image could not be read. Choose another photo.');
    $target = imagecreatetruecolor(256,256);
    imagefill($target,0,0,imagecolorallocate($target,255,255,255));
    $side = min(imagesx($source),imagesy($source));
    imagecopyresampled($target,$source,0,0,(int)((imagesx($source)-$side)/2),(int)((imagesy($source)-$side)/2),256,256,$side,$side);
    ob_start(); imagejpeg($target,null,88); $bytes = ob_get_clean();
    imagedestroy($source); imagedestroy($target);
    return $bytes;
}
function caSendCode(string $email, string $code, string $shopName): bool {
    require_once __DIR__.'/notifications.php';
    $src = dirname(__DIR__,2).'/vendor/phpmailer/src/';
    if (!is_file($src.'PHPMailer.php')) return false;
    foreach (['Exception','PHPMailer','SMTP'] as $file) require_once $src.$file.'.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host=MAIL_HOST; $mail->Port=MAIL_PORT;
        $mail->SMTPAuth=true; $mail->Username=MAIL_USERNAME; $mail->Password=MAIL_PASSWORD;
        $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout=15; $mail->CharSet='UTF-8';
        $mail->setFrom(MAIL_FROM,MAIL_FROMNAME); $mail->addAddress($email);
        $mail->Subject='Verify your email for '.$shopName;
        $mail->Body="Your email verification code is $code. It expires in 10 minutes. If you did not request this change, ignore this email.";
        return $mail->send();
    } catch (Throwable $e) { error_log('Customer verification email failed: '.$e->getCode()); return false; }
}
function caAction(mysqli $db, array $user, array $shop, array $post, array $files = [], ?callable $sendCode = null): string {
    caCheckCsrf($post);
    $uid=(int)$user['id']; $sid=(int)$shop['id'];
    $action=caText($post,'action',40);
    $db->begin_transaction();
    try {
        $current=caQuery($db,'SELECT * FROM users WHERE id=? AND shop_id=? AND is_active=1 FOR UPDATE',[$uid,$sid])->get_result()->fetch_assoc();
        if (!$current) throw new InvalidArgumentException('Account not available.');
        $account=caQuery($db,'SELECT * FROM customer_accounts WHERE user_id=? AND shop_id=? FOR UPDATE',[$uid,$sid])->get_result()->fetch_assoc();
        if (!$account || (int)$account['session_version'] !== (int)($_SESSION['customer_session_version'] ?? 0)) throw new InvalidArgumentException('Please sign in again.');
        $version=null;
        if ($action==='profile') {
            $name=caText($post,'name',100); $phone=caText($post,'phone',25,false);
            if ($phone!=='' && !preg_match('/^[+0-9 ()-]{7,25}$/',$phone)) throw new InvalidArgumentException('Enter a valid phone number.');
            caQuery($db,'UPDATE users SET name=?,phone=? WHERE id=? AND shop_id=?',[$name,$phone,$uid,$sid]);
            $message='Personal details updated.';
        } elseif ($action==='photo' || $action==='remove_photo') {
            $bytes=null;
            if ($action==='photo') {
                $file=$files['photo'] ?? [];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) throw new InvalidArgumentException('Select an image under 5 MB and try again.');
                $bytes=caPhoto($file['tmp_name']);
            }
            caQuery($db,'UPDATE customer_accounts SET photo=?,photo_updated=? WHERE user_id=? AND shop_id=?',[$bytes,$bytes===null ? null : date('Y-m-d H:i:s'),$uid,$sid]);
            $message=$bytes===null ? 'Photo removed.' : 'Profile photo updated.';
        } elseif ($action==='preferences') {
            caQuery($db,'UPDATE customer_accounts SET marketing_email=? WHERE user_id=? AND shop_id=?',[isset($post['marketing_email'])?1:0,$uid,$sid]);
            $message='Email preferences saved.';
        } elseif ($action==='address_save') {
            $id=(int)($post['address_id']??0);
            if ($id && !caQuery($db,'SELECT id FROM customer_addresses WHERE id=? AND user_id=? AND shop_id=?',[$id,$uid,$sid])->get_result()->num_rows) throw new InvalidArgumentException('Address not found.');
            $fields=[];
            foreach (['label'=>40,'recipient'=>100,'phone'=>25,'line1'=>200,'line2'=>200,'city'=>100,'state'=>100,'pincode'=>12] as $field=>$max) $fields[$field]=caText($post,$field,$max,$field!=='line2');
            if (!preg_match('/^[+0-9 ()-]{7,25}$/',$fields['phone']) || !preg_match('/^[0-9]{6}$/',$fields['pincode'])) throw new InvalidArgumentException('Enter a valid phone number and six-digit pincode.');
            $count=(int)caQuery($db,'SELECT COUNT(*) FROM customer_addresses WHERE user_id=? AND shop_id=?',[$uid,$sid])->get_result()->fetch_row()[0];
            if (!$id && $count>=10) throw new InvalidArgumentException('You can save up to 10 addresses.');
            $default=$count===0 || !empty($post['is_default']);
            if ($id && !$default) $default=(bool)caQuery($db,'SELECT is_default FROM customer_addresses WHERE id=?',[$id])->get_result()->fetch_row()[0];
            if ($default) caQuery($db,'UPDATE customer_addresses SET is_default=0 WHERE user_id=? AND shop_id=?',[$uid,$sid]);
            if ($id) caQuery($db,'UPDATE customer_addresses SET label=?,recipient=?,phone=?,line1=?,line2=?,city=?,state=?,pincode=?,is_default=? WHERE id=? AND user_id=? AND shop_id=?',[...array_values($fields),(int)$default,$id,$uid,$sid]);
            else caQuery($db,'INSERT INTO customer_addresses(label,recipient,phone,line1,line2,city,state,pincode,is_default,user_id,shop_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[...array_values($fields),(int)$default,$uid,$sid]);
            if ($default) caQuery($db,'UPDATE users SET address=? WHERE id=? AND shop_id=?',[caAddressText($fields),$uid,$sid]);
            $message='Address saved.';
        } elseif ($action==='address_delete' || $action==='address_default') {
            $id=(int)($post['address_id']??0);
            $address=caQuery($db,'SELECT * FROM customer_addresses WHERE id=? AND user_id=? AND shop_id=?',[$id,$uid,$sid])->get_result()->fetch_assoc();
            if (!$address) throw new InvalidArgumentException('Address not found.');
            if ($action==='address_delete') {
                caQuery($db,'DELETE FROM customer_addresses WHERE id=? AND user_id=? AND shop_id=?',[$id,$uid,$sid]);
                if ($address['is_default']) {
                    $next=caQuery($db,'SELECT * FROM customer_addresses WHERE user_id=? AND shop_id=? ORDER BY id LIMIT 1',[$uid,$sid])->get_result()->fetch_assoc();
                    if ($next) caQuery($db,'UPDATE customer_addresses SET is_default=1 WHERE id=?',[$next['id']]);
                    caQuery($db,'UPDATE users SET address=? WHERE id=? AND shop_id=?',[$next?caAddressText($next):'', $uid,$sid]);
                }
                $message='Address removed.';
            } else {
                caQuery($db,'UPDATE customer_addresses SET is_default=(id=?) WHERE user_id=? AND shop_id=?',[$id,$uid,$sid]);
                caQuery($db,'UPDATE users SET address=? WHERE id=? AND shop_id=?',[caAddressText($address),$uid,$sid]);
                $message='Default address updated.';
            }
        } elseif ($action==='wishlist_add' || $action==='wishlist_remove') {
            $pid=(int)($post['product_id']??0);
            if ($action==='wishlist_add') {
                if (!caQuery($db,'SELECT id FROM products WHERE id=? AND shop_id=? AND is_active=1',[$pid,$sid])->get_result()->num_rows) throw new InvalidArgumentException('Product unavailable.');
                caQuery($db,'INSERT IGNORE INTO customer_wishlist(user_id,shop_id,product_id) VALUES (?,?,?)',[$uid,$sid,$pid]);
                $message='Product saved to your wishlist.';
            } else {
                caQuery($db,'DELETE FROM customer_wishlist WHERE user_id=? AND shop_id=? AND product_id=?',[$uid,$sid,$pid]); $message='Product removed from wishlist.';
            }
        } elseif ($action==='buy_again' || $action==='wishlist_cart') {
            if ($action==='buy_again') {
                $order=caOrder($db,$uid,$sid,(int)($post['order_id']??0));
                $lines=caQuery($db,'SELECT product_id,SUM(quantity) AS quantity FROM order_items WHERE order_id=? GROUP BY product_id',[$order['id']])->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $pid=(int)($post['product_id']??0);
                if (!caQuery($db,'SELECT product_id FROM customer_wishlist WHERE user_id=? AND shop_id=? AND product_id=?',[$uid,$sid,$pid])->get_result()->num_rows) throw new InvalidArgumentException('Saved product not found.');
                $lines=[['product_id'=>$pid,'quantity'=>1]];
            }
            $added=0; $skipped=0;
            foreach ($lines as $line) {
                $p=caQuery($db,'SELECT id,stock FROM products WHERE id=? AND shop_id=? AND is_active=1 FOR UPDATE',[$line['product_id'],$sid])->get_result()->fetch_assoc();
                if (!$p || $p['stock']<=0) { $skipped++; continue; }
                $cart=caQuery($db,'SELECT id,quantity FROM cart WHERE user_id=? AND shop_id=? AND product_id=? FOR UPDATE',[$uid,$sid,$p['id']])->get_result()->fetch_assoc();
                $qty=min((int)$line['quantity'],max(0,(int)$p['stock']-(int)($cart['quantity']??0)));
                if (!$qty) { $skipped++; continue; }
                if ($cart) caQuery($db,'UPDATE cart SET quantity=quantity+? WHERE id=?',[$qty,$cart['id']]);
                else caQuery($db,'INSERT INTO cart(user_id,shop_id,product_id,quantity) VALUES (?,?,?,?)',[$uid,$sid,$p['id'],$qty]);
                $added+=$qty;
                if ($qty<(int)$line['quantity']) $skipped++;
            }
            $message="$added units added to cart at current prices.".($skipped ? " $skipped items were unavailable or stock-limited." : '');
        } elseif ($action==='password' || $action==='signout_others' || $action==='email_request') {
            $password=caText($post,'current_password',500);
            if (!$current['password'] || !password_verify($password,$current['password'])) throw new InvalidArgumentException('Current password is incorrect. Google-only accounts can set a password through Forgot Password.');
            if ($action==='email_request') {
                $email=caText($post,'new_email',180);
                if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strcasecmp($email,$current['email'])===0) throw new InvalidArgumentException('Enter a different valid email address.');
                if (caQuery($db,'SELECT id FROM users WHERE shop_id=? AND email=?',[$sid,$email])->get_result()->num_rows) throw new InvalidArgumentException('This email cannot be used for this shop.');
                if ($account['email_sent'] && strtotime($account['email_sent'])>time()-60) throw new InvalidArgumentException('Wait one minute before requesting another code.');
                $code=(string)random_int(100000,999999);
                if (!($sendCode ?? 'caSendCode')($email,$code,$shop['name'])) throw new InvalidArgumentException('Verification email could not be sent. Your email has not changed. Try again later.');
                caQuery($db,'UPDATE customer_accounts SET pending_email=?,email_code=?,email_sent=NOW(),email_expires=DATE_ADD(NOW(),INTERVAL 10 MINUTE),email_attempts=0 WHERE user_id=? AND shop_id=?',[$email,password_hash($code,PASSWORD_DEFAULT),$uid,$sid]);
                $message='Verification code sent to the new email address.';
            } else {
                if ($action==='password') {
                    $new=caText($post,'new_password',128);
                    if (mb_strlen($new)<10 || strlen($new)>72 || $new!==($post['confirm_password']??'')) throw new InvalidArgumentException('Use at least 10 characters, at most 72 bytes, and matching new passwords.');
                    caQuery($db,'UPDATE users SET password=? WHERE id=? AND shop_id=?',[password_hash($new,PASSWORD_DEFAULT),$uid,$sid]);
                    caQuery($db,'UPDATE customer_accounts SET password_changed=NOW(),pending_email=NULL,email_code=NULL WHERE user_id=? AND shop_id=?',[$uid,$sid]);
                }
                $version=(int)$account['session_version']+1;
                caQuery($db,'UPDATE customer_accounts SET session_version=? WHERE user_id=? AND shop_id=?',[$version,$uid,$sid]);
                $message=$action==='password'?'Password changed. Other sessions signed out.':'Other sessions signed out.';
            }
        } elseif ($action==='email_verify') {
            $code=caText($post,'code',6);
            if (!$account['pending_email'] || !$account['email_expires'] || strtotime($account['email_expires'])<time() || $account['email_attempts']>=5) throw new InvalidArgumentException('Code expired or too many attempts. Request a new code.');
            if (!password_verify($code,$account['email_code'])) {
                caQuery($db,'UPDATE customer_accounts SET email_attempts=email_attempts+1 WHERE user_id=? AND shop_id=?',[$uid,$sid]);
                $db->commit();
                throw new InvalidArgumentException('Incorrect verification code.');
            }
            if (caQuery($db,'SELECT id FROM users WHERE shop_id=? AND email=? AND id<>?',[$sid,$account['pending_email'],$uid])->get_result()->num_rows) throw new InvalidArgumentException('This email cannot be used for this shop.');
            caQuery($db,"UPDATE users SET email=?,google_id=NULL,auth_provider='local' WHERE id=? AND shop_id=?",[$account['pending_email'],$uid,$sid]);
            $version=(int)$account['session_version']+1;
            caQuery($db,'UPDATE customer_accounts SET pending_email=NULL,email_code=NULL,email_expires=NULL,session_version=? WHERE user_id=? AND shop_id=?',[$version,$uid,$sid]);
            $message='Email verified and updated. Other sessions signed out. Sign in with your password next time.';
        } elseif ($action==='support_create') {
            $kind=caText($post,'kind',20); $oid=(int)($post['order_id']??0);
            if (!in_array($kind,['question','order_issue','return'],true)) throw new InvalidArgumentException('Choose a request type.');
            if ($kind!=='question' && !$oid) throw new InvalidArgumentException('Choose an order for this request.');
            if ($oid) { $order=caOrder($db,$uid,$sid,$oid); if ($kind==='return' && $order['status']!=='delivered') throw new InvalidArgumentException('Return requests are available for delivered orders only.'); }
            $subject=caText($post,'subject',160); $body=caText($post,'message',3000);
            if ((int)caQuery($db,"SELECT COUNT(*) FROM customer_support WHERE user_id=? AND shop_id=? AND status NOT IN ('resolved','closed','rejected')",[$uid,$sid])->get_result()->fetch_row()[0]>=10) throw new InvalidArgumentException('You already have 10 active requests. Reply to an existing request.');
            caQuery($db,'INSERT INTO customer_support(user_id,shop_id,order_id,kind,subject) VALUES (?,?,?,?,?)',[$uid,$sid,$oid?:null,$kind,$subject]);
            $tid=$db->insert_id;
            caQuery($db,"INSERT INTO customer_support_messages(ticket_id,user_id,shop_id,author,message) VALUES (?,?,?,'customer',?)",[$tid,$uid,$sid,$body]);
            $message='Request submitted to the shop. Reference #'.$tid.'.';
        } elseif ($action==='support_reply') {
            $tid=(int)($post['ticket_id']??0); $body=caText($post,'message',3000);
            $ticket=caQuery($db,'SELECT * FROM customer_support WHERE id=? AND user_id=? AND shop_id=? FOR UPDATE',[$tid,$uid,$sid])->get_result()->fetch_assoc();
            if (!$ticket || $ticket['status']==='closed') throw new InvalidArgumentException('This request is unavailable or closed.');
            caQuery($db,"INSERT INTO customer_support_messages(ticket_id,user_id,shop_id,author,message) VALUES (?,?,?,'customer',?)",[$tid,$uid,$sid,$body]);
            caQuery($db,"UPDATE customer_support SET status=CASE WHEN status IN ('awaiting_customer','resolved') THEN 'open' ELSE status END,updated_at=NOW() WHERE id=?",[$tid]);
            $message='Reply sent to the shop.';
        } else throw new InvalidArgumentException('Unknown account action.');
        $db->commit();
        if ($action==='profile') $_SESSION['user_name']=$name;
        if ($version!==null) { session_regenerate_id(true); $_SESSION['customer_session_version']=$version; }
        return $message;
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
