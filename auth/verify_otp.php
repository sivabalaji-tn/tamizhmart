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

$error   = '';
$success = (isset($_GET['sent'])) ? 'OTP sent! Check your email.' : '';
$email   = $_SESSION['otp_email']   ?? null;
$shop_id = $_SESSION['otp_shop_id'] ?? null;

if (!$email || !$shop_id || !$shop) {
    header('Location: forgot_password.php?shop=' . urlencode($shop_slug ?? ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = implode('', $_POST['otp'] ?? []);
    $entered_otp = preg_replace('/\D/', '', $entered_otp);

    $stmt = $conn->prepare("SELECT otp, expires_at FROM password_resets WHERE email = ? AND shop_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('si', $email, $shop_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $error = 'No OTP found. Please request a new one.';
    } elseif (strtotime($row['expires_at']) < time()) {
        $error = 'OTP has expired. Please request a new one.';
    } elseif (!hash_equals($row['otp'], $entered_otp)) {
        $error = 'Incorrect OTP. Please try again.';
    } else {
        // OTP valid — mark as verified in session
        $_SESSION['otp_verified_email']   = $email;
        $_SESSION['otp_verified_shop_id'] = $shop_id;
        // Clean up
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND shop_id = ?");
        $stmt->bind_param('si', $email, $shop_id);
        $stmt->execute();
        unset($_SESSION['otp_email'], $_SESSION['otp_shop_id']);
        header('Location: reset_password.php?shop=' . urlencode($shop_slug));
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
<title>Enter OTP &mdash; <?= htmlspecialchars($shop['name'] ?? 'TamizhMart') ?></title>
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
.wrap{position:relative;z-index:10;width:100%;max-width:420px;}
.shop-header{text-align:center;margin-bottom:28px;}
.shop-name{font-family:'Syne',sans-serif;font-weight:800;font-size:24px;letter-spacing:-.5px;color:#fff;}
.shop-sub{font-size:13.5px;color:var(--muted);margin-top:4px;}
.glass-card{background:var(--glass);border:1px solid var(--glass-border);border-radius:20px;padding:32px;backdrop-filter:blur(24px);box-shadow:0 24px 80px rgba(0,0,0,.5);}
.card-icon{width:60px;height:60px;border-radius:16px;margin:0 auto 20px;background:color-mix(in srgb,var(--primary) 15%,transparent);border:1px solid color-mix(in srgb,var(--primary) 25%,transparent);display:flex;align-items:center;justify-content:center;font-size:26px;color:var(--primary);}
.card-title{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;text-align:center;margin-bottom:6px;}
.card-desc{font-size:13.5px;color:var(--muted);text-align:center;margin-bottom:8px;line-height:1.6;}
.email-pill{display:inline-flex;align-items:center;gap:6px;background:color-mix(in srgb,var(--primary) 12%,transparent);border:1px solid color-mix(in srgb,var(--primary) 22%,transparent);color:var(--primary);padding:5px 14px;border-radius:99px;font-size:13px;font-weight:600;margin:0 auto 24px;display:flex;justify-content:center;}
/* OTP boxes */
.otp-row{display:flex;gap:10px;justify-content:center;margin-bottom:24px;}
.otp-box{
  width:52px;height:62px;border-radius:14px;
  background:var(--input-bg);border:1.5px solid var(--input-border);
  color:var(--text);font-size:26px;font-weight:800;
  text-align:center;outline:none;
  font-family:'Syne',sans-serif;
  transition:border-color .2s,box-shadow .2s,transform .15s;
  caret-color:var(--primary);
}
.otp-box:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);transform:scale(1.06);}
.otp-box.filled{border-color:color-mix(in srgb,var(--primary) 50%,transparent);}
.otp-box.shake{animation:shake .3s ease;}
@keyframes shake{0%,100%{transform:translateX(0);}25%,75%{transform:translateX(-5px);}50%{transform:translateX(5px);}}
.btn-verify{width:100%;padding:14px;border:none;border-radius:12px;background:var(--primary);color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:15px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-verify:hover{opacity:.9;transform:translateY(-1px);}
.btn-verify:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.resend-row{text-align:center;margin-top:18px;font-size:13.5px;color:var(--muted);}
.resend-row a{color:var(--primary);text-decoration:none;font-weight:600;}
.timer{font-weight:700;color:var(--primary);}
.alert-err{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.25);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#fca5a5;display:flex;align-items:center;gap:9px;animation:shake .35s ease;}
.alert-ok{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#6ee7b7;display:flex;align-items:center;gap:9px;}
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
    <div class="shop-sub">Verify your identity</div>
  </div>
  <div class="glass-card">
    <div class="card-icon"><i class="bi bi-patch-check"></i></div>
    <div class="card-title">Enter the OTP</div>
    <div class="card-desc">We sent a 6-digit code to</div>
    <div class="email-pill"><i class="bi bi-envelope-check"></i><?= htmlspecialchars($email) ?></div>

    <?php if ($error): ?><div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success && !$error): ?><div class="alert-ok"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" id="otpForm" action="verify_otp.php?shop=<?= htmlspecialchars($shop_slug) ?>">
      <div class="otp-row" id="otpRow">
        <?php for ($k = 0; $k < 6; $k++): ?>
        <input type="text" name="otp[]" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
               autocomplete="<?= $k === 0 ? 'one-time-code' : 'off' ?>" required>
        <?php endfor; ?>
      </div>
      <button type="submit" class="btn-verify" id="verifyBtn" disabled>
        <i class="bi bi-shield-check"></i> Verify OTP
      </button>
    </form>

    <div class="resend-row">
      Didn't get it?
      <span id="resendTimer">Resend in <span class="timer" id="countdown">120</span>s</span>
      <a href="forgot_password.php?shop=<?= htmlspecialchars($shop_slug) ?>" id="resendLink" style="display:none;">Resend OTP</a>
    </div>
    <div class="back-link"><a href="forgot_password.php?shop=<?= htmlspecialchars($shop_slug) ?>"><i class="bi bi-arrow-left"></i> Use a different email</a></div>
  </div>
</div>
<script>
(function(){
  const boxes   = document.querySelectorAll('.otp-box');
  const btn     = document.getElementById('verifyBtn');
  const form    = document.getElementById('otpForm');

  function checkFilled(){
    const allFilled = [...boxes].every(b => b.value.length === 1);
    btn.disabled = !allFilled;
    boxes.forEach(b => b.classList.toggle('filled', b.value.length === 1));
  }

  boxes.forEach((box, idx) => {
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && idx > 0) boxes[idx - 1].focus();
    });
    box.addEventListener('input', e => {
      box.value = box.value.replace(/\D/g, '').slice(-1);
      if (box.value && idx < boxes.length - 1) boxes[idx + 1].focus();
      checkFilled();
    });
    box.addEventListener('paste', e => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      text.split('').slice(0, 6).forEach((ch, i) => { if (boxes[i]) boxes[i].value = ch; });
      const next = Math.min(text.length, 5);
      boxes[next].focus();
      checkFilled();
    });
    box.addEventListener('focus', () => box.select());
  });

  boxes[0].focus();

  // Countdown + resend
  let secs = 120;
  const cd  = document.getElementById('countdown');
  const rt  = document.getElementById('resendTimer');
  const rl  = document.getElementById('resendLink');
  const iv  = setInterval(() => {
    secs--;
    cd.textContent = secs;
    if (secs <= 0) {
      clearInterval(iv);
      rt.style.display = 'none';
      rl.style.display = 'inline';
    }
  }, 1000);

  <?php if ($error): ?>
  document.querySelectorAll('.otp-box').forEach(b => { b.classList.add('shake'); b.value = ''; });
  boxes[0].focus();
  btn.disabled = true;
  <?php endif; ?>
})();
</script>
</body></html>
