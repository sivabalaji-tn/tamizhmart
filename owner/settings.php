<?php
session_start();
require '../config/db.php';

$page_title    = 'Store Settings';
$page_subtitle = 'Manage your shop information and branding';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $name         = trim($_POST['name']);
        $description  = trim($_POST['description']);
        $phone        = trim($_POST['phone'] ?? '');
        $address_text = trim($_POST['address_setting'] ?? '');
        $announcement = trim($_POST['announcement'] ?? '');
        $ann_active   = isset($_POST['announcement_active']) ? 1 : 0;

        // Update shop basic info
        $stmt = $conn->prepare("UPDATE shops SET name=?, description=?, announcement=?, announcement_active=? WHERE id=? AND owner_id=?");
        $stmt->bind_param("sssiii", $name, $description, $announcement, $ann_active, $shop_id, $_SESSION['owner_id']);
        $stmt->execute();

        // Update shop_settings for phone and address
        foreach (['phone'=>$phone, 'address'=>$address_text] as $k=>$v) {
            $conn->query("INSERT INTO shop_settings (shop_id, setting_key, setting_value) VALUES ($shop_id, '$k', '" . $conn->real_escape_string($v) . "')
                ON DUPLICATE KEY UPDATE setting_value = '" . $conn->real_escape_string($v) . "'");
        }

        $success = "Store information updated.";
        // Refresh shop
        $s2 = $conn->prepare("SELECT * FROM shops WHERE id=?");
        $s2->bind_param("i", $shop_id);
        $s2->execute();
        $shop = $s2->get_result()->fetch_assoc();

    } elseif ($action === 'update_logo') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $f   = $_FILES['logo'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $name = uniqid('logo_').'.'.$ext;
                if (move_uploaded_file($f['tmp_name'], "../assets/uploads/logos/$name")) {
                    $stmt = $conn->prepare("UPDATE shops SET logo=? WHERE id=?");
                    $stmt->bind_param("si", $name, $shop_id);
                    $stmt->execute();
                    $shop['logo'] = $name;
                    $success = "Logo updated.";
                }
            } else { $error = "Only JPG, PNG, WEBP allowed."; }
        }

    } elseif ($action === 'update_banner') {
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === 0) {
            $f   = $_FILES['banner'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $name = uniqid('banner_').'.'.$ext;
                if (move_uploaded_file($f['tmp_name'], "../assets/uploads/banners/$name")) {
                    $stmt = $conn->prepare("UPDATE shops SET banner=? WHERE id=?");
                    $stmt->bind_param("si", $name, $shop_id);
                    $stmt->execute();
                    $shop['banner'] = $name;
                    $success = "Banner updated.";
                }
            } else { $error = "Only JPG, PNG, WEBP allowed."; }
        }

    } elseif ($action === 'remove_logo') {
        $conn->query("UPDATE shops SET logo=NULL WHERE id=$shop_id");
        $shop['logo'] = null;
        $success = "Logo removed.";

    } elseif ($action === 'remove_banner') {
        $conn->query("UPDATE shops SET banner=NULL WHERE id=$shop_id");
        $shop['banner'] = null;
        $success = "Banner removed.";

    } elseif ($action === 'update_tax') {
        $tax_enabled = isset($_POST['tax_enabled']) ? '1' : '0';
        $cgst_rate   = min(28, max(0, floatval($_POST['cgst_rate'] ?? 9)));
        $sgst_rate   = min(28, max(0, floatval($_POST['sgst_rate'] ?? 9)));
        foreach ([
            'tax_enabled' => $tax_enabled,
            'cgst_rate'   => $cgst_rate,
            'sgst_rate'   => $sgst_rate,
        ] as $k => $v) {
            $v_esc = $conn->real_escape_string($v);
            $conn->query("INSERT INTO shop_settings (shop_id, setting_key, setting_value)
                          VALUES ($shop_id, '$k', '$v_esc')
                          ON DUPLICATE KEY UPDATE setting_value='$v_esc'");
        }
        $success = "Tax settings saved.";

    } elseif ($action === 'update_razorpay') {
        $rz_enabled = isset($_POST['razorpay_enabled']) ? '1' : '0';
        $rz_key_id  = trim($_POST['razorpay_key_id'] ?? '');
        $rz_secret  = trim($_POST['razorpay_key_secret'] ?? '');

        if ($rz_enabled === '1' && (!str_starts_with($rz_key_id, 'rzp_') || empty($rz_secret))) {
            $error = "Please enter valid Razorpay Key ID (starts with rzp_) and Secret.";
        } else {
            foreach ([
                'razorpay_enabled'    => $rz_enabled,
                'razorpay_key_id'     => $rz_key_id,
                'razorpay_key_secret' => $rz_secret,
            ] as $k => $v) {
                $v_esc = $conn->real_escape_string($v);
                $conn->query("INSERT INTO shop_settings (shop_id, setting_key, setting_value)
                              VALUES ($shop_id, '$k', '$v_esc')
                              ON DUPLICATE KEY UPDATE setting_value='$v_esc'");
            }
            $success = "Razorpay settings saved.";
        }
    }
}

// Fetch extra settings
$settings = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php elseif ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- Left Column -->
    <div class="col-lg-7">

        <!-- Basic Info -->
        <div class="card-glass animate-in mb-3">
            <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-shop" style="color:var(--primary);margin-right:6px;"></i>Shop Profile Details</div>
            <div class="section-sub" style="margin-bottom:18px;">Basic store information visible to your customers</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_info">
                <div style="display:grid;gap:14px;">
                    <div>
                        <div class="form-label-custom">Shop Name *</div>
                        <input type="text" name="name" class="input-custom" value="<?= htmlspecialchars($shop['name']) ?>" required>
                    </div>
                    <div>
                        <div class="form-label-custom">Description</div>
                        <textarea name="description" class="input-custom" placeholder="Tell customers about your shop..."><?= htmlspecialchars($shop['description'] ?? '') ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <div class="form-label-custom">Support Phone</div>
                            <input type="text" name="phone" class="input-custom" placeholder="+91 00000 00000" value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">
                        </div>
                        <div>
                            <div class="form-label-custom">Storefront Handle</div>
                            <div style="padding:9px 12px;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:var(--radius-sm);font-size:13px;color:var(--text-muted);">
                                /<?= htmlspecialchars($shop['slug']) ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="form-label-custom">Shop Address</div>
                        <textarea name="address_setting" class="input-custom" placeholder="Physical store address (shown on invoice & footer)" style="min-height:65px;"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
                    </div>

                    <!-- Announcement Bar -->
                    <div style="padding:14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:var(--radius-sm);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <div>
                                <div style="font-size:13px;font-weight:600;color:var(--text-primary);">Header Announcement Banner</div>
                                <div style="font-size:11.5px;color:var(--text-muted);">Displayed prominently at top of your store page</div>
                            </div>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="announcement_active" value="1" <?= $shop['announcement_active'] ? 'checked' : '' ?> style="accent-color:var(--primary);width:16px;height:16px;">
                                <span style="font-size:12.5px;font-weight:600;color:var(--text-secondary);">Active</span>
                            </label>
                        </div>
                        <input type="text" name="announcement" class="input-custom" placeholder="e.g. Special Offer: Free delivery on orders above ₹499!" value="<?= htmlspecialchars($shop['announcement'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn-primary-custom" style="margin-top:18px;">
                    <i class="bi bi-check-lg"></i> Save Profile Settings
                </button>
            </form>
        </div>

    </div>

    <!-- Right Column -->
    <div class="col-lg-5">

        <!-- Logo Upload -->
        <div class="card-glass animate-in d1 mb-3">
            <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-image" style="color:var(--primary);margin-right:6px;"></i>Store Logo</div>
            <div class="section-sub" style="margin-bottom:16px;">Displayed in your store header and invoices</div>

            <div style="display:flex;flex-direction:column;align-items:center;gap:14px;text-align:center;">
                <div style="width:90px;height:90px;border-radius:12px;background:#F1F5F9;border:1px solid #CBD5E1;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                    <?php if ($shop['logo']): ?>
                    <img src="../assets/uploads/logos/<?= htmlspecialchars($shop['logo']) ?>" style="width:100%;height:100%;object-fit:cover;" id="logoPreviewImg">
                    <?php else: ?>
                    <i class="bi bi-shop" style="font-size:36px;color:#94A3B8;" id="logoPlaceholder"></i>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" style="width:100%;">
                    <input type="hidden" name="action" value="update_logo">
                    <input type="file" name="logo" id="logoInput" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    <div style="display:flex;gap:8px;justify-content:center;">
                        <button type="button" class="btn-primary-custom" onclick="document.getElementById('logoInput').click()">
                            <i class="bi bi-upload"></i> Upload Logo
                        </button>
                        <?php if ($shop['logo']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="remove_logo">
                            <button type="submit" class="btn-danger-custom"><i class="bi bi-trash3"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </form>
                <div style="font-size:11.5px;color:var(--text-muted);">PNG, JPG or WEBP &middot; max 5MB &middot; 200&times;200px recommended</div>
            </div>
        </div>

        <!-- Banner Upload -->
        <div class="card-glass animate-in d2 mb-3">
            <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-panorama" style="color:var(--primary);margin-right:6px;"></i>Store Banner</div>
            <div class="section-sub" style="margin-bottom:16px;">Hero header graphic on your shop's homepage</div>

            <div style="display:flex;flex-direction:column;align-items:center;gap:14px;text-align:center;">
                <div style="width:100%;height:110px;border-radius:8px;background:#F1F5F9;border:1px solid #CBD5E1;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                    <?php if ($shop['banner']): ?>
                    <img src="../assets/uploads/banners/<?= htmlspecialchars($shop['banner']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <i class="bi bi-image" style="font-size:32px;color:#94A3B8;"></i>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" style="width:100%;">
                    <input type="hidden" name="action" value="update_banner">
                    <input type="file" name="banner" id="bannerInput" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    <div style="display:flex;gap:8px;justify-content:center;">
                        <button type="button" class="btn-primary-custom" onclick="document.getElementById('bannerInput').click()">
                            <i class="bi bi-upload"></i> Upload Banner
                        </button>
                        <?php if ($shop['banner']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="remove_banner">
                            <button type="submit" class="btn-danger-custom"><i class="bi bi-trash3"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </form>
                <div style="font-size:11.5px;color:var(--text-muted);">Recommended 1280&times;400px &middot; max 5MB</div>
            </div>
        </div>

        <!-- Shop URL Info -->
        <div class="card-glass animate-in d3">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:700;margin-bottom:8px;">Live Store URL</div>
            <div style="display:flex;align-items:center;gap:8px;padding:9px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:var(--radius-sm);">
                <i class="bi bi-link-45deg" style="color:var(--primary);flex-shrink:0;"></i>
                <span style="font-size:12.5px;color:var(--text-secondary);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    shop.tamizhmart.optikl.ink/<?= htmlspecialchars($shop['slug']) ?>
                </span>
                <button onclick="navigator.clipboard.writeText('shop.tamizhmart.optikl.ink/<?= $shop['slug'] ?>').then(()=>this.innerHTML='<i class=\'bi bi-check-lg\'></i>')"
                    style="background:none;border:none;color:var(--text-muted);cursor:pointer;padding:2px 4px;font-size:14px;" title="Copy URL">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     TAX SETTINGS CARD
     ═══════════════════════════════════════════════════ -->
<div style="margin-top:20px;" class="animate-in d2">
    <div class="card-glass">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
            <div style="width:36px;height:36px;border-radius:8px;background:#FFFBEB;border:1px solid #FDE68A;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-percent" style="color:#D97706;font-size:18px;"></i>
            </div>
            <div>
                <div style="font-weight:700;font-size:16px;color:var(--text-primary);">GST &amp; Invoicing Tax Rules</div>
                <div style="font-size:12px;color:var(--text-muted);">Set CGST &amp; SGST rates for printed customer invoices. Set 0% if not GST registered.</div>
            </div>
        </div>
        <hr style="border-color:#E2E8F0;margin:14px 0;">
        <?php
        $tax_enabled = $settings['tax_enabled'] ?? '0';
        $cgst_rate   = min(28, max(0, floatval($settings['cgst_rate'] ?? 9)));
        $sgst_rate   = min(28, max(0, floatval($settings['sgst_rate'] ?? 9)));
        ?>
        <?php if ($tax_enabled === '1'): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:14px;">
            <span style="width:6px;height:6px;background:#059669;border-radius:50%;display:inline-block;"></span>
            GST active &mdash; CGST <?= $cgst_rate ?>% + SGST <?= $sgst_rate ?>% = <?= floatval($cgst_rate)+floatval($sgst_rate) ?>%
        </div>
        <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:#F1F5F9;border:1px solid #E2E8F0;color:var(--text-muted);padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:14px;">
            <span style="width:6px;height:6px;background:var(--text-muted);border-radius:50%;display:inline-block;"></span>
            Tax Disabled &mdash; invoices will show 0% GST
        </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="update_tax">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:var(--radius-sm);">
                    <input type="checkbox" name="tax_enabled" value="1" id="taxToggle"
                        <?= $tax_enabled === '1' ? 'checked' : '' ?>
                        onchange="document.getElementById('taxFields').style.display=this.checked?'grid':'none'"
                        style="width:16px;height:16px;accent-color:var(--primary);flex-shrink:0;">
                    <div>
                        <div style="font-weight:600;font-size:13.5px;color:var(--text-primary);">Enable GST on customer invoices</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">Disable if your store is un-registered or operating under composition scheme</div>
                    </div>
                </label>
                <div id="taxFields" style="display:<?= $tax_enabled === '1' ? 'grid' : 'none' ?>;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">CGST Rate (%)</div>
                        <input type="number" name="cgst_rate" class="input-custom" min="0" max="28" step="0.5" value="<?= htmlspecialchars($cgst_rate) ?>" placeholder="9">
                    </div>
                    <div>
                        <div class="form-label-custom">SGST Rate (%)</div>
                        <input type="number" name="sgst_rate" class="input-custom" min="0" max="28" step="0.5" value="<?= htmlspecialchars($sgst_rate) ?>" placeholder="9">
                    </div>
                </div>
                <button type="submit" class="btn-primary-custom" style="justify-content:center;padding:10px;">
                    <i class="bi bi-check-lg"></i> Save Tax Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     RAZORPAY PAYMENT SETTINGS CARD
     ═══════════════════════════════════════════════════ -->
<div style="margin-top:20px;" class="animate-in d3">
    <div class="card-glass">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
            <div style="width:36px;height:36px;border-radius:8px;background:#F0F9FF;border:1px solid #BAE6FD;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-credit-card-2-front" style="color:#0EA5E9;font-size:18px;"></i>
            </div>
            <div>
                <div style="font-weight:700;font-size:16px;color:var(--text-primary);">Online Payment Gateway (Razorpay)</div>
                <div style="font-size:12px;color:var(--text-muted);">Accept UPI, Cards, Net Banking &amp; Wallets directly into your account</div>
            </div>
            <span style="margin-left:auto;font-size:11px;font-weight:700;background:#0F172A;color:#fff;padding:3px 8px;border-radius:4px;">RAZORPAY</span>
        </div>
        <hr style="border-color:#E2E8F0;margin:14px 0;">

        <?php
        $rz = [
            'enabled' => $settings['razorpay_enabled'] ?? '0',
            'key_id'  => $settings['razorpay_key_id'] ?? '',
            'secret'  => $settings['razorpay_key_secret'] ?? '',
        ];
        ?>

        <?php if ($rz['enabled'] === '1' && !empty($rz['key_id'])): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:14px;">
            <span style="width:6px;height:6px;background:#059669;border-radius:50%;display:inline-block;"></span>
            Online Payments Active
        </div>
        <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:#F1F5F9;border:1px solid #E2E8F0;color:var(--text-muted);padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:14px;">
            <span style="width:6px;height:6px;background:var(--text-muted);border-radius:50%;display:inline-block;"></span>
            Inactive — Cash on Delivery (COD) Only
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="update_razorpay">
            <div style="display:flex;flex-direction:column;gap:14px;">

                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:var(--radius-sm);">
                    <input type="checkbox" name="razorpay_enabled" value="1" id="rzToggle"
                        <?= $rz['enabled'] === '1' ? 'checked' : '' ?>
                        onchange="document.getElementById('rzFields').style.display=this.checked?'flex':'none'"
                        style="width:16px;height:16px;accent-color:var(--primary);flex-shrink:0;">
                    <div>
                        <div style="font-weight:600;font-size:13.5px;color:var(--text-primary);">Enable Razorpay online payments</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">Customers can select "Pay Online" at checkout</div>
                    </div>
                </label>

                <div id="rzFields" style="display:<?= $rz['enabled'] === '1' ? 'flex' : 'none' ?>;flex-direction:column;gap:12px;">

                    <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:var(--radius-sm);padding:10px 12px;font-size:12px;color:#0369A1;">
                        <i class="bi bi-info-circle-fill" style="margin-right:4px;"></i>
                        Retrieve API keys from your <a href="https://dashboard.razorpay.com/app/keys" target="_blank" style="color:var(--primary);font-weight:700;">Razorpay Dashboard &rarr; Settings &rarr; API Keys</a>.
                    </div>

                    <div>
                        <div class="form-label-custom">Key ID (starts with rzp_test_ or rzp_live_)</div>
                        <input type="text" name="razorpay_key_id" class="input-custom"
                            placeholder="rzp_live_xxxxxxxxxxxx"
                            value="<?= htmlspecialchars($rz['key_id']) ?>"
                            autocomplete="off">
                    </div>

                    <div>
                        <div class="form-label-custom">Key Secret</div>
                        <div style="position:relative;">
                            <input type="password" name="razorpay_key_secret" id="rzSecret" class="input-custom"
                                placeholder="Your Razorpay secret key"
                                value="<?= htmlspecialchars($rz['secret']) ?>"
                                autocomplete="new-password"
                                style="padding-right:40px;">
                            <button type="button" onclick="toggleSecret()"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:15px;" title="Show/hide">
                                <i class="bi bi-eye" id="rzEyeIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary-custom" style="justify-content:center;padding:10px;">
                    <i class="bi bi-check-lg"></i> Save Payment Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSecret() {
    const f = document.getElementById('rzSecret');
    const i = document.getElementById('rzEyeIcon');
    if (f.type === 'password') { f.type = 'text';     i.className = 'bi bi-eye-slash'; }
    else                        { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>

<?php require 'includes/footer.php'; ?>