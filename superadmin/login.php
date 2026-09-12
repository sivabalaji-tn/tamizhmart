<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/includes/audit.php';
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
            try {
                saAuditAuth($conn, 'login');
                session_regenerate_id(true);
                header("Location: dashboard.php");
                exit;
            } catch (Throwable $exception) {
                unset($_SESSION['superadmin_id'], $_SESSION['superadmin_name'], $_SESSION['superadmin_email']);
                error_log('Superadmin sign-in audit failed: ' . get_class($exception));
                $error = 'Sign in could not be recorded. Please try again.';
            }
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
<title>Administrator sign in | TamizhMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/admin.css?v=1" rel="stylesheet">
<link href="assets/login.css?v=1" rel="stylesheet">
</head>
<body class="login-page">
<header class="login-brand"><i class="bi bi-bag-check" aria-hidden="true"></i><span>TamizhMart</span></header>
<main class="login-main">
    <div class="login-heading"><span class="login-eyebrow">Platform administration</span><h1>Sign in to your account</h1></div>
    <?php if ($error): ?>
    <div class="alert-error" role="alert"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="login.php">
        <div class="login-field">
            <label class="input-label" for="email">Email address</label>
            <input type="email" name="email" id="email" class="input-custom" placeholder="HQ User ID" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="username" required autofocus>
        </div>
        <div class="login-field">
            <label class="input-label" for="pw">Password</label>
            <div class="password-field">
                <input type="password" name="password" class="input-custom" placeholder="HQ Password`" autocomplete="current-password" required id="pw">
                <button type="button" id="passwordToggle" class="icon-button" aria-label="Show password" title="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
            </div>
        </div>
        <button type="submit" class="btn-primary-custom login-submit">Sign in <i class="bi bi-arrow-right"></i></button>
    </form>
    <a href="../owner/login.php" class="login-back"><i class="bi bi-arrow-left"></i> Merchant sign in</a>
</main>
<footer class="login-footer">TamizhMart &middot; Administrator access</footer>
<script>
document.getElementById('passwordToggle').addEventListener('click', function () {
    const input = document.getElementById('pw');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    this.setAttribute('aria-pressed', String(show));
    this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    this.title = show ? 'Hide password' : 'Show password';
    this.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
</body>
</html>
