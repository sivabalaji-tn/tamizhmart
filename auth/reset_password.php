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

$email   = $_SESSION['otp_verified_email']   ?? null;
$shop_id = $_SESSION['otp_verified_shop_id'] ?? null;

if (!$email || !$shop_id || !$shop) {
    header('Location: forgot_password.php?shop=' . urlencode($shop_slug ?? ''));
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND shop_id = ?");
        $stmt->bind_param('ssi', $hash, $email, $shop_id);
        $stmt->execute();

        // Clean session
        unset($_SESSION['otp_verified_email'], $_SESSION['otp_verified_shop_id']);

        $success = 'Password updated! Redirecting to login&hellip;';
    }
}

$primary = $shop['theme_primary'] ?? '#c8a97e';
$bg      = $shop['theme_bg']      ?? '#0d0b08';
$font    = $shop['theme_font']    ?? 'Inter';
$login   = 'login.php?shop=' . urlencode($shop_slug);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password &mdash; <?= htmlspecialchars($shop['name'] ?? 'TamizhMart') ?></title>
<?php if ($success): ?>
<meta http-equiv="refresh" content="2;url=<?= htmlspecialchars($login) ?>">
<?php endif; ?>
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
.form-ctrl{width:100%;padding:14px 46px 14px 46px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:12px;color:var(--text);font-size:14.5px;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit;}
.form-ctrl:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.form-ctrl::placeholder{color:var(--muted);}
.input-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:16px;pointer-events:none;}
.pass-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;padding:4px;transition:color .15s;}
.pass-toggle:hover{color:var(--text);}
/* Strength bar */
.strength-bar{height:4px;border-radius:99px;background:rgba(255,255,255,.08);margin:-10px 0 14px;overflow:hidden;}
.strength-fill{height:100%;border-radius:99px;transition:width .3s,background .3s;}
.strength-label{font-size:11.5px;color:var(--muted);margin-bottom:14px;min-height:16px;}
.btn-submit{width:100%;padding:14px;border:none;border-radius:12px;background:var(--primary);color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:15px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;}
.btn-submit:hover{opacity:.9;transform:translateY(-1px);}
.alert-err{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.25);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#fca5a5;display:flex;align-items:center;gap:9px;animation:shake .35s ease;}
.alert-ok{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#6ee7b7;display:flex;align-items:center;gap:9px;}
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
    <div class="shop-sub">Create a new password</div>
  </div>
  <div class="glass-card">
    <div class="card-icon"><i class="bi bi-key"></i></div>
    <div class="card-title">New Password</div>
    <div class="card-desc">Choose a strong password for your <?= htmlspecialchars($shop['name'] ?? '') ?> account.</div>

    <?php if ($error): ?><div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-ok"><i class="bi bi-check-circle-fill"></i><?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="reset_password.php?shop=<?= htmlspecialchars($shop_slug) ?>">
      <div class="input-wrap">
        <input type="password" name="password" id="pw1" class="form-ctrl" placeholder="New password" required minlength="8">
        <i class="bi bi-lock input-icon"></i>
        <button type="button" class="pass-toggle" onclick="togglePw('pw1','eye1')"><i class="bi bi-eye" id="eye1"></i></button>
      </div>
      <div class="strength-bar"><div class="strength-fill" id="strengthFill" style="width:0;"></div></div>
      <div class="strength-label" id="strengthLabel"></div>

      <div class="input-wrap">
        <input type="password" name="password2" id="pw2" class="form-ctrl" placeholder="Confirm new password" required minlength="8">
        <i class="bi bi-lock input-icon"></i>
        <button type="button" class="pass-toggle" onclick="togglePw('pw2','eye2')"><i class="bi bi-eye" id="eye2"></i></button>
      </div>
      <button type="submit" class="btn-submit"><i class="bi bi-check-lg"></i> Update Password</button>
    </form>
    <?php endif; ?>

    <div class="back-link"><a href="<?= htmlspecialchars($login) ?>"><i class="bi bi-arrow-left"></i> Back to Sign In</a></div>
  </div>
</div>
<script>
function togglePw(id, iconId) {
  const inp = document.getElementById(id);
  const ico = document.getElementById(iconId);
  inp.type  = inp.type === 'password' ? 'text' : 'password';
  ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

const pw1   = document.getElementById('pw1');
const fill  = document.getElementById('strengthFill');
const label = document.getElementById('strengthLabel');
const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
const labels = ['Weak','Fair','Good','Strong'];

if (pw1) pw1.addEventListener('input', () => {
  const v = pw1.value;
  let score = 0;
  if (v.length >= 8)  score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^a-zA-Z0-9]/.test(v)) score++;
  score = Math.max(0, Math.min(score - 1, 3));
  if (!v) { fill.style.width='0'; label.textContent=''; return; }
  fill.style.width = ((score+1)*25) + '%';
  fill.style.background = colors[score];
  label.textContent = labels[score] + ' password';
  label.style.color = colors[score];
});
</script>
</body></html>
