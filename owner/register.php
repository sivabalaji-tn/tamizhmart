<?php
session_start();
require '../config/db.php';

// Check if registration is open
$reg_open = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key='registration_open'")->fetch_row();
if ($reg_open && $reg_open[0] === '0') {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Registration Closed</title><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet"><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:"Plus Jakarta Sans",sans-serif;background:#F8FAFC;color:#1E293B;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;}h1{font-weight:700;font-size:24px;margin:16px 0 8px;}p{color:#64748B;font-size:14px;max-width:320px;margin:0 auto 20px;}a{color:#2563EB;font-size:14px;font-weight:600;text-decoration:none;}</style></head><body><div><div style="font-size:48px">🔒</div><h1>Registration Closed</h1><p>New seller registrations are currently disabled by system administrator.</p><a href="login.php">&larr; Return to Sign In</a></div></body></html>');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $shop_name  = trim($_POST['shop_name']);
    $shop_slug  = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $shop_name), '-'));

    if (empty($shop_slug)) {
        $name_slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $shop_slug = !empty($name_slug) ? $name_slug . '-shop' : 'shop-' . substr(uniqid(), -6);
    }

    $shop_slug = substr($shop_slug, 0, 60);

    $check = $conn->prepare("SELECT id FROM owners WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if (empty($name) || empty($email) || empty($password) || empty($shop_name)) {
        $error = "All fields are required.";
    } elseif ($check->num_rows > 0) {
        $error = "This email address is already registered.";
    } else {
        $slugCheck = $conn->prepare("SELECT id FROM shops WHERE slug = ?");
        $slugCheck->bind_param("s", $shop_slug);
        $slugCheck->execute();
        $slugCheck->store_result();
        if ($slugCheck->num_rows > 0) {
            $shop_slug = $shop_slug . '-' . substr(base_convert(mt_rand(0, PHP_INT_MAX), 10, 36), 0, 3);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO owners (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed);
        $stmt->execute();
        $owner_id = $stmt->insert_id;

        $stmt2 = $conn->prepare("INSERT INTO shops (owner_id, name, slug) VALUES (?, ?, ?)");
        $stmt2->bind_param("iss", $owner_id, $shop_name, $shop_slug);
        $stmt2->execute();
        $shop_id = $stmt2->insert_id;

        // Auto-assign 30-day trial
        $trial_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $grace_until   = date('Y-m-d H:i:s', strtotime('+37 days'));
        $conn->query("INSERT INTO shop_subscriptions
            (shop_id, plan_id, status, started_at, expires_at, grace_until)
            VALUES ($shop_id, 1, 'trial', NOW(), '$trial_expires', '$grace_until')");

        $success = true;

        $shop_url = 'http://tamizhmart.optikl.ink/shop/index.php?shop=' . $shop_slug;

        try {
            require_once '../shop/includes/notifications.php';
            sendOwnerRegistrationEmail($email, $name, $shop_name, $shop_slug, $shop_url);
            sendAdminNewShopEmail($name, $email, $shop_name, $shop_slug, $shop_url);
        } catch (Throwable $e) {
            error_log('Registration email error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Registration &mdash; TamizhMart Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F8FAFC;
            --card-bg: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1E293B;
            --text-secondary: #475569;
            --text-muted: #64748B;
            --primary-blue: #2563EB;
            --primary-hover: #1D4ED8;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 960px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .auth-side-banner {
            background: #1E293B;
            color: #FFFFFF;
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .auth-side-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 10% 20%, rgba(37, 99, 235, 0.15), transparent 40%);
        }

        .brand-logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 18px;
            color: #FFFFFF;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 1;
        }

        .brand-logo-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #FFFFFF;
        }

        .banner-content {
            position: relative;
            z-index: 1;
            margin: 40px 0;
        }

        .banner-content h2 {
            font-weight: 800;
            font-size: 26px;
            line-height: 1.3;
            margin-bottom: 12px;
        }

        .banner-content p {
            color: #94A3B8;
            font-size: 14px;
            line-height: 1.6;
        }

        .feature-bullets {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }

        .feature-bullet {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #CBD5E1;
        }

        .feature-bullet i {
            color: #10B981;
            font-size: 16px;
        }

        .banner-footer {
            font-size: 12px;
            color: #64748B;
            position: relative;
            z-index: 1;
        }

        .auth-form-panel {
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 28px;
        }

        .form-header h1 {
            font-weight: 700;
            font-size: 22px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .form-header p {
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .form-group-custom {
            margin-bottom: 16px;
        }

        .form-label-custom {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-control-custom {
            width: 100%;
            padding: 10px 12px 10px 36px;
            font-size: 13.5px;
            color: var(--text-primary);
            background-color: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            outline: none;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control-custom:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-submit-merchant {
            width: 100%;
            padding: 11px;
            background-color: var(--primary-blue);
            color: #FFFFFF;
            font-weight: 600;
            font-size: 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-submit-merchant:hover {
            background-color: var(--primary-hover);
        }

        .alert-custom {
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FCA5A5; }
        .alert-success { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

        @media (max-width: 840px) {
            .auth-container { grid-template-columns: 1fr; }
            .auth-side-banner { display: none; }
            .auth-form-panel { padding: 32px 24px; }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <!-- Side Banner -->
    <div class="auth-side-banner">
        <div class="brand-logo-badge">
            <div class="brand-logo-icon"><i class="bi bi-shop"></i></div>
            <span>TamizhMart</span>
        </div>
        <div class="banner-content">
            <h2>Launch Your E-Commerce Storefront</h2>
            <p>Join thousands of independent merchants growing their online business with our enterprise seller tools.</p>
            <div class="feature-bullets">
                <div class="feature-bullet"><i class="bi bi-check-circle-fill"></i> Instant 30-Day Free Trial</div>
                <div class="feature-bullet"><i class="bi bi-check-circle-fill"></i> Razorpay &amp; COD Payment Gateway</div>
                <div class="feature-bullet"><i class="bi bi-check-circle-fill"></i> Custom Domain &amp; Theme Controls</div>
                <div class="feature-bullet"><i class="bi bi-check-circle-fill"></i> Real-time Order &amp; Stock Sync</div>
            </div>
        </div>
        <div class="banner-footer">
            &copy; <?= date('Y') ?> TamizhMart Merchant Services.
        </div>
    </div>

    <!-- Registration Form -->
    <div class="auth-form-panel">
        <div class="form-header">
            <h1>Create Merchant Account</h1>
            <p>Get started with your free 30-day seller trial</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-custom alert-error">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert-custom alert-success" style="flex-direction:column;align-items:flex-start;gap:10px;">
            <div style="font-weight:700;font-size:14px;"><i class="bi bi-check-circle-fill"></i> Registration Complete!</div>
            <div style="font-size:13px;line-height:1.5;">Your seller account and shop <strong><?= htmlspecialchars($shop_name) ?></strong> have been provisioned with a 30-day free trial.</div>
            <a href="login.php" class="btn-submit-merchant" style="text-decoration:none;margin-top:4px;">Sign In to Dashboard &rarr;</a>
        </div>
        <?php else: ?>

        <form method="POST" action="register.php">
            <div class="form-group-custom">
                <label class="form-label-custom">Full Name</label>
                <div class="input-wrapper">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="name" class="form-control-custom" placeholder="e.g. Siva Balaji" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group-custom">
                <label class="form-label-custom">Business Email Address</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control-custom" placeholder="seller@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group-custom">
                <label class="form-label-custom">Shop / Business Name</label>
                <div class="input-wrapper">
                    <i class="bi bi-shop input-icon"></i>
                    <input type="text" name="shop_name" class="form-control-custom" placeholder="e.g. Royal Organics Store" value="<?= htmlspecialchars($_POST['shop_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group-custom">
                <label class="form-label-custom">Password</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="form-control-custom" placeholder="Minimum 6 characters" required>
                </div>
            </div>

            <button type="submit" class="btn-submit-merchant">
                Create Seller Account <i class="bi bi-arrow-right"></i>
            </button>

            <div style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-muted);">
                Already have a merchant account? <a href="login.php" style="color:var(--primary-blue);font-weight:600;text-decoration:none;">Sign In</a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

</body>
</html>