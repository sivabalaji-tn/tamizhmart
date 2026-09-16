<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require '../config/db.php';

if (isset($_SESSION['handler_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare('SELECT h.*, s.name AS shop_name FROM shop_handlers h JOIN shops s ON h.shop_id = s.id WHERE h.email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $handler = $stmt->get_result()->fetch_assoc();

        if ($handler && password_verify($password, $handler['password'])) {
            if (!$handler['is_active']) {
                $error = 'Your account has been deactivated. Please contact your shop owner.';
            } else {
                $_SESSION['handler_id']       = $handler['id'];
                $_SESSION['handler_shop_id']  = $handler['shop_id'];
                $_SESSION['handler_name']     = $handler['name'];
                $_SESSION['handler_shop_name'] = $handler['shop_name'];
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handler Login — TamizhMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --handler-accent: #7C3AED;
            --handler-accent-hover: #6D28D9;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 50%, #1a1045 100%);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            padding: 24px;
        }
        .login-card {
            width: 100%; max-width: 400px;
            background: #FFFFFF;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .login-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, var(--handler-accent), #5B21B6);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: #FFFFFF;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(124,58,237,0.35);
        }
        .login-title { font-size: 22px; font-weight: 800; color: #1E293B; text-align: center; letter-spacing: -0.3px; }
        .login-sub { font-size: 13px; color: #64748B; text-align: center; margin-top: 4px; margin-bottom: 28px; }
        .form-group { margin-bottom: 18px; }
        .form-label { font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 6px; }
        .form-input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #CBD5E1;
            border-radius: 8px;
            font-size: 14px; font-family: inherit;
            color: #1E293B;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-input:focus { border-color: var(--handler-accent); box-shadow: 0 0 0 3px rgba(124,58,237,0.12); }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 15px; pointer-events: none; }
        .form-input.with-icon { padding-left: 38px; }
        .toggle-pw { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; cursor: pointer; background: none; border: none; padding: 0; }
        .toggle-pw:hover { color: #475569; }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--handler-accent), #5B21B6);
            border: none; border-radius: 8px;
            color: #FFFFFF; font-size: 14px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            transition: all 0.15s;
            box-shadow: 0 4px 12px rgba(124,58,237,0.3);
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 6px;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(124,58,237,0.4); }
        .btn-login:active { transform: translateY(0); }
        .error-box {
            background: #FEF2F2; border: 1px solid #FECACA;
            border-radius: 8px; padding: 10px 14px;
            color: #B91C1C; font-size: 13px;
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 18px;
        }
        .login-footer { margin-top: 24px; text-align: center; font-size: 12px; color: #94A3B8; }
        .login-footer a { color: #7C3AED; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-icon"><i class="bi bi-person-badge-fill"></i></div>
    <div class="login-title">Handler Login</div>
    <div class="login-sub">Sign in to access your delivery console</div>

    <?php if ($error): ?>
    <div class="error-box"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
        <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <div class="input-wrapper">
                <i class="bi bi-envelope input-icon"></i>
                <input type="email" id="email" name="email" class="form-input with-icon"
                    placeholder="your@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required autofocus>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <div class="input-wrapper">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" id="password" name="password" class="form-input with-icon"
                    placeholder="Enter your password" required>
                <button type="button" class="toggle-pw" onclick="togglePw()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
    </form>

    <div class="login-footer">
        Are you a shop owner? <a href="../owner/login.php">Login here →</a>
    </div>
</div>
<script>
function togglePw() {
    const pw = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
