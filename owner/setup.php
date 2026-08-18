<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
// Must be logged in as owner
if (!isset($_SESSION['owner_id'])) {
    header("Location: login.php");
    exit;
}

$shop_id  = $_SESSION['shop_id'];
$owner_id = $_SESSION['owner_id'];

// Check if already completed setup
$setup_done = $conn->query("SELECT setting_value FROM shop_settings WHERE shop_id=$shop_id AND setting_key='setup_complete'")->fetch_assoc();
if ($setup_done && $setup_done['setting_value'] === '1') {
    header("Location: dashboard.php");
    exit;
}

$shop = $conn->query("SELECT * FROM shops WHERE id=$shop_id")->fetch_assoc();
$step = (int)($_GET['step'] ?? 1);
$step = max(1, min(5, $step));
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1') { // Basic info
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $stmt = $conn->prepare("UPDATE shops SET name=?, description=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $desc, $shop_id);
        $stmt->execute();
        header("Location: setup.php?step=2");
        exit;

    } elseif ($action === 'step2') { // Logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $f   = $_FILES['logo'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $name = uniqid('logo_').'.'.$ext;
                if (move_uploaded_file($f['tmp_name'], "../assets/uploads/logos/$name")) {
                    $stmt = $conn->prepare("UPDATE shops SET logo=? WHERE id=?");
                    $stmt->bind_param("si", $name, $shop_id);
                    $stmt->execute();
                }
            }
        }
        header("Location: setup.php?step=3");
        exit;

    } elseif ($action === 'step3') { // Theme
        $primary   = $_POST['primary'] ?? '#2563eb';
        $secondary = $_POST['secondary'] ?? '#1d4ed8';
        $bg        = $_POST['bg'] ?? '#f8fafc';
        $font      = $_POST['font'] ?? 'Poppins';
        $stmt = $conn->prepare("UPDATE shops SET theme_primary=?, theme_secondary=?, theme_bg=?, theme_font=? WHERE id=?");
        $stmt->bind_param("ssssi", $primary, $secondary, $bg, $font, $shop_id);
        $stmt->execute();
        header("Location: setup.php?step=4");
        exit;

    } elseif ($action === 'step4') { // First category + product
        $already = $conn->query("SELECT setting_value FROM shop_settings WHERE shop_id=$shop_id AND setting_key='setup_complete'")->fetch_assoc();
        if ($already && $already['setting_value'] === '1') {
            header("Location: dashboard.php"); exit;
        }
        $cat_name = trim($_POST['category_name'] ?? '');
        if ($cat_name) {
            $existing_cat = $conn->query("SELECT id FROM categories WHERE shop_id=$shop_id AND name='".addslashes($cat_name)."' LIMIT 1")->fetch_assoc();
            if ($existing_cat) {
                $cat_id = $existing_cat['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (shop_id, name, is_active) VALUES (?,?,1)");
                $stmt->bind_param("is", $shop_id, $cat_name);
                $stmt->execute();
                $cat_id = $stmt->insert_id;
            }

            $prod_name  = trim($_POST['product_name'] ?? '');
            $prod_price = (float)($_POST['product_price'] ?? 0);
            $prod_stock = (int)($_POST['product_stock'] ?? 10);
            if ($prod_name && $prod_price > 0) {
                $existing_prod = $conn->query("SELECT id FROM products WHERE shop_id=$shop_id LIMIT 1")->fetch_assoc();
                if (!$existing_prod) {
                    $stmt2 = $conn->prepare("INSERT INTO products (shop_id, category_id, name, price, stock, is_active) VALUES (?,?,?,?,?,1)");
                    $stmt2->bind_param("iisdi", $shop_id, $cat_id, $prod_name, $prod_price, $prod_stock);
                    $stmt2->execute();
                }
            }
        }
        header("Location: setup.php?step=5");
        exit;

    } elseif ($action === 'complete') { // Finish
        $announcement = trim($_POST['announcement'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        if ($announcement) {
            $conn->query("UPDATE shops SET announcement='".addslashes($announcement)."', announcement_active=1 WHERE id=$shop_id");
        }
        if ($phone) {
            $conn->query("INSERT INTO shop_settings (shop_id,setting_key,setting_value) VALUES ($shop_id,'phone','".addslashes($phone)."') ON DUPLICATE KEY UPDATE setting_value='".addslashes($phone)."'");
        }
        $conn->query("INSERT INTO shop_settings (shop_id,setting_key,setting_value) VALUES ($shop_id,'setup_complete','1') ON DUPLICATE KEY UPDATE setting_value='1'");
        header("Location: dashboard.php?welcome=1");
        exit;
    }
}

// Refresh shop data
$shop = $conn->query("SELECT * FROM shops WHERE id=$shop_id")->fetch_assoc();
$steps_info = [
    1 => ['icon'=>'shop',        'title'=>'Shop Profile', 'desc'=>'Name & description'],
    2 => ['icon'=>'image',       'title'=>'Store Logo',   'desc'=>'Upload brand logo'],
    3 => ['icon'=>'palette2',    'title'=>'Theme',        'desc'=>'Select brand colors'],
    4 => ['icon'=>'box-seam',    'title'=>'Catalog',      'desc'=>'Add category & product'],
    5 => ['icon'=>'check-circle','title'=>'Launch',       'desc'=>'Final touches'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Onboarding Wizard &mdash; TamizhMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --border-color: #CBD5E1;
            --text-primary: #1E293B;
            --text-muted: #64748B;
            --primary: #2563EB;
            --primary-hover: #1D4ED8;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .setup-wrap {
            max-width: 660px;
            margin: 0 auto;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 18px;
            color: var(--text-primary);
        }

        .brand-badge-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--primary);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .progress-bar-wrap {
            display: flex;
            gap: 4px;
            margin-bottom: 32px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .progress-step {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px 4px;
            border-radius: 8px;
        }

        .progress-step.done { background: #ECFDF5; }
        .progress-step.active { background: #EFF6FF; }

        .step-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12.5px;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            background: #FFFFFF;
        }

        .progress-step.done .step-dot { background: #10B981; border-color: #10B981; color: #FFFFFF; }
        .progress-step.active .step-dot { background: var(--primary); border-color: var(--primary); color: #FFFFFF; }

        .step-label { font-size: 11px; color: var(--text-muted); font-weight: 600; text-align: center; }
        .progress-step.active .step-label { color: var(--primary); }
        .progress-step.done .step-label { color: #047857; }

        .setup-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 36px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .setup-title { font-weight: 800; font-size: 22px; color: var(--text-primary); margin-bottom: 6px; }
        .setup-sub { font-size: 13.5px; color: var(--text-muted); margin-bottom: 24px; line-height: 1.5; }

        .form-label-custom { font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
        .input-custom {
            width: 100%;
            padding: 10px 12px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 13.5px;
            outline: none;
            transition: border-color 0.15s ease-in-out;
        }
        .input-custom:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        textarea.input-custom { resize: vertical; min-height: 80px; }

        .btn-next {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 22px;
            background: var(--primary);
            border: none;
            border-radius: 6px;
            color: #FFFFFF;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            transition: background 0.15s ease-in-out;
        }
        .btn-next:hover { background: var(--primary-hover); }

        .btn-skip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-muted);
            font-size: 13.5px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-skip:hover { background: #F8FAFC; color: var(--text-primary); }

        .color-row { display: flex; gap: 14px; flex-wrap: wrap; }
        .color-pick { display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .color-pick input[type=color] { width: 44px; height: 44px; border-radius: 8px; border: 1px solid var(--border-color); cursor: pointer; padding: 2px; }
        .color-pick span { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        .font-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .font-opt {
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
        }
        .font-opt input[type=radio] { accent-color: var(--primary); }

        .upload-zone {
            border: 2px dashed var(--border-color);
            background: #F8FAFC;
            border-radius: 8px;
            padding: 32px 20px;
            text-align: center;
            position: relative;
            cursor: pointer;
        }
        .upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .upload-zone i { font-size: 32px; color: var(--primary); margin-bottom: 8px; display: block; }
    </style>
</head>
<body>

<div class="setup-wrap">

    <!-- Header Logo -->
    <div class="brand-header">
        <div class="brand-badge">
            <div class="brand-badge-icon"><i class="bi bi-shop"></i></div>
            <span>TamizhMart Store Onboarding</span>
        </div>
    </div>

    <!-- Progress Steps -->
    <div class="progress-bar-wrap">
        <?php foreach ($steps_info as $s => $info): ?>
        <div class="progress-step <?= $s < $step ? 'done' : ($s === $step ? 'active' : '') ?>">
            <div class="step-dot">
                <?php if ($s < $step): ?>
                <i class="bi bi-check-lg"></i>
                <?php else: ?>
                <i class="bi bi-<?= $info['icon'] ?>"></i>
                <?php endif; ?>
            </div>
            <div class="step-label"><?= $info['title'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Step Card -->
    <div class="setup-card">

    <?php if ($step === 1): ?>
        <div class="setup-title">Step 1: Store Information</div>
        <p class="setup-sub">Enter basic shop details visible to your customers.</p>
        <form method="POST" style="display:grid;gap:14px;">
            <input type="hidden" name="action" value="step1">
            <div>
                <div class="form-label-custom">Shop Name *</div>
                <input type="text" name="name" class="input-custom" value="<?= htmlspecialchars($shop['name']) ?>" required>
            </div>
            <div>
                <div class="form-label-custom">Description</div>
                <textarea name="description" class="input-custom" placeholder="Describe your store and products..."><?= htmlspecialchars($shop['description'] ?? '') ?></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:10px;">
                <button type="submit" class="btn-next">Next Step &rarr;</button>
            </div>
        </form>

    <?php elseif ($step === 2): ?>
        <div class="setup-title">Step 2: Brand Logo</div>
        <p class="setup-sub">Upload your store logo to personalize header and customer receipts.</p>
        <form method="POST" enctype="multipart/form-data" style="display:grid;gap:18px;">
            <input type="hidden" name="action" value="step2">
            <div class="upload-zone">
                <input type="file" name="logo" accept="image/*" onchange="previewLogo(this)">
                <i class="bi bi-cloud-upload" id="uploadIcon"></i>
                <p id="uploadText" style="font-size:13px;color:var(--text-muted);">Click to upload or drag logo file here<br><span style="font-size:11.5px;">PNG, JPG or WEBP &middot; max 5MB</span></p>
                <img id="logoPreview" style="display:none;max-height:90px;border-radius:6px;margin:10px auto 0;">
            </div>
            <div style="display:flex;justify-content:space-between;">
                <a href="setup.php?step=3" class="btn-skip">Skip For Now</a>
                <button type="submit" class="btn-next">Next Step &rarr;</button>
            </div>
        </form>

    <?php elseif ($step === 3): ?>
        <div class="setup-title">Step 3: Theme Customization</div>
        <p class="setup-sub">Choose primary brand colors and typography for your storefront.</p>
        <form method="POST" style="display:grid;gap:18px;">
            <input type="hidden" name="action" value="step3">
            <div>
                <div class="form-label-custom" style="margin-bottom:10px;">Brand Color Palette</div>
                <div class="color-row">
                    <div class="color-pick">
                        <input type="color" name="primary" value="<?= $shop['theme_primary'] ?? '#2563eb' ?>">
                        <span>Primary</span>
                    </div>
                    <div class="color-pick">
                        <input type="color" name="secondary" value="<?= $shop['theme_secondary'] ?? '#1d4ed8' ?>">
                        <span>Secondary</span>
                    </div>
                    <div class="color-pick">
                        <input type="color" name="bg" value="<?= $shop['theme_bg'] ?? '#f8fafc' ?>">
                        <span>Background</span>
                    </div>
                </div>
            </div>
            <div>
                <div class="form-label-custom" style="margin-bottom:10px;">Font Family</div>
                <div class="font-grid">
                    <?php foreach (['Poppins'=>'Poppins','DM Sans'=>'DM Sans','Syne'=>'Syne','Nunito'=>'Nunito'] as $val => $label): ?>
                    <label class="font-opt">
                        <input type="radio" name="font" value="<?= $val ?>" <?= ($shop['theme_font'] ?? 'Poppins') === $val ? 'checked' : '' ?>>
                        <span style="font-family:'<?= $val ?>',sans-serif;font-size:13px;font-weight:600;"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;">
                <a href="setup.php?step=4" class="btn-skip">Skip</a>
                <button type="submit" class="btn-next">Next Step &rarr;</button>
            </div>
        </form>

    <?php elseif ($step === 4): ?>
        <div class="setup-title">Step 4: Initial Catalog</div>
        <p class="setup-sub">Add your first category and product to populate your catalog.</p>
        <form method="POST" style="display:grid;gap:14px;">
            <input type="hidden" name="action" value="step4">
            <div style="padding:14px;background:#F8FAFC;border:1px solid var(--border-color);border-radius:8px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);margin-bottom:8px;">First Category</div>
                <input type="text" name="category_name" class="input-custom" placeholder="e.g. Beverages, Bakery, Accessories...">
            </div>
            <div style="padding:14px;background:#F8FAFC;border:1px solid var(--border-color);border-radius:8px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);margin-bottom:8px;">First Product</div>
                <div style="display:grid;gap:10px;">
                    <input type="text" name="product_name" class="input-custom" placeholder="Product Title">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <input type="number" name="product_price" class="input-custom" placeholder="Price (₹)" step="0.01">
                        <input type="number" name="product_stock" class="input-custom" placeholder="Stock Qty" value="10">
                    </div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;">
                <a href="setup.php?step=5" class="btn-skip">Skip</a>
                <button type="submit" class="btn-next">Next Step &rarr;</button>
            </div>
        </form>

    <?php elseif ($step === 5): ?>
        <div style="text-align:center;padding:10px 0 20px;">
            <div style="font-size:48px;margin-bottom:10px;">🚀</div>
            <div class="setup-title">Store Ready to Launch!</div>
            <p class="setup-sub">Add optional contact info and announcement banner to complete setup.</p>
        </div>
        <form method="POST" style="display:grid;gap:14px;">
            <input type="hidden" name="action" value="complete">
            <div>
                <div class="form-label-custom">Announcement Banner (optional)</div>
                <input type="text" name="announcement" class="input-custom" placeholder="e.g. Free shipping on orders above ₹499!">
            </div>
            <div>
                <div class="form-label-custom">Support Phone</div>
                <input type="text" name="phone" class="input-custom" placeholder="+91 00000 00000">
            </div>
            <button type="submit" class="btn-next" style="width:100%;justify-content:center;padding:12px;font-size:14px;">
                <i class="bi bi-shop"></i> Launch Seller Console
            </button>
        </form>
    <?php endif; ?>

    </div>

    <div style="text-align:center;margin-top:16px;font-size:12px;color:var(--text-muted);">
        Step <?= $step ?> of 5
    </div>
</div>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('logoPreview').src = e.target.result;
            document.getElementById('logoPreview').style.display = 'block';
            document.getElementById('uploadIcon').style.display = 'none';
            document.getElementById('uploadText').style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>