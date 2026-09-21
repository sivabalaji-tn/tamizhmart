<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);
ini_set('display_errors','1');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../owner/includes/debug_tools.php';

if (($argv[1] ?? '') === 'render') {
    $database = $argv[2] ?? '';
    if (!preg_match('/^tamizhmart_debug_test_[a-f0-9]{12}$/', $database)) exit(2);
    $conn->select_db($database);
    session_id('debug-preview-' . bin2hex(random_bytes(8)));
    session_start();
    $_SESSION = ['owner_id'=>1,'shop_id'=>1,'owner_name'=>'Test Owner'];
    session_write_close();
    $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['PHP_SELF']='/owner/debugging.php';
    ob_start();
    register_shutdown_function(function () { $html=ob_get_clean(); echo $html; if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); });
    chdir(__DIR__ . '/../owner');
    require 'debugging.php';
    exit;
}

function debugCheck(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
function debugAction(mysqli $conn, string $action, array $post = [], int $owner = 1, int $shop = 1): array {
    return odRun($conn,$owner,$shop,['action'=>$action,'csrf'=>'debug-test-token','request_key'=>bin2hex(random_bytes(16))] + $post,'debug-test-token');
}
function debugReject(callable $callback, string $label): void {
    try { $callback(); } catch (InvalidArgumentException $exception) { debugCheck(true,$label); return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
function debugValue(mysqli $conn, string $sql) { return $conn->query($sql)->fetch_row()[0]; }

$source = DB_NAME;
$database = 'tamizhmart_debug_test_' . bin2hex(random_bytes(6));
$created = false;
$mediaRoot = sys_get_temp_dir() . '/' . $database;
try {
    $conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4"); $created=true;
    $tables=odTables($conn);
    $conn->select_db($database);
    // Preserve real foreign keys in the isolated test schema; checks are enabled for every action.
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) throw new RuntimeException('Unexpected table name');
        $ddl=$conn->query("SHOW CREATE TABLE `$source`.`$table`")->fetch_row()[1];
        $conn->query($ddl);
    }
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    $conn->query(file_get_contents(__DIR__ . '/../databasefile/add_owner_debug_logs.sql'));
    $hash=password_hash('TestOwnerPassword!',PASSWORD_DEFAULT);
    for($shop=1;$shop<=2;$shop++) {
        odQuery($conn,'INSERT INTO owners(id,name,email,password) VALUES (?,?,?,?)',[$shop,'Test Owner '.$shop,'owner'.$shop.'@example.invalid',$hash]);
        odQuery($conn,'INSERT INTO shops(id,owner_id,name,slug,description,logo) VALUES (?,?,?,?,?,?)',[$shop,$shop,'Test Shop '.$shop,'test-shop-'.$shop,'Temporary test shop','shared.jpg']);
        odQuery($conn,'INSERT INTO categories(id,shop_id,name) VALUES (?,?,?)',[$shop,$shop,'Category '.$shop]);
        odQuery($conn,'INSERT INTO products(id,shop_id,category_id,name,price,stock,image,discount_price) VALUES (?,?,?,?,?,?,?,?)',[$shop,$shop,$shop,'Product '.$shop,100,7,'shared.jpg',80]);
        odQuery($conn,'INSERT INTO users(id,shop_id,name,email,password) VALUES (?,?,?,?,?)',[$shop,$shop,'Customer '.$shop,'customer'.$shop.'@example.invalid',$hash]);
        odQuery($conn,'INSERT INTO orders(id,shop_id,user_id,total_amount,address) VALUES (?,?,?,100,?)',[$shop,$shop,$shop,'Test address']);
        odQuery($conn,'INSERT INTO order_items(order_id,product_id,quantity,price) VALUES (?,?,1,100)',[$shop,$shop]);
        odQuery($conn,'INSERT INTO cart(shop_id,user_id,product_id,quantity) VALUES (?,?,?,1)',[$shop,$shop,$shop]);
        odQuery($conn,'INSERT INTO shop_handlers(id,shop_id,name,email,password,cod_wallet) VALUES (?,?,?,?,?,100)',[$shop,$shop,'Handler '.$shop,'handler'.$shop.'@example.invalid',$hash]);
        odQuery($conn,"INSERT INTO coupons(shop_id,code,type,value,used_count) VALUES (?,?,'flat',10,2)",[$shop,'TEST'.$shop]);
        odQuery($conn,"INSERT INTO email_campaign_allowances(shop_id,month_key,used_count,monthly_limit) VALUES (?,DATE_FORMAT(NOW(),'%Y-%m'),2,2)",[$shop]);
        odQuery($conn,"INSERT INTO email_campaigns(id,shop_id,owner_id,month_key,preset,campaign_type,subject,headline,description) VALUES (?,?,?,DATE_FORMAT(NOW(),'%Y-%m'),'premium_sale','offer','Test','Test','Test')",[$shop,$shop,$shop]);
        odQuery($conn,'INSERT INTO email_campaign_products(campaign_id,product_id) VALUES (?,?)',[$shop,$shop]);
        odQuery($conn,"INSERT INTO email_campaign_recipients(campaign_id,user_id,email) VALUES (?,?,?)",[$shop,$shop,'customer'.$shop.'@example.invalid']);
        odQuery($conn,'INSERT INTO delivery_otps(order_id,handler_id,otp_code,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))',[$shop,$shop,'123456']);
        odQuery($conn,"INSERT INTO handler_activity_log(handler_id,shop_id,order_id,action) VALUES (?,?,?,'assigned')",[$shop,$shop,$shop]);
        odQuery($conn,'INSERT INTO handler_cod_settlements(handler_id,shop_id,amount,settled_by) VALUES (?,?,100,?)',[$shop,$shop,$shop]);
        odQuery($conn,'INSERT INTO commission_log(shop_id,order_id,order_amount,commission_rate,commission_amount) VALUES (?,?,100,2,2)',[$shop,$shop]);
        odQuery($conn,'INSERT INTO commission_collections(shop_id,commission_amount) VALUES (?,2)',[$shop]);
        odQuery($conn,"INSERT INTO shop_settings(shop_id,setting_key,setting_value) VALUES (?,'setup_complete','1')",[$shop]);
        odQuery($conn,"INSERT INTO password_resets(email,shop_id,otp,expires_at) VALUES (?,?,'123456',DATE_ADD(NOW(),INTERVAL 1 DAY))",['customer'.$shop.'@example.invalid',$shop]);
        odQuery($conn,"INSERT INTO popups(shop_id,title,message,image) VALUES (?,'Test','Test','private.jpg')",[$shop]);
    }
    $conn->query("INSERT INTO plans(id,name,slug,price,duration_days,features) VALUES (1,'Test','test',0,30,'[]')");
    $conn->query("INSERT INTO shop_subscriptions(shop_id,plan_id,status,expires_at) VALUES (1,1,'trial',DATE_ADD(NOW(),INTERVAL 30 DAY)),(2,1,'trial',DATE_ADD(NOW(),INTERVAL 30 DAY))");
    $otherBefore=odCounts($conn,2);
    putenv('APP_ENV=production'); putenv('OWNER_DEBUG_TOOLS=1');
    debugReject(fn()=>debugAction($conn,'stock_set',['quantity'=>100]),'Production blocks debug actions even if the tool flag is enabled');
    putenv('APP_ENV=test'); putenv('OWNER_DEBUG_TOOLS=0');
    debugReject(fn()=>debugAction($conn,'stock_set',['quantity'=>100]),'Disabled tools reject direct actions');
    putenv('OWNER_DEBUG_TOOLS=1');
    debugReject(fn()=>debugAction($conn,'stock_set',['quantity'=>100],1,2),'Owners cannot target another shop');
    debugReject(fn()=>odRun($conn,1,1,['action'=>'stock_set','quantity'=>100,'csrf'=>'wrong','request_key'=>bin2hex(random_bytes(16))],'debug-test-token'),'CSRF validation is enforced');
    debugAction($conn,'stock_set',['quantity'=>100]);
    debugCheck((int)debugValue($conn,'SELECT stock FROM products WHERE id=1')===100 && (int)debugValue($conn,'SELECT stock FROM products WHERE id=2')===7,'Set stock uses exact units and affects only the current shop');
    debugAction($conn,'stock_add',['quantity'=>20,'scope'=>'product','product_id'=>1]);
    debugCheck((int)debugValue($conn,'SELECT stock FROM products WHERE id=1')===120,'Add stock increments the selected product');
    debugReject(fn()=>debugAction($conn,'stock_set',['quantity'=>100,'scope'=>'product','product_id'=>2]),'Foreign product selections are rejected');
    debugReject(fn()=>debugAction($conn,'stock_set',['quantity'=>-1]),'Negative stock is rejected');
    debugReject(fn()=>debugAction($conn,'stock_add',['quantity'=>1000000]),'Stock overflow is rejected');
    debugAction($conn,'visibility',['is_active'=>0,'scope'=>'category','category_id'=>1]);
    debugAction($conn,'remove_discounts');
    debugCheck((int)debugValue($conn,'SELECT is_active FROM products WHERE id=1')===0 && debugValue($conn,'SELECT discount_price FROM products WHERE id=1')===null,'Visibility and discount tools work');
    $key=bin2hex(random_bytes(16)); $post=['action'=>'stock_add','quantity'=>1,'csrf'=>'debug-test-token','request_key'=>$key];
    odRun($conn,1,1,$post,'debug-test-token'); odRun($conn,1,1,$post,'debug-test-token');
    debugCheck((int)debugValue($conn,'SELECT stock FROM products WHERE id=1')===121,'Repeated submissions execute only once');
    debugAction($conn,'seed_products',['count'=>5]); debugAction($conn,'seed_customers',['count'=>3]); debugAction($conn,'seed_orders',['count'=>4]);
    debugCheck((int)debugValue($conn,"SELECT COUNT(*) FROM orders WHERE shop_id=1 AND notes LIKE '[DEBUG]%'")===4,'Synthetic order scenarios are generated');
    debugCheck((int)debugValue($conn,"SELECT COUNT(*) FROM users WHERE shop_id=1 AND email LIKE 'debug-%@example.invalid' AND is_active=0")===3,'Test customers are inactive with non-deliverable email addresses');
    debugReject(fn()=>debugAction($conn,'clear_catalogue',['confirmation'=>'test-shop-1']),'Catalogue cleanup protects existing orders');
    debugReject(fn()=>debugAction($conn,'clear_customers',['confirmation'=>'test-shop-1']),'Customer cleanup protects existing orders');
    debugAction($conn,'clear_carts');
    debugCheck((int)debugValue($conn,'SELECT COUNT(*) FROM cart WHERE shop_id=1')===0 && (int)debugValue($conn,'SELECT COUNT(*) FROM cart WHERE shop_id=2')===1,'Cart cleanup stays within this shop');
    debugAction($conn,'reset_coupon_usage');
    debugCheck((int)debugValue($conn,'SELECT used_count FROM coupons WHERE shop_id=1')===0 && (int)debugValue($conn,'SELECT used_count FROM coupons WHERE shop_id=2')===2,'Coupon counters reset only for this shop');
    debugAction($conn,'reset_campaign_usage');
    debugCheck((int)debugValue($conn,'SELECT used_count FROM email_campaign_allowances WHERE shop_id=1')===0 && (int)debugValue($conn,'SELECT monthly_limit FROM email_campaign_allowances WHERE shop_id=1')===2 && (int)debugValue($conn,'SELECT used_count FROM email_campaign_allowances WHERE shop_id=2')===2,'Campaign reset retains the monthly limit and other shop usage');
    $conn->query("UPDATE shops SET announcement='Test announcement',announcement_active=1 WHERE id=1");
    debugAction($conn,'reset_appearance',['confirmation'=>'test-shop-1']);
    debugCheck(debugValue($conn,'SELECT announcement FROM shops WHERE id=1')===null && (int)debugValue($conn,'SELECT announcement_active FROM shops WHERE id=1')===0,'Appearance reset clears the test announcement');
    debugReject(fn()=>debugAction($conn,'reset_shop',['confirmation'=>'wrong','password'=>'TestOwnerPassword!','acknowledge'=>1]),'Reset requires the exact shop slug');
    debugReject(fn()=>debugAction($conn,'reset_shop',['confirmation'=>'test-shop-1','password'=>'wrong','acknowledge'=>1]),'Reset requires the current owner password');
    $conn->query("CREATE TRIGGER debug_test_fail BEFORE INSERT ON owner_debug_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test failure'");
    try { debugAction($conn,'stock_set',['quantity'=>500]); throw new RuntimeException('Failure was not raised'); } catch(mysqli_sql_exception $exception) {}
    debugCheck((int)debugValue($conn,'SELECT stock FROM products WHERE id=1')===121,'Operation-log failures roll back database mutations');
    $conn->query('DROP TRIGGER debug_test_fail');
    $conn->query('INSERT INTO order_items(order_id,product_id,quantity,price) VALUES (2,1,1,100)');
    debugReject(fn()=>debugAction($conn,'reset_shop',['confirmation'=>'test-shop-1','password'=>'TestOwnerPassword!','acknowledge'=>1]),'Cross-shop references block destructive cleanup');
    $conn->query('DELETE FROM order_items WHERE order_id=2 AND product_id=1');
    $conn->query('CREATE TABLE future_shop_data (id INT PRIMARY KEY,shop_id INT) ENGINE=InnoDB');
    debugReject(fn()=>debugAction($conn,'reset_shop',['confirmation'=>'test-shop-1','password'=>'TestOwnerPassword!','acknowledge'=>1]),'Unknown shop-owned tables cannot silently escape a full reset');
    $conn->query('DROP TABLE future_shop_data');
    if (in_array('--render-fixture',$argv,true)) {
        $process=proc_open([PHP_BINARY,__FILE__,'render',$database],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]); $html=stream_get_contents($pipes[1]); fclose($pipes[1]); $errors=stream_get_contents($pipes[2]); fclose($pipes[2]);
        if(proc_close($process)!==0 || preg_match('/Warning:|Fatal error|Notice:/',$html.$errors)) throw new RuntimeException('Fixture rendering failed: '.$errors);
        file_put_contents(sys_get_temp_dir().'/tamizhmart-debug-preview.html',$html);
    }
    $result=debugAction($conn,'reset_shop',['confirmation'=>'test-shop-1','password'=>'TestOwnerPassword!','acknowledge'=>1,'remove_media'=>1]);
    $remaining=odCounts($conn,1);
    foreach($remaining as $table=>$count) if($table!=='shop_subscriptions') debugCheck($count===0,"Full reset clears $table");
    debugCheck($remaining['shop_subscriptions']===1,'Subscriptions are retained by default');
    debugCheck(odCounts($conn,2)===$otherBefore,'Other shop data survives the full reset');
    debugCheck((int)debugValue($conn,'SELECT COUNT(*) FROM owners WHERE id=1')===1 && debugValue($conn,'SELECT slug FROM shops WHERE id=1')==='test-shop-1','Owner access and shop identity survive reset');
    foreach(['order_items'=>'order_id','delivery_otps'=>'order_id','email_campaign_products'=>'campaign_id','email_campaign_recipients'=>'campaign_id'] as $table=>$keyColumn) debugCheck((int)debugValue($conn,"SELECT COUNT(*) FROM `$table` WHERE `$keyColumn`=1")===0,"Dependent $table records are removed");
    debugCheck((int)debugValue($conn,'SELECT COUNT(*) FROM owner_debug_logs WHERE shop_id=1')>0,'Debug history survives shop reset');
    mkdir($mediaRoot.'/products',0700,true); mkdir($mediaRoot.'/popups',0700,true); mkdir($mediaRoot.'/logos',0700,true);
    file_put_contents($mediaRoot.'/products/shared.jpg','shared'); file_put_contents($mediaRoot.'/products/unreferenced-test.jpg','private'); file_put_contents($mediaRoot.'/outside.txt','outside');
    symlink($mediaRoot.'/outside.txt',$mediaRoot.'/products/link.jpg');
    $cleanup=odRemoveMedia($conn,[['products','shared.jpg'],['products','unreferenced-test.jpg'],['products','../outside.txt'],['products','link.jpg']],$mediaRoot);
    debugCheck(file_exists($mediaRoot.'/products/shared.jpg') && !file_exists($mediaRoot.'/products/unreferenced-test.jpg') && file_exists($mediaRoot.'/outside.txt'),'Media cleanup preserves shared files, blocks traversal and skips symlinks');
    debugAction($conn,'reset_shop',['confirmation'=>'test-shop-1','password'=>'TestOwnerPassword!','acknowledge'=>1,'clear_subscriptions'=>1]);
    debugCheck((int)debugValue($conn,'SELECT COUNT(*) FROM shop_subscriptions WHERE shop_id=1')===0,'Subscription deletion requires its explicit option');
    debugAction($conn,'seed_products',['count'=>2]); debugAction($conn,'seed_customers',['count'=>2]); debugAction($conn,'seed_orders',['count'=>2]);
    debugAction($conn,'clear_orders',['confirmation'=>'test-shop-1']);
    debugCheck((int)debugValue($conn,'SELECT COUNT(*) FROM orders WHERE shop_id=1')===0 && (int)debugValue($conn,'SELECT COUNT(*) FROM products WHERE shop_id=1')===2,'Selective order cleanup retains the catalogue');
    debugAction($conn,'clear_customers',['confirmation'=>'test-shop-1']);
    debugAction($conn,'clear_catalogue',['confirmation'=>'test-shop-1']);
    debugAction($conn,'clear_promotions',['confirmation'=>'test-shop-1']);
    debugCheck((int)debugValue($conn,'SELECT COUNT(*) FROM users WHERE shop_id=1')===0 && (int)debugValue($conn,'SELECT COUNT(*) FROM products WHERE shop_id=1')===0 && odCounts($conn,2)===$otherBefore,'Selective cleanup succeeds after order removal without affecting other shops');
    echo "All owner debugging integration checks passed.\n";
} finally {
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    if($created && preg_match('/^tamizhmart_debug_test_[a-f0-9]{12}$/',$database)) { $conn->select_db($source); $conn->query("DROP DATABASE `$database`"); echo "Temporary database removed.\n"; }
    // Only known files created by this test are removed.
    foreach(['products/shared.jpg','products/unreferenced-test.jpg','products/link.jpg','outside.txt'] as $file) if(is_file($mediaRoot.'/'.$file)||is_link($mediaRoot.'/'.$file)) unlink($mediaRoot.'/'.$file);
    foreach(['products','popups','logos'] as $folder) if(is_dir($mediaRoot.'/'.$folder)) rmdir($mediaRoot.'/'.$folder);
    if(is_dir($mediaRoot)) rmdir($mediaRoot);
}
