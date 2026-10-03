<?php
session_start();
require_once '../config/db.php';
require_once __DIR__.'/../shop/includes/customer_account.php';
if (empty($_SESSION['owner_id']) || empty($_SESSION['shop_id'])) { header('Location: login.php'); exit; }
$sid=(int)$_SESSION['shop_id']; $oid=(int)$_SESSION['owner_id'];
if (!caQuery($conn,'SELECT s.id FROM shops s JOIN owners o ON o.id=s.owner_id WHERE s.id=? AND o.id=? AND s.is_suspended=0 AND o.is_suspended=0',[$sid,$oid])->get_result()->num_rows) { http_response_code(403); exit('Access denied.'); }
caEnsure($conn); header('Cache-Control: no-store');
$id=(int)($_GET['ticket']??0);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try { caOwnerReply($conn,$oid,$sid,$_POST); $_SESSION['support_flash']=['success','Reply and status saved.']; }
    catch (InvalidArgumentException $e) { $_SESSION['support_flash']=['error',$e->getMessage()]; }
    catch (Throwable $e) { error_log('Owner support action failed: '.$e->getCode()); $_SESSION['support_flash']=['error','Unable to update this request.']; }
    header('Location: customer_support.php?ticket='.$id,true,303); exit;
}
$flash=$_SESSION['support_flash']??null; unset($_SESSION['support_flash']);
$statuses=['open','in_progress','awaiting_customer','approved','rejected','resolved','closed'];
$filter=in_array($_GET['status']??'',$statuses,true)?$_GET['status']:'';
$page=max(1,(int)($_GET['page']??1)); $offset=($page-1)*20;
$ticket=$id?caQuery($conn,'SELECT t.*,u.name,u.email,o.shop_order_number FROM customer_support t JOIN users u ON u.id=t.user_id AND u.shop_id=t.shop_id LEFT JOIN orders o ON o.id=t.order_id AND o.shop_id=t.shop_id AND o.user_id=t.user_id WHERE t.id=? AND t.shop_id=?',[$id,$sid])->get_result()->fetch_assoc():null;
$args=[$sid];$where='t.shop_id=?';if($filter){$where.=' AND t.status=?';$args[]=$filter;}
$tickets=caQuery($conn,"SELECT t.*,u.name FROM customer_support t JOIN users u ON u.id=t.user_id AND u.shop_id=t.shop_id WHERE $where ORDER BY t.updated_at DESC LIMIT 20 OFFSET $offset",$args)->get_result()->fetch_all(MYSQLI_ASSOC);
$page_title='Customer Support';$page_subtitle='Questions, order issues and return requests';
require 'includes/sidebar.php';
?>
<style>
.support-workspace{max-width:1100px;margin:auto;overflow-wrap:anywhere}.support-workspace *{letter-spacing:0}.support-workspace h2{font-size:20px;margin-bottom:12px}.support-workspace label{display:block;font-size:13px;margin-bottom:8px}.support-workspace .input-custom{width:100%;min-height:44px;margin-bottom:18px}.support-workspace textarea{min-height:130px}.support-workspace form{max-width:700px}.support-head{display:flex;gap:16px;justify-content:space-between;align-items:start;flex-wrap:wrap;padding-bottom:20px;border-bottom:1px solid var(--card-border)}.support-thread{max-height:520px;overflow-y:auto;margin:22px 0}.support-message{border-left:3px solid var(--card-border);padding:14px 18px;margin:12px 0}.support-message.owner{border-color:var(--primary);background:var(--primary-light)}.support-message p{white-space:pre-wrap;font-size:14px;line-height:1.7;margin:8px 0 0}.support-message small{font-size:12px;color:var(--text-muted)}.support-list{width:100%;overflow:auto}.support-list table{min-width:620px}.support-workspace .support-note{font-size:13px;color:var(--text-muted);margin:12px 0}.main-content{min-width:0}@media(max-width:600px){.support-workspace .input-custom{font-size:16px}.support-workspace h2{font-size:18px}}
</style>
<div class="support-workspace">
<?php if($flash):?><div role="status" class="alert <?= $flash[0]==='error'?'alert-danger':'alert-success' ?>"><?= caEscape($flash[1]) ?></div><?php endif;?>
<?php if($id && !$ticket):?><div class="alert alert-warning">Request not found.</div><?php endif;?>
<?php if($ticket):?><header class="support-head"><div><h2>#<?= $ticket['id'] ?> &middot; <?= caEscape($ticket['subject']) ?></h2><div><?= caEscape($ticket['name']) ?> &middot; <?= caEscape($ticket['email']) ?></div><p class="support-note"><?= caEscape(str_replace('_',' ',$ticket['kind'])) ?> &middot; <?= caEscape($ticket['created_at']) ?><?php if($ticket['order_id']):?> &middot; Order #<?= caEscape($ticket['shop_order_number']?:$ticket['order_id']) ?><?php endif;?></p></div><a class="btn-ghost-custom" href="customer_support.php"><i class="bi bi-arrow-left"></i> All requests</a></header>
<div class="support-thread"><?php $messages=caQuery($conn,'SELECT * FROM customer_support_messages WHERE ticket_id=? AND shop_id=? ORDER BY id DESC LIMIT 100',[$id,$sid])->get_result()->fetch_all(MYSQLI_ASSOC);foreach(array_reverse($messages) as $m):?><article class="support-message <?= caEscape($m['author']) ?>"><strong><?= $m['author']==='owner'?'Shop team':caEscape($ticket['name']) ?></strong> <small><?= caEscape($m['created_at']) ?></small><p><?= caEscape($m['message']) ?></p></article><?php endforeach;?></div>
<form method="post"><?php caFields('support_owner_reply');?><input type="hidden" name="ticket_id" value="<?= $id ?>"><label for="support-status">Status</label><select class="input-custom" name="status" id="support-status"><?php foreach($statuses as $status):if(in_array($status,['approved','rejected'],true)&&$ticket['kind']!=='return')continue;?><option <?= $ticket['status']===$status?'selected':'' ?> value="<?= $status ?>"><?= caEscape(ucwords(str_replace('_',' ',$status))) ?></option><?php endforeach;?></select><label for="support-reply">Reply to customer</label><textarea id="support-reply" name="message" class="input-custom" maxlength="3000"></textarea><p class="support-note">Return approvals record your decision only. Handle any refund through your payment process.</p><button class="btn-primary-custom"><i class="bi bi-send"></i> Save &amp; reply</button></form>
<?php else:?><form method="get"><label for="filter">Status</label><select class="input-custom" name="status" id="filter"><option value="">All statuses</option><?php foreach($statuses as $s):?><option value="<?= $s ?>" <?= $filter===$s?'selected':'' ?>><?= caEscape(ucwords(str_replace('_',' ',$s))) ?></option><?php endforeach;?></select><button class="btn-ghost-custom"><i class="bi bi-funnel"></i> Filter</button></form><div class="support-list" style="margin-top:24px"><table class="table-glass"><thead><tr><th>Request</th><th>Customer</th><th>Type</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php foreach($tickets as $t):?><tr><td><a href="customer_support.php?ticket=<?= $t['id'] ?>">#<?= $t['id'] ?> <?= caEscape($t['subject']) ?></a></td><td><?= caEscape($t['name']) ?></td><td><?= caEscape(str_replace('_',' ',$t['kind'])) ?></td><td><?= caEscape(str_replace('_',' ',$t['status'])) ?></td><td><?= caEscape($t['updated_at']) ?></td></tr><?php endforeach;?><?php if(!$tickets):?><tr><td colspan="5">No support requests.</td></tr><?php endif;?></tbody></table></div><nav style="display:flex;gap:20px;margin-top:20px"><?php if($page>1):?><a href="?status=<?= caEscape($filter) ?>&page=<?= $page-1 ?>">Previous</a><?php endif;?><?php if(count($tickets)===20):?><a href="?status=<?= caEscape($filter) ?>&page=<?= $page+1 ?>">Next</a><?php endif;?></nav><?php endif;?>
</div>
<?php require 'includes/footer.php'; ?>
