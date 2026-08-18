<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
if (isset($_SESSION['owner_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
// Subscription error messages
if (($_GET['err'] ?? '') === 'suspended') {
    $error = 'Your shop has been suspended. Please contact TamizhMart admin to reactivate.';
}
if (($_GET['err'] ?? '') === 'expired') {
    $error = 'Your subscription has expired and grace period has ended. Contact admin to renew.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT o.id, o.name, o.password, s.id AS shop_id FROM owners o LEFT JOIN shops s ON s.owner_id = o.id WHERE o.email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $owner  = $result->fetch_assoc();

    if ($owner && password_verify($password, $owner['password'])) {
        $_SESSION['owner_id']   = $owner['id'];
        $_SESSION['owner_name'] = $owner['name'];
        $_SESSION['shop_id']    = $owner['shop_id'];
        
        $sid = $owner['shop_id'];
        if (!$sid) {
            header("Location: register.php");
            exit;
        }
        $setup = $conn->query("SELECT setting_value FROM shop_settings WHERE shop_id=$sid AND setting_key='setup_complete'")->fetch_assoc();

        // ── Check subscription status ─────────────────────────
        $sub = null;
        if ($sid) {
            $sub_st = $conn->prepare("SELECT ss.*, p.name AS plan_name FROM shop_subscriptions ss JOIN plans p ON ss.plan_id=p.id WHERE ss.shop_id=? ORDER BY ss.id DESC LIMIT 1");
            $sub_st->bind_param('i', $sid);
            $sub_st->execute();
            $sub = $sub_st->get_result()->fetch_assoc();
        }
        if ($sub) {
            $now = time();
            $expires   = strtotime($sub['expires_at']);
            $grace_end = strtotime($sub['grace_until'] ?? $sub['expires_at']);

            if ($sub['status'] === 'suspended') {
                session_destroy();
                header("Location: login.php?err=suspended");
                exit;
            } elseif ($sub['status'] === 'active' || $sub['status'] === 'trial') {
                if ($now > $expires) {
                    $conn->query("UPDATE shop_subscriptions SET status='grace' WHERE id={$sub['id']}");
                    $_SESSION['sub_warning'] = 'Your subscription expired. You have until ' . date('d M Y', $grace_end) . ' to renew.';
                }
            } elseif ($sub['status'] === 'grace') {
                if ($now > $grace_end) {
                    $conn->query("UPDATE shop_subscriptions SET status='suspended' WHERE id={$sub['id']}");
                    $conn->query("UPDATE shops SET is_suspended=1 WHERE id=$sid");
                    session_destroy();
                    header("Location: login.php?err=expired");
                    exit;
                } else {
                    $days_left = ceil(($grace_end - $now) / 86400);
                    $_SESSION['sub_warning'] = "Grace period: $days_left day(s) left. Contact admin to renew.";
                }
            }
        }

        if (!$setup || $setup['setting_value'] !== '1') {
            header("Location: setup.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    } else {
        $error = "Invalid email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Console Login &mdash; TamizhMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --body-bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --card-border: #E2E8F0;
            --navy-blue: #1E293B;
            --text-primary: #1E293B;
            --text-secondary: #475569;
            --text-muted: #64748B;
            --primary-blue: #2563EB;
            --primary-blue-hover: #1D4ED8;
            --danger-red: #DC2626;
            --danger-bg: #FEF2F2;
            --danger-border: #FECACA;
            --radius: 10px;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-card: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        }

        html, body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            color: var(--text-primary);
            background: var(--body-bg);
            -webkit-font-smoothing: antialiased;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-wrap {
            width: 100%;
            max-width: 440px;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-icon-box {
            width: 48px; height: 48px;
            background: var(--navy-blue);
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #FFFFFF;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(30, 41, 59, 0.15);
        }

        .brand-title {
            font-weight: 800;
            font-size: 22px;
            color: var(--text-primary);
            letter-spacing: -0.3px;
        }
        .brand-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .card-panel {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 32px 28px;
            box-shadow: var(--shadow-card);
        }

        .form-heading {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .form-sub {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .form-group-custom {
            margin-bottom: 16px;
        }

        .form-label-custom {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            display: block;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 15px;
            pointer-events: none;
            z-index: 1;
        }

        .form-control-custom {
            width: 100%;
            padding: 10px 14px 10px 40px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 13.5px;
            outline: none;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.02);
        }
        .form-control-custom::placeholder { color: #94A3B8; }
        .form-control-custom:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .pass-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 15px;
            padding: 4px;
            z-index: 1;
        }
        .pass-toggle:hover { color: var(--text-primary); }

        .alert-error {
            padding: 11px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-red);
        }

        .btn-submit {
            width: 100%;
            padding: 11px;
            background: var(--primary-blue);
            border: 1px solid var(--primary-blue);
            border-radius: 6px;
            color: #FFFFFF;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover {
            background: var(--primary-blue-hover);
            border-color: var(--primary-blue-hover);
            color: #FFFFFF;
        }

        .btn-spinner {
            display: none;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #FFFFFF;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading .btn-text { display: none; }
        .loading .btn-spinner { display: block; }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E2E8F0;
        }

        .customer-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9.5px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.15s ease-in-out;
        }
        .customer-link:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            color: var(--text-primary);
        }

        .form-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .form-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }
        .form-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-wrap">

    <div class="brand-header">
        <div class="brand-icon-box"><i class="bi bi-shop"></i></div>
        <div class="brand-title">TamizhMart Seller Console</div>
        <div class="brand-subtitle">Manage orders, inventory, and storefront settings</div>
    </div>

    <div class="card-panel" id="loginCard">

        <h2 class="form-heading">Sign in to your shop</h2>
        <p class="form-sub">Enter your seller credentials to access your dashboard</p>

        <?php if ($error): ?>
        <div class="alert-error" id="errorAlert">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" novalidate>

            <div class="form-group-custom">
                <label class="form-label-custom">Email Address</label>
                <div class="input-wrap">
                    <input
                        type="email"
                        name="email"
                        class="form-control-custom"
                        placeholder="seller@example.com"
                        required
                        autofocus
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                    <i class="bi bi-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-group-custom">
                <label class="form-label-custom">Password</label>
                <div class="input-wrap">
                    <input
                        type="password"
                        name="password"
                        class="form-control-custom"
                        placeholder="••••••••"
                        id="passwordInput"
                        required
                    >
                    <i class="bi bi-lock input-icon"></i>
                    <button type="button" class="pass-toggle" onclick="togglePass()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span class="btn-text"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Seller Central</span>
                <div class="btn-spinner"></div>
            </button>

        </form>

        <div class="divider">OR</div>

        <a href="../auth/login.php" class="customer-link">
            <i class="bi bi-person"></i> Sign in as a Customer
        </a>

    </div>

    <div class="form-footer">
        Don't have a seller account yet? <a href="register.php">Create shop <i class="bi bi-arrow-right"></i></a>
    </div>

</div>

<script>
    function togglePass() {
        const input = document.getElementById('passwordInput');
        const icon  = document.getElementById('eyeIcon');
        input.type  = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }

    document.getElementById('loginForm').addEventListener('submit', function () {
        document.getElementById('submitBtn').classList.add('loading');
    });
</script>

</body>
</html>