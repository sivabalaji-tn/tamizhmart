<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
$page_title    = 'Social Links';
$page_subtitle = 'Connect your social media and contact channels';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

// Load existing settings
$settings = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'whatsapp'  => $_POST['whatsapp']  ?? '',
        'instagram' => $_POST['instagram'] ?? '',
        'facebook'  => $_POST['facebook']  ?? '',
        'twitter'   => $_POST['twitter']   ?? '',
        'youtube'   => $_POST['youtube']   ?? '',
        'website'   => $_POST['website']   ?? '',
        'email_contact' => $_POST['email_contact'] ?? '',
        'phone'     => $_POST['phone']     ?? '',
        'address'   => $_POST['address']   ?? '',
    ];

    foreach ($fields as $key => $value) {
        $k = $conn->real_escape_string($key);
        $v = $conn->real_escape_string(trim($value));
        $conn->query("INSERT INTO shop_settings (shop_id, setting_key, setting_value) VALUES ($shop_id, '$k', '$v')
            ON DUPLICATE KEY UPDATE setting_value='$v'");
    }
    $settings = array_merge($settings, $fields);
    $success  = 'Social links saved successfully.';
}

$socials = [
    ['key'=>'whatsapp',     'icon'=>'whatsapp',     'label'=>'WhatsApp Number',    'placeholder'=>'+91 98765 43210',                 'prefix'=>'',          'color'=>'#25D366'],
    ['key'=>'instagram',    'icon'=>'instagram',    'label'=>'Instagram Handle',   'placeholder'=>'@yourshop',                      'prefix'=>'instagram.com/', 'color'=>'#E1306C'],
    ['key'=>'facebook',     'icon'=>'facebook',     'label'=>'Facebook Page',      'placeholder'=>'facebook.com/yourshop',           'prefix'=>'',          'color'=>'#1877F2'],
    ['key'=>'twitter',      'icon'=>'twitter-x',    'label'=>'X (Twitter) Handle', 'placeholder'=>'@yourshop',                      'prefix'=>'x.com/',    'color'=>'#0F172A'],
    ['key'=>'youtube',      'icon'=>'youtube',      'label'=>'YouTube Channel',    'placeholder'=>'youtube.com/@yourshop',           'prefix'=>'',          'color'=>'#FF0000'],
    ['key'=>'website',      'icon'=>'globe2',       'label'=>'Website URL',        'placeholder'=>'https://yourwebsite.com',         'prefix'=>'',          'color'=>'#2563EB'],
    ['key'=>'email_contact','icon'=>'envelope',     'label'=>'Contact Email',      'placeholder'=>'hello@yourshop.com',             'prefix'=>'',          'color'=>'#F59E0B'],
    ['key'=>'phone',        'icon'=>'telephone',    'label'=>'Phone Number',       'placeholder'=>'+91 98765 43210',                'prefix'=>'',          'color'=>'#10B981'],
    ['key'=>'address',      'icon'=>'geo-alt',      'label'=>'Shop Address',       'placeholder'=>'123 Main St, Chennai, Tamil Nadu','prefix'=>'',          'color'=>'#EF4444'],
];
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-glass animate-in">
            <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-share-fill" style="color:var(--primary);margin-right:6px;"></i>Social Channels & Contact Information</div>
            <div class="section-sub" style="margin-bottom:20px;">These details appear in your storefront footer to help buyers connect with you.</div>
            <form method="POST">
                <div style="display:grid;gap:14px;">
                    <?php foreach ($socials as $s): ?>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;border-radius:8px;background:#F8FAFC;border:1px solid #E2E8F0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-<?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;font-size:18px;"></i>
                        </div>
                        <div style="flex:1;">
                            <div class="form-label-custom"><?= $s['label'] ?></div>
                            <input type="text" name="<?= $s['key'] ?>"
                                class="input-custom"
                                placeholder="<?= $s['placeholder'] ?>"
                                value="<?= htmlspecialchars($settings[$s['key']] ?? '') ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn-primary-custom" style="margin-top:20px;">
                    <i class="bi bi-check-lg"></i> Save Social Channels
                </button>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-glass animate-in d1">
            <div style="font-size:13.5px;font-weight:700;color:var(--text-primary);margin-bottom:12px;"><i class="bi bi-eye-fill" style="color:var(--primary);margin-right:6px;"></i>Active Channels Preview</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($socials as $s):
                    if (empty($settings[$s['key']])) continue;
                ?>
                <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;">
                    <i class="bi bi-<?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;"></i>
                    <span style="color:var(--text-secondary);font-weight:500;"><?= $s['label'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty(array_filter(array_map(fn($s) => $settings[$s['key']] ?? '', $socials)))): ?>
                <div style="color:var(--text-muted);font-size:12.5px;padding:6px 0;">Fill in any contact link on the left to activate footer shortcuts.</div>
                <?php endif; ?>
            </div>
            <div style="margin-top:16px;padding:12px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;font-size:12px;color:#1E3A8A;">
                <i class="bi bi-info-circle-fill" style="color:var(--primary);margin-right:4px;"></i>
                Social links automatically render in your shop's footer once saved.
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
