<?php
/**
 * TamizhMart — Gmail SMTP Test
 * URL: http://localhost/tamizhmart/test_mail.php
 * DELETE THIS FILE after testing!
 */

// ── Same path as notifications.php uses ──────────────────────
$src = __DIR__ . '/vendor/phpmailer/src/';

echo "<h2>SMTP Mail Test</h2>";
echo "<pre>";

// Step 1: Check files exist
echo "=== Step 1: PHPMailer Files ===\n";
$files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
$all_found = true;
foreach ($files as $f) {
    $path = $src . $f;
    if (file_exists($path)) {
        echo "✅ Found: vendor/phpmailer/src/$f\n";
    } else {
        echo "❌ MISSING: vendor/phpmailer/src/$f\n";
        $all_found = false;
    }
}

if (!$all_found) {
    echo "\n❌ STOP — Copy the 3 files from PHPMailer/src/ into vendor/phpmailer/src/\n";
    echo "</pre>"; exit;
}

// Step 2: Load PHPMailer
echo "\n=== Step 2: Loading PHPMailer ===\n";
require_once $src . 'Exception.php';
require_once $src . 'PHPMailer.php';
require_once $src . 'SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
echo "✅ PHPMailer loaded\n";

// ════════════════════════════════════════════
// !! FILL THESE IN !!
$YOUR_GMAIL    = 'sivathetechie24@gmail.com'; 
$YOUR_PASSWORD = 'yjqz ofcg htvl qxfu';   
$SEND_TEST_TO  = 'sivabalaji800@gmail.com'; 
// ════════════════════════════════════════════

if (str_contains($YOUR_GMAIL, 'your_gmail')) {
    echo "\n❌ STOP — Fill in YOUR_GMAIL, YOUR_PASSWORD, and SEND_TEST_TO above\n";
    echo "</pre>"; exit;
}

// Step 3: Try sending
echo "\n=== Step 3: Sending Test Email ===\n";
echo "From: $YOUR_GMAIL\n";
echo "To:   $SEND_TEST_TO\n\n";

try {
    $mail = new PHPMailer(true);

    // Debug output — shows every SMTP step
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function($str, $level) {
        echo htmlspecialchars($str) . "\n";
    };

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $YOUR_GMAIL;
    $mail->Password   = $YOUR_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($YOUR_GMAIL, 'SMTP Test');
    $mail->addAddress($SEND_TEST_TO);

    $mail->isHTML(true);
$mail->Subject = "SMTP Mail Test Successful!";

$mail->Body = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Mail Test</title>
</head>

<body style="margin:0;padding:40px;background:#f4f7fb;font-family:Segoe UI,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td align="center">

<table width="650" cellpadding="0" cellspacing="0"
style="background:#ffffff;border-radius:14px;
box-shadow:0 8px 25px rgba(0,0,0,.08);overflow:hidden;">

<tr>
<td style="background:linear-gradient(135deg,#4F46E5,#2563EB);
padding:35px;text-align:center;color:white;">

<h1 style="margin:0;font-size:34px;">
Mail Test Successful
</h1>

<p style="font-size:18px;margin-top:10px;">
Congratulations! Your SMTP configuration is working perfectly <SivaBalaji>.
</p>

</td>
</tr>

<tr>
<td style="padding:35px;">

<h2 style="color:#1E293B;">
Hello Siva Balaji
Im Working Perfectly Fine
</h2>

<p style="font-size:16px;color:#555;line-height:1.8;">
This email confirms that your Project application has successfully connected
to Gmail SMTP, authenticated using your App Password, established a secure
STARTTLS connection, and delivered this email successfully.
</p>

<table width="100%"
style="margin-top:25px;border-collapse:collapse;">

<tr>

<td style="
background:#E8F5E9;
padding:18px;
border-left:5px solid #4CAF50;">
<b style="color:#2E7D32;"> SMTP Connection</b><br>
Connection established successfully.
</td>

</tr>

<tr><td height="15"></td></tr>

<tr>

<td style="
background:#E3F2FD;
padding:18px;
border-left:5px solid #2196F3;">
<b style="color:#1565C0;"> Secure Authentication</b><br>
Authenticated using Gmail App Password.
</td>

</tr>

<tr><td height="15"></td></tr>

<tr>

<td style="
background:#FFF3E0;
padding:18px;
border-left:5px solid #FB8C00;">
<b style="color:#EF6C00;"> Email Delivered</b><br>
HTML email rendered successfully.
</td>

</tr>

</table>

<div style="margin-top:35px;text-align:center;">

<a href="https://github.com"
style="
background:#2563EB;
padding:14px 32px;
color:white;
text-decoration:none;
border-radius:8px;
font-weight:bold;
display:inline-block;">

View Project

</a>

</div>

<hr style="margin:40px 0;border:none;border-top:1px solid #ddd;">

<h3 style="color:#4F46E5;">
📊 Mail Details
</h3>

<table cellpadding="8">

<tr>
<td><b>Server</b></td>
<td>smtp.gmail.com</td>
</tr>

<tr>
<td><b>Port</b></td>
<td>587</td>
</tr>

<tr>
<td><b>Encryption</b></td>
<td>STARTTLS</td>
</tr>

<tr>
<td><b>Mailer</b></td>
<td>PHPMailer</td>
</tr>

<tr>
<td><b>Status</b></td>
<td style="color:green;"><b>SUCCESS</b></td>
</tr>

<tr>
<td><b>Generated</b></td>
<td>'.date("d M Y h:i:s A").'</td>
</tr>

</table>

</td>
</tr>

<tr>

<td
style="
background:#1E293B;
color:#ddd;
padding:25px;
text-align:center;
font-size:14px;">

This is an automated SMTP test email generated using
<strong>PHP + PHPMailer + Gmail SMTP</strong>.

<br><br>

<span style="color:#4ADE80;">
✔ Mail delivery verified successfully.
</span>

</td>

</tr>

</table>

</td>
</tr>
</table>

</body>
</html>';

$mail->AltBody = "Your PHP Mail Test was successful.";

    $mail->send();
    echo "\n✅ SUCCESS — Check your inbox at $SEND_TEST_TO\n";

} catch (Exception $e) {
    echo "\n❌ FAILED — Error: " . $mail->ErrorInfo . "\n";
    echo "\nCommon fixes:\n";
    echo "  - Wrong App Password? Go to myaccount.google.com/apppasswords and create a new one\n";
    echo "  - 2-Step Verification not on? Enable it at myaccount.google.com/security\n";
    echo "  - Using your normal Gmail password? Must use App Password, not your login password\n";
    echo "  - XAMPP blocking port 587? Try changing Port to 465 and SMTPSecure to PHPMailer::ENCRYPTION_SMTPS\n";
}

echo "</pre>";
echo "<br><b style='color:red'>⚠️ DELETE this file from your server after testing!</b>";
?>