<?php
session_start();
require '../config/db.php';

$shop_slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
$shop = null;
if ($shop_slug) {
    $stmt = $conn->prepare("SELECT * FROM shops WHERE slug = ? AND is_active = 1");
    $stmt->bind_param('s', $shop_slug);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $shop) {
    $email   = trim($_POST['email'] ?? '');
    $shop_id = $shop['id'];

    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND shop_id = ? LIMIT 1");
    $stmt->bind_param('si', $email, $shop_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = 'No account found with this email in ' . htmlspecialchars($shop['name']) . '.';
    } else {
        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600);

        $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            shop_id INT NOT NULL,
            otp CHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp (email, shop_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $d = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND shop_id = ?");
        $d->bind_param('si', $email, $shop_id);
        $d->execute();

        $i = $conn->prepare("INSERT INTO password_resets (email, shop_id, otp, expires_at) VALUES (?, ?, ?, ?)");
        $i->bind_param('siss', $email, $shop_id, $otp, $expires);
        $i->execute();

        try {
            require_once '../PHPMailer-master/src/PHPMailer.php';
            require_once '../PHPMailer-master/src/SMTP.php';
            require_once '../PHPMailer-master/src/Exception.php';
            $m = new PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP();
            $m->Host       = 'smtp.gmail.com';
            $m->SMTPAuth   = true;
            $m->Username   = 'sivathetechie24@gmail.com';
            $m->Password   = 'yjqz ofcg htvl qxfu';
            $m->SMTPSecure = 'tls';
            $m->Port       = 587;
            $pc       = $shop['theme_primary'] ?? '#c8a97e';
            $sn       = htmlspecialchars($shop['name']);
            $un       = htmlspecialchars($user['name']);
            $yr       = date('Y');
            $m->setFrom('sivathetechie24@gmail.com', $sn);
            $m->addAddress($email, $user['name']);
            $m->isHTML(true);
            $m->Subject = "Your Password Reset OTP \xe2\x80\x94 {$sn}";
            $m->Body    = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f5f3ef;font-family:Helvetica Neue,Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f3ef;padding:40px 0;'><tr><td>
<table width='100%' cellpadding='0' cellspacing='0' style='max-width:520px;margin:0 auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.1);'>
<tr><td style='background:{$pc};padding:32px 40px;text-align:center;'>
<div style='font-size:28px;font-weight:900;color:#fff;'>{$sn}</div>
<div style='color:rgba(255,255,255,.7);font-size:13px;margin-top:4px;'>Password Reset Request</div>
</td></tr>
<tr><td style='padding:40px;text-align:center;'>
<div style='font-size:40px;margin-bottom:16px;'>&#128272;</div>
<h2 style='margin:0 0 10px;font-size:22px;font-weight:800;color:#1a1208;'>Verify your identity</h2>
<p style='margin:0 0 28px;font-size:15px;color:#666;line-height:1.6;'>Hi <strong>{$un}</strong>, use the OTP below to reset your password. This code expires in <strong>10 minutes</strong>.</p>
<div style='background:#f5f3ef;border-radius:16px;padding:28px 20px;margin-bottom:24px;'>
<div style='font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#999;margin-bottom:14px;'>One-Time Password</div>
<div style='font-size:48px;font-weight:900;letter-spacing:14px;color:{$pc};font-family:monospace;'>{$otp}</div>
</div>
<p style='font-size:13px;color:#aaa;line-height:1.7;'>If you did not request a password reset, you can safely ignore this email.</p>
</td></tr>
<tr><td style='background:#1a1208;padding:24px 40px;text-align:center;'>
<div style='color:rgba(240,236,228,.5);font-size:12.5px;line-height:1.8;'>&copy; {$yr} {$sn}. All rights reserved.</div>
</td></tr>
</table></td></tr></table></body></html>";
            $m->send();
        } catch (\Exception $e) { /* mail failed silently */ }

        $_SESSION['otp_email']   = $email;
        $_SESSION['otp_shop_id'] = $shop_id;
        header('Location: verify_otp.php?shop=' . urlencode($shop_slug) . '&sent=1');
        exit;
    }
}
$primary = $shop['theme_primary'] ?? '#c8a97e';
$bg      = $shop['theme_bg']      ?? '#0d0b08';
$font    = $shop['theme_font']    ?? 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password &mdash; <?= htmlspecialchars($shop['name'] ?? 'TamizhMart') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&family=<?= urlencode($font) ?>:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --primary:<?= htmlspecialchars($primary) ?>;
  --text:#f0ece4; --muted:rgba(240,236,228,.5);
  --input-bg:rgba(255,255,255,.06); --input-border:rgba(255,255,255,.12);
  --glass:rgba(255,255,255,.05); --glass-border:rgba(255,255,255,.1);
}
html,body{min-height:100vh;font-family:'<?= htmlspecialchars($font) ?>','DM Sans',sans-serif;color:var(--text);background:#0c0c0e;display:flex;align-items:center;justify-content:center;padding:24px;}
.bg-dynamic{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 65% 65% at 10% 15%,color-mix(in srgb,var(--primary) 20%,transparent),transparent 60%),radial-gradient(ellipse 55% 55% at 90% 85%,color-mix(in srgb,var(--primary) 12%,transparent),transparent 55%),#0c0c0e;}
.grain{position:fixed;inset:0;z-index:1;opacity:.03;pointer-events:none;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");background-size:180px;}
.ring{position:fixed;border-radius:50%;border:1px solid color-mix(in srgb,var(--primary) 12%,transparent);pointer-events:none;z-index:0;animation:pulse 6s ease-in-out infinite;}
.ring1{width:520px;height:520px;top:50%;left:50%;transform:translate(-50%,-50%);}
.ring2{width:760px;height:760px;top:50%;left:50%;transform:translate(-50%,-50%);animation-delay:-2s;}
@keyframes pulse{0%,100%{opacity:.4;transform:translate(-50%,-50%) scale(1);}50%{opacity:.2;transform:translate(-50%,-50%) scale(1.04);}}
.wrap{position:relative;z-index:10;width:100%;max-width:400px;}
.shop-header{text-align:center;margin-bottom:28px;}
.shop-name{font-family:'Syne',sans-serif;font-weight:800;font-size:24px;letter-spacing:-.5px;color:#fff;}
.shop-sub{font-size:13.5px;color:var(--muted);margin-top:4px;}
.glass-card{background:var(--glass);border:1px solid var(--glass-border);border-radius:20px;padding:32px;backdrop-filter:blur(24px);box-shadow:0 24px 80px rgba(0,0,0,.5);}
.card-icon{width:60px;height:60px;border-radius:16px;margin:0 auto 20px;background:color-mix(in srgb,var(--primary) 15%,transparent);border:1px solid color-mix(in srgb,var(--primary) 25%,transparent);display:flex;align-items:center;justify-content:center;font-size:26px;color:var(--primary);}
.card-title{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;text-align:center;margin-bottom:6px;}
.card-desc{font-size:13.5px;color:var(--muted);text-align:center;margin-bottom:24px;line-height:1.6;}
.input-wrap{position:relative;margin-bottom:16px;}
.form-ctrl{width:100%;padding:14px 20px 14px 46px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:12px;color:var(--text);font-size:14.5px;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit;}
.form-ctrl:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.form-ctrl::placeholder{color:var(--muted);}
.input-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:16px;pointer-events:none;}
.btn-submit{width:100%;padding:14px;border:none;border-radius:12px;background:var(--primary);color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:15px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;}
.btn-submit:hover{opacity:.9;transform:translateY(-1px);}
.alert-err{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.25);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#fca5a5;display:flex;align-items:center;gap:9px;animation:shake .35s ease;}
@keyframes shake{0%,100%{transform:translateX(0);}20%,60%{transform:translateX(-6px);}40%,80%{transform:translateX(6px);}}
.back-link{text-align:center;margin-top:20px;font-size:13.5px;color:var(--muted);}
.back-link a{color:var(--primary);text-decoration:none;font-weight:600;}
</style>
</head>
<body>
<div class="bg-dynamic"></div><div class="grain"></div>
<div class="ring ring1"></div><div class="ring ring2"></div>
<div class="wrap">
  <div class="shop-header">
    <div class="shop-name"><?= htmlspecialchars($shop['name'] ?? 'TamizhMart') ?></div>
    <div class="shop-sub">Reset your password</div>
  </div>
  <div class="glass-card">
    <div class="card-icon"><i class="bi bi-shield-lock"></i></div>
    <div class="card-title">Forgot Password?</div>
    <div class="card-desc">Enter the email linked to your <?= htmlspecialchars($shop['name'] ?? '') ?> account and we'll send a 6-digit OTP.</div>
    <?php if ($error): ?><div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($shop): ?>
    <form method="POST" action="forgot_password.php?shop=<?= htmlspecialchars($shop_slug) ?>">
      <div class="input-wrap">
        <input type="email" name="email" class="form-ctrl" placeholder="Email address" required autofocus
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <i class="bi bi-envelope input-icon"></i>
      </div>
      <button type="submit" class="btn-submit"><i class="bi bi-send"></i> Send OTP</button>
    </form>
    <?php else: ?>
    <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i>Invalid or unknown shop link.</div>
    <?php endif; ?>
    <div class="back-link">Remember your password? <a href="login.php?shop=<?= htmlspecialchars($shop_slug ?? '') ?>"><i class="bi bi-arrow-left"></i> Back to Sign In</a></div>
  </div>
</div>
</body></html>
