<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
if (isset($_SESSION['superadmin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM super_admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['superadmin_id']   = $admin['id'];
            $_SESSION['superadmin_name'] = $admin['name'];
            $_SESSION['superadmin_email']= $admin['email'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Invalid administrative credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Security Gateway — TamizhMart Enterprise</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Syne:wght@800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family:'Plus Jakarta Sans',sans-serif;
    background:#080c14;
    color:#f1f5f9;
    min-height:100vh;
    display:flex; align-items:center; justify-content:center;
    padding:24px;
    position:relative; overflow:hidden;
}

/* Cyber Matrix Background Overlay */
.bg-glow {
    position:fixed; inset:0; z-index:0; pointer-events:none;
    background:
        radial-gradient(ellipse 65% 55% at 50% -10%, rgba(37, 99, 235, 0.15) 0%, transparent 70%),
        radial-gradient(ellipse 40% 40% at 80% 80%, rgba(6, 182, 212, 0.08) 0%, transparent 60%),
        linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px),
        #080c14;
    background-size: 100% 100%, 100% 100%, 40px 40px, 40px 40px;
}

.card {
    position:relative; z-index:1;
    background:rgba(15, 23, 42, 0.65);
    border:1px solid rgba(59, 130, 246, 0.2);
    border-radius:20px; padding:44px 40px;
    width:100%; max-width:430px;
    box-shadow:0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(59, 130, 246, 0.1);
    backdrop-filter: blur(20px);
    animation:fadeUp .45s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

.logo-header {
    display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 24px;
}
.logo {
    width:56px; height:56px; border-radius:14px;
    background:linear-gradient(135deg,#2563eb,#0284c7);
    display:flex; align-items:center; justify-content:center;
    font-size:26px; color: #fff; margin-bottom: 16px;
    box-shadow:0 8px 24px rgba(37, 99, 235, 0.4), inset 0 1px 1px rgba(255,255,255,0.3);
    border: 1px solid rgba(255,255,255,0.25);
}
h1 {
    font-family:'Syne',sans-serif; font-weight:800; font-size:24px;
    color: #ffffff; letter-spacing:-0.5px;
}
.sub {
    font-size:12.5px; margin-top: 4px;
    color:#94a3b8; font-weight: 400;
}
.restricted {
    display:flex; align-items:center; justify-content:center; gap:8px;
    background:rgba(6, 182, 212, 0.08); border:1px solid rgba(6, 182, 212, 0.2);
    border-radius:10px; padding:10px 14px; margin-bottom:24px;
    font-size:11.5px; color:#38bdf8; font-weight:600; font-family:'JetBrains Mono', monospace;
}
label { font-size:12px; font-weight:600; color:#94a3b8; display:block; margin-bottom:7px; }
.inp {
    width:100%; padding:12px 16px;
    background:rgba(15, 23, 42, 0.7);
    border:1px solid rgba(59, 130, 246, 0.2);
    border-radius:10px; color:#f8fafc;
    font-family:'Plus Jakarta Sans',sans-serif; font-size:13.5px; outline:none;
    transition:all .2s;
}
.inp:focus { border-color:#3b82f6; box-shadow:0 0 0 3.5px rgba(59, 130, 246, 0.2); background: rgba(15, 23, 42, 0.9); }
.inp::placeholder { color:#475569; }
.pw { position:relative; }
.pw .inp { padding-right:44px; }
.pw-btn {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:#64748b;
    cursor:pointer; font-size:16px; padding:4px; transition:color .2s;
}
.pw-btn:hover { color:#60a5fa; }
.err {
    background:rgba(239, 68, 68, 0.1); border:1px solid rgba(239, 68, 68, 0.25);
    border-radius:10px; padding:11px 14px;
    display:flex; align-items:center; gap:8px;
    font-size:13px; color:#f87171; margin-bottom:20px;
}
.btn {
    width:100%; padding:13px;
    background:linear-gradient(135deg,#1d4ed8,#2563eb);
    border:1px solid rgba(255,255,255,0.15); border-radius:10px; color:#fff;
    font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:14px;
    cursor:pointer; transition:all .2s; margin-top:8px;
    box-shadow:0 4px 20px rgba(37, 99, 235, 0.35);
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn:hover { filter:brightness(1.15); transform:translateY(-1px); box-shadow:0 6px 24px rgba(37, 99, 235, 0.45); }
.fg { margin-bottom:18px; }
.back {
    display:flex; align-items:center; justify-content:center; gap:6px;
    margin-top:24px; font-size:12.5px; color:#64748b;
    text-decoration:none; transition:color .2s; font-weight: 500;
}
.back:hover { color:#60a5fa; }
</style>
</head>
<body>
<div class="bg-glow"></div>
<div class="card">
    <div class="logo-header">
        <div class="logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h1>Command Center</h1>
        <p class="sub">TamizhMart Enterprise Super Administration</p>
    </div>
    
    <div class="restricted">
        <i class="bi bi-lock-fill"></i>
        AUTHENTICATED ACCESS ONLY &middot; SECURE PORTAL
    </div>

    <?php if ($error): ?>
    <div class="err"><i class="bi bi-exclamation-octagon-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="fg">
            <label>Administrator ID / Email</label>
            <input type="email" name="email" class="inp" placeholder="Authozied E-mail Address"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="fg">
            <label>Security Key</label>
            <div class="pw">
                <input type="password" name="password" class="inp" placeholder="••••••••••••" required id="pw">
                <button type="button" class="pw-btn" onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password';this.querySelector('i').className=i.type==='password'?'bi bi-eye':'bi bi-eye-slash'">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn"><i class="bi bi-key-fill"></i> Authenticate Console</button>
    </form>
    <a href="../owner/login.php" class="back"><i class="bi bi-arrow-left"></i> Return to Merchant Portal</a>
</div>
</body>
</html>