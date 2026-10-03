<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../shop/includes/customer_account.php';
require_once __DIR__.'/../owner/includes/debug_tools.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
if (($argv[1]??'')==='render') {
    $db=$argv[2]??''; if(!preg_match('/^tm_account_test_[a-f0-9]{12}$/',$db))exit(2);
    $conn->select_db($db);
    $tab=$argv[3]??'overview';
    $_SESSION=['user_id'=>1,'user_name'=>'Test Customer','shop_id'=>1,'owner_id'=>1,'owner_name'=>'Test Owner','customer_session_version'=>(int)caAccount($conn,1,1)['session_version']];
    $_GET=['shop'=>'account-test-one','tab'=>$tab,'ticket'=>($tab==='support-thread'?1:0),'order_id'=>1];
    if($tab==='support-thread')$_GET['tab']='support';
    $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['PHP_SELF']='/shop/profile.php';
    session_write_close();
    $file='profile.php'; $directory='shop';
    if($tab==='owner-support'){ $directory='owner';$file='customer_support.php';$_GET['ticket']=1; }
    if($tab==='receipt')$file='reciept.php';
    if($tab==='checkout')$file='checkout.php';
    if($tab==='product'){ $file='product.php';$_GET['id']=1; }
    chdir(__DIR__.'/../'.$directory);
    register_shutdown_function(function(){if(session_status()===PHP_SESSION_ACTIVE)session_destroy();});
    require $file; exit;
}
$results=[];
function checkAccount(bool $ok,string $label):void{global $results;if(!$ok)throw new RuntimeException($label);$results[]='PASS '.$label;}
function rejectAccount(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException $e){checkAccount(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function valueAccount(mysqli $db,string $sql){return $db->query($sql)->fetch_row()[0];}
$source=DB_NAME;$database='tm_account_test_'.bin2hex(random_bytes(6));$created=false;$keepFixture=false;
$photo=sys_get_temp_dir().'/'.$database.'.png';$invalid=sys_get_temp_dir().'/'.$database.'.txt';
try {
    $tables=array_column($conn->query('SHOW TABLES')->fetch_all(),0);
    $conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4");$created=true;$conn->select_db($database);
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    foreach($tables as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/',$table))throw new RuntimeException('Invalid table');$conn->query($conn->query("SHOW CREATE TABLE `$source`.`$table`")->fetch_row()[1]);}
    $conn->query('SET FOREIGN_KEY_CHECKS=1'); caEnsure($conn);
    $hash=password_hash('ExamplePassword!123',PASSWORD_DEFAULT);
    for($id=1;$id<=2;$id++){
        caQuery($conn,'INSERT INTO owners(id,name,email,password) VALUES (?,?,?,?)',[$id,'Test Owner '.$id,'owner'.$id.'@example.invalid',$hash]);
        caQuery($conn,"INSERT INTO shops(id,owner_id,name,slug,theme_primary,theme_secondary,theme_bg,theme_text,theme_font) VALUES (?,?,?,?, '#17765c','#496caa','#f7faf9','#202c29','Inter')",[$id,$id,'Account Test Shop '.$id,$id===1?'account-test-one':'account-test-two']);
        caQuery($conn,'INSERT INTO users(id,shop_id,name,email,password,phone) VALUES (?,?,?,?,?,?)',[$id,$id,'Test Customer '.$id,'customer'.$id.'@example.invalid',$hash,'9876543210']);
        caQuery($conn,'INSERT INTO categories(id,shop_id,name) VALUES (?,?,?)',[$id,$id,'Essentials']);
        caQuery($conn,'INSERT INTO products(id,shop_id,category_id,name,price,discount_price,stock,is_active) VALUES (?,?,?,?,100,80,3,1)',[$id,$id,$id,'Everyday cotton tote']);
        caQuery($conn,"INSERT INTO orders(id,shop_id,user_id,shop_order_number,total_amount,status,payment_status,address) VALUES (?,?,?,1,160,'delivered','paid','Test delivery address')",[$id,$id,$id]);
        caQuery($conn,'INSERT INTO order_items(order_id,product_id,quantity,price) VALUES (?,?,2,80)',[$id,$id]);
        caAccount($conn,$id,$id);
    }
    $u=caQuery($conn,'SELECT * FROM users WHERE id=1')->get_result()->fetch_assoc();$s=caQuery($conn,'SELECT * FROM shops WHERE id=1')->get_result()->fetch_assoc();
    $_SESSION=['user_id'=>1,'customer_session_version'=>0,'customer_csrf'=>'test-csrf'];
    $run=function(string $action,array $post=[],?callable $send=null)use($conn,$u,$s){return caAction($conn,$u,$s,['csrf'=>'test-csrf','action'=>$action]+$post,[],$send);};
    rejectAccount(fn()=>caAction($conn,$u,$s,['action'=>'profile','csrf'=>'wrong']),'CSRF rejects invalid forms');
    rejectAccount(fn()=>caAction($conn,$u,['id'=>2,'name'=>'Other'],['csrf'=>'test-csrf','action'=>'preferences']),'Cross-shop account mutation rejected');
    $run('profile',['name'=>'Test Customer','phone'=>'9876543210']);
    checkAccount(valueAccount($conn,'SELECT name FROM users WHERE id=1')==='Test Customer','Personal details saved');
    $address=['label'=>'Home','recipient'=>'Test Customer','phone'=>'9876543210','line1'=>'12 Test Street','line2'=>'Near the library','city'=>'Chennai','state'=>'Tamil Nadu','pincode'=>'600001'];
    $run('address_save',$address);$aid=(int)$conn->insert_id;
    $run('address_save',['label'=>'Work']+ $address);
    checkAccount((int)valueAccount($conn,'SELECT SUM(is_default) FROM customer_addresses WHERE user_id=1')===1,'Exactly one default address');
    $run('address_default',['address_id'=>2]);
    checkAccount(str_contains(valueAccount($conn,'SELECT address FROM users WHERE id=1'),'12 Test Street'),'Default address is available to existing checkout');
    rejectAccount(fn()=>caAction($conn,['id'=>2],['id'=>2,'name'=>'Other'],['csrf'=>'test-csrf','action'=>'address_delete','address_id'=>1]),'Other customer cannot delete an address');
    $run('address_delete',['address_id'=>2]);
    checkAccount((int)valueAccount($conn,'SELECT is_default FROM customer_addresses WHERE id=1')===1,'Deleting default promotes a remaining address');
    $run('wishlist_add',['product_id'=>1]);$run('wishlist_add',['product_id'=>1]);
    checkAccount((int)valueAccount($conn,'SELECT COUNT(*) FROM customer_wishlist WHERE user_id=1')===1,'Wishlist save is idempotent');
    rejectAccount(fn()=>$run('wishlist_add',['product_id'=>2]),'Foreign shop product cannot enter wishlist');
    $run('wishlist_cart',['product_id'=>1]);$run('buy_again',['order_id'=>1]);$run('buy_again',['order_id'=>1]);
    checkAccount((int)valueAccount($conn,'SELECT quantity FROM cart WHERE user_id=1')===3,'Reorder respects remaining stock and existing cart');
    rejectAccount(fn()=>$run('buy_again',['order_id'=>2]),'Cannot reorder another customer order');
    checkAccount(!caMarketingAllowed($conn,1,1),'Marketing is opt-in by default');
    $run('preferences',['marketing_email'=>1]);checkAccount(caMarketingAllowed($conn,1,1),'Marketing subscription saved');
    foreach(['all_registered','buyers','recent_buyers'] as $audience) checkAccount(array_column(caCampaignRecipients($conn,1,$audience),'id')===[1],'Subscribed audience filtered: '.$audience);
    checkAccount(caCampaignRecipients($conn,1,'inactive_customers')===[],'Recent customers excluded from inactive audience');
    $conn->query('UPDATE orders SET created_at=DATE_SUB(NOW(),INTERVAL 100 DAY) WHERE id=1');
    checkAccount(array_column(caCampaignRecipients($conn,1,'inactive_customers'),'id')===[1] && caCampaignRecipients($conn,1,'recent_buyers')===[],'Inactive and recent audience date boundaries respected');
    $conn->query('UPDATE orders SET created_at=NOW() WHERE id=1');
    $run('preferences');checkAccount(!caMarketingAllowed($conn,1,1),'Marketing opt-out enforced');
    foreach(['all_registered','buyers','recent_buyers','inactive_customers'] as $audience)checkAccount(caCampaignRecipients($conn,1,$audience)===[],'Opt-out excludes customer from '.$audience);
    $run('support_create',['kind'=>'return','order_id'=>1,'subject'=>'Return request','message'=>'The size is not suitable.']);
    rejectAccount(fn()=>$run('support_create',['kind'=>'order_issue','order_id'=>2,'subject'=>'Other order','message'=>'Test']),'Support rejects another customer order');
    $conn->query("UPDATE orders SET status='pending' WHERE id=1");
    rejectAccount(fn()=>$run('support_create',['kind'=>'return','order_id'=>1,'subject'=>'Return','message'=>'Test']),'Returns require a delivered order');
    $conn->query("UPDATE orders SET status='delivered' WHERE id=1");
    rejectAccount(fn()=>caOwnerReply($conn,2,2,['csrf'=>'test-csrf','ticket_id'=>1,'status'=>'resolved','message'=>'Other owner']),'Other shop owner cannot read/update support request');
    caOwnerReply($conn,1,1,['csrf'=>'test-csrf','ticket_id'=>1,'status'=>'approved','message'=>'We can accept the return. Please bring the item to the shop.']);
    checkAccount(valueAccount($conn,'SELECT status FROM customer_support WHERE id=1')==='approved','Owner can approve return and reply');
    $run('support_reply',['ticket_id'=>1,'message'=>'Thank you. I will visit tomorrow.']);
    checkAccount((int)valueAccount($conn,'SELECT COUNT(*) FROM customer_support_messages WHERE ticket_id=1')===3,'Customer-owner conversation persists');
    checkAccount(valueAccount($conn,'SELECT status FROM customer_support WHERE id=1')==='approved','Customer reply retains return decision');
    $captured='';$mailer=function($email,$code)use(&$captured){$captured=$code;return true;};
    rejectAccount(fn()=>$run('email_request',['current_password'=>'ExamplePassword!123','new_email'=>'updated@example.invalid'],fn()=>false),'Mail delivery failure leaves email unchanged');
    $run('email_request',['current_password'=>'ExamplePassword!123','new_email'=>'updated@example.invalid'],$mailer);
    rejectAccount(fn()=>$run('email_request',['current_password'=>'ExamplePassword!123','new_email'=>'updated@example.invalid'],$mailer),'Verification resend cooldown enforced');
    checkAccount(valueAccount($conn,'SELECT email FROM users WHERE id=1')==='customer1@example.invalid','Email stays unchanged until verification');
    rejectAccount(fn()=>$run('email_verify',['code'=>'000000']),'Incorrect email code rejected');
    checkAccount((int)valueAccount($conn,'SELECT email_attempts FROM customer_accounts WHERE user_id=1')===1,'Failed verification attempts are counted');
    $conn->query('UPDATE customer_accounts SET email_expires=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE user_id=1');
    rejectAccount(fn()=>$run('email_verify',['code'=>$captured]),'Expired verification codes rejected');
    $conn->query('UPDATE customer_accounts SET email_expires=DATE_ADD(NOW(),INTERVAL 10 MINUTE),email_attempts=5 WHERE user_id=1');
    rejectAccount(fn()=>$run('email_verify',['code'=>$captured]),'Verification attempt limit enforced');
    $conn->query('UPDATE customer_accounts SET email_attempts=0 WHERE user_id=1');
    $run('email_verify',['code'=>$captured]);
    checkAccount(valueAccount($conn,'SELECT email FROM users WHERE id=1')==='updated@example.invalid','Verified email is updated');
    $run('password',['current_password'=>'ExamplePassword!123','new_password'=>'ChangedPassword!123','confirm_password'=>'ChangedPassword!123']);
    checkAccount(password_verify('ChangedPassword!123',valueAccount($conn,'SELECT password FROM users WHERE id=1')),'Password securely updated');
    $run('signout_others',['current_password'=>'ChangedPassword!123']);$fresh=$_SESSION;
    $_SESSION=['user_id'=>1,'customer_session_version'=>0];caValidateSession($conn);
    checkAccount(!isset($_SESSION['user_id']),'Old sessions are invalidated');
    $_SESSION=$fresh;caValidateSession($conn);checkAccount(isset($_SESSION['user_id']),'Current session remains valid');
    if(!function_exists('imagecreatetruecolor'))throw new RuntimeException('GD is required for photo tests');
    $im=imagecreatetruecolor(600,400);imagefill($im,0,0,imagecolorallocate($im,31,118,92));imagepng($im,$photo);imagedestroy($im);
    $photoBytes=caPhoto($photo);$dimensions=getimagesizefromstring($photoBytes);
    checkAccount($dimensions[0]===256&&$dimensions[1]===256&&$dimensions['mime']==='image/jpeg','Photo normalized to a 256px square JPEG');
    file_put_contents($invalid,'<?php echo "bad";');rejectAccount(fn()=>caPhoto($invalid),'Non-image uploads rejected');
    caQuery($conn,'UPDATE customer_accounts SET photo=?,photo_updated=NOW() WHERE user_id=1',[$photoBytes]);
    if(in_array('--render-fixture',$argv,true)){
        $directory=sys_get_temp_dir().'/tm-account-preview';if(!is_dir($directory))mkdir($directory);
        file_put_contents($directory.'/photo.jpg',$photoBytes);
        foreach(['overview','details','addresses','orders','wishlist','preferences','security','receipts','support','support-thread','owner-support','receipt','checkout','product'] as $tab){
            $process=proc_open([PHP_BINARY,__FILE__,'render',$database,$tab],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            fclose($pipes[0]);$html=stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);
            if(proc_close($process)!==0 || preg_match('/Warning:|Fatal error|Notice:/',$html.$error))throw new RuntimeException('Render '.$tab.' failed: '.$error.' '.substr($html,0,500));
            file_put_contents($directory.'/'.$tab.'.html',$html);
        }
        checkAccount(true,'All account sections and connected pages rendered without PHP errors');
    }
    if(in_array('--http-fixture',$argv,true)){
        $keepFixture=true;
        echo json_encode(['database'=>$database,'email'=>'updated@example.invalid','password'=>'ChangedPassword!123','shop'=>'account-test-one'])."\n";
        return;
    }
    $conn->query(file_get_contents(__DIR__.'/../databasefile/add_owner_debug_logs.sql'));
    putenv('APP_ENV=test');putenv('OWNER_DEBUG_TOOLS=1');
    odRun($conn,1,1,['csrf'=>'test-csrf','request_key'=>bin2hex(random_bytes(16)),'action'=>'reset_shop','confirmation'=>'account-test-one','password'=>'ExamplePassword!123','acknowledge'=>1],'test-csrf');
    foreach(['customer_accounts','customer_addresses','customer_wishlist','customer_support','customer_support_messages'] as $table)checkAccount((int)valueAccount($conn,"SELECT COUNT(*) FROM $table WHERE shop_id=1")===0,'Shop reset removes '.$table);
    checkAccount((int)valueAccount($conn,'SELECT COUNT(*) FROM customer_accounts WHERE shop_id=2')===1,'Shop reset preserves another shop accounts');
    caDeleteShopData($conn,2);
    checkAccount((int)valueAccount($conn,'SELECT COUNT(*) FROM customer_accounts WHERE shop_id=2')===0,'Permanent deletion cleanup removes remaining account records');
    echo implode("\n",$results)."\nAll customer-account checks passed.\n";
}finally{
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    if($created&&!$keepFixture&&preg_match('/^tm_account_test_[a-f0-9]{12}$/',$database)){$conn->select_db($source);$conn->query("DROP DATABASE `$database`");}
    foreach([$photo,$invalid] as $file)if(is_file($file))unlink($file);
    session_destroy();
}
