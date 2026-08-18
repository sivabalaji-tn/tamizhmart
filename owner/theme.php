<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
$page_title    = 'Theme & Colors';
$page_subtitle = 'Customise your storefront\'s visual design in real time';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_theme') {
    $primary   = $_POST['theme_primary'];
    $secondary = $_POST['theme_secondary'];
    $bg        = $_POST['theme_bg'];
    $text_col  = $_POST['theme_text'];
    $font      = $_POST['theme_font'];

    $hex_pattern = '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
    if (preg_match($hex_pattern, $primary) && preg_match($hex_pattern, $secondary) && preg_match($hex_pattern, $bg)) {
        $stmt = $conn->prepare("UPDATE shops SET theme_primary=?, theme_secondary=?, theme_bg=?, theme_text=?, theme_font=? WHERE id=? AND owner_id=?");
        $stmt->bind_param("sssssii", $primary, $secondary, $bg, $text_col, $font, $shop_id, $_SESSION['owner_id']);
        $stmt->execute();
        $success = "Theme saved! Your storefront now reflects the new design.";
        $s2 = $conn->prepare("SELECT * FROM shops WHERE id=?");
        $s2->bind_param("i", $shop_id);
        $s2->execute();
        $shop = $s2->get_result()->fetch_assoc();
    }
}

$fonts = [
    'Poppins'   => 'Poppins — Modern & Clean',
    'DM Sans'   => 'DM Sans — Friendly & Readable',
    'Syne'      => 'Syne — Bold & Editorial',
    'Lato'      => 'Lato — Professional & Neutral',
    'Playfair Display' => 'Playfair Display — Elegant & Luxury',
    'Nunito'    => 'Nunito — Soft & Rounded',
    'Raleway'   => 'Raleway — Stylish & Minimal',
    'Josefin Sans' => 'Josefin Sans — Geometric & Trendy',
];

$presets = [
    ['name'=>'Midnight Navy', 'primary'=>'#2563eb', 'secondary'=>'#1d4ed8', 'bg'=>'#f8fafc', 'text'=>'#0f172a'],
    ['name'=>'Forest Emerald','primary'=>'#059669', 'secondary'=>'#047857', 'bg'=>'#f0fdf4', 'text'=>'#064e3b'],
    ['name'=>'Royal Purple',  'primary'=>'#7c3aed', 'secondary'=>'#6d28d9', 'bg'=>'#faf5ff', 'text'=>'#3b0764'],
    ['name'=>'Warm Amber',    'primary'=>'#d97706', 'secondary'=>'#b45309', 'bg'=>'#fffbeb', 'text'=>'#451a03'],
    ['name'=>'Crimson Red',   'primary'=>'#dc2626', 'secondary'=>'#b91c1c', 'bg'=>'#fef2f2', 'text'=>'#450a0a'],
    ['name'=>'Sunset Orange', 'primary'=>'#ea580c', 'secondary'=>'#c2410c', 'bg'=>'#fff7ed', 'text'=>'#431407'],
    ['name'=>'Rose Pink',     'primary'=>'#db2777', 'secondary'=>'#be185d', 'bg'=>'#fdf2f8', 'text'=>'#500724'],
    ['name'=>'Classic Slate', 'primary'=>'#475569', 'secondary'=>'#334155', 'bg'=>'#f8fafc', 'text'=>'#0f172a'],
];
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" id="themeForm">
    <input type="hidden" name="action" value="save_theme">
    <input type="hidden" name="theme_primary"   id="inp_primary"   value="<?= htmlspecialchars($shop['theme_primary'] ?? '#2563eb') ?>">
    <input type="hidden" name="theme_secondary" id="inp_secondary" value="<?= htmlspecialchars($shop['theme_secondary'] ?? '#1d4ed8') ?>">
    <input type="hidden" name="theme_bg"        id="inp_bg"        value="<?= htmlspecialchars($shop['theme_bg'] ?? '#f8fafc') ?>">
    <input type="hidden" name="theme_text"      id="inp_text"      value="<?= htmlspecialchars($shop['theme_text'] ?? '#0f172a') ?>">
    <input type="hidden" name="theme_font"      id="inp_font"      value="<?= htmlspecialchars($shop['theme_font'] ?? 'Poppins') ?>">

    <div class="row g-3">

        <!-- Controls -->
        <div class="col-lg-5">

            <!-- Color Presets -->
            <div class="card-glass animate-in mb-3">
                <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-palette-fill" style="color:var(--primary);margin-right:6px;"></i>Color Presets</div>
                <div class="section-sub" style="margin-bottom:16px;">Select a curated color scheme for your store</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach ($presets as $preset): ?>
                    <button type="button" class="preset-btn" onclick="applyPreset('<?= $preset['primary'] ?>','<?= $preset['secondary'] ?>','<?= $preset['bg'] ?>','<?= $preset['text'] ?>')"
                        style="padding:10px 12px;border-radius:6px;border:1px solid #CBD5E1;background:#FFFFFF;cursor:pointer;transition:all 0.15s ease-in-out;display:flex;align-items:center;gap:8px;text-align:left;">
                        <div style="display:flex;gap:3px;flex-shrink:0;">
                            <div style="width:12px;height:12px;border-radius:50%;background:<?= $preset['primary'] ?>;"></div>
                            <div style="width:12px;height:12px;border-radius:50%;background:<?= $preset['secondary'] ?>;"></div>
                            <div style="width:12px;height:12px;border-radius:50%;background:<?= $preset['bg'] ?>;border:1px solid #CBD5E1;"></div>
                        </div>
                        <span style="font-size:12px;color:var(--text-primary);font-weight:600;"><?= $preset['name'] ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Custom Colors -->
            <div class="card-glass animate-in d1 mb-3">
                <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-sliders" style="color:var(--primary);margin-right:6px;"></i>Custom Color Fine-Tuning</div>
                <div class="section-sub" style="margin-bottom:16px;">Customize each brand color individually</div>

                <div style="display:grid;gap:12px;">
                    <?php
                    $color_fields = [
                        ['id'=>'pick_primary',   'inp'=>'inp_primary',   'label'=>'Primary Brand Color', 'hint'=>'Buttons, badges, highlights'],
                        ['id'=>'pick_secondary', 'inp'=>'inp_secondary', 'label'=>'Secondary Accent',     'hint'=>'Hover states & secondary elements'],
                        ['id'=>'pick_bg',        'inp'=>'inp_bg',        'label'=>'Store Background',    'hint'=>'Main page background canvas'],
                        ['id'=>'pick_text',      'inp'=>'inp_text',      'label'=>'Primary Text Color',  'hint'=>'Headings and body text'],
                    ];
                    foreach ($color_fields as $cf):
                    ?>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <input type="color" id="<?= $cf['id'] ?>"
                            value="<?= htmlspecialchars($shop[$cf['inp'] === 'inp_primary' ? 'theme_primary' : ($cf['inp'] === 'inp_secondary' ? 'theme_secondary' : ($cf['inp'] === 'inp_bg' ? 'theme_bg' : 'theme_text'))] ?? '#2563eb') ?>"
                            oninput="syncColor(this, '<?= $cf['inp'] ?>')"
                            style="width:38px;height:38px;border-radius:6px;border:1px solid #CBD5E1;cursor:pointer;padding:2px;background:none;">
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--text-primary);"><?= $cf['label'] ?></div>
                            <div style="font-size:11.5px;color:var(--text-muted);"><?= $cf['hint'] ?></div>
                        </div>
                        <div style="margin-left:auto;font-family:monospace;font-size:12px;color:var(--text-muted);" id="<?= $cf['id'] ?>_hex">
                            <?= htmlspecialchars($shop[$cf['inp'] === 'inp_primary' ? 'theme_primary' : ($cf['inp'] === 'inp_secondary' ? 'theme_secondary' : ($cf['inp'] === 'inp_bg' ? 'theme_bg' : 'theme_text'))] ?? '#2563eb') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Font Selection -->
            <div class="card-glass animate-in d2">
                <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-type" style="color:var(--primary);margin-right:6px;"></i>Font Typography</div>
                <div class="section-sub" style="margin-bottom:14px;">Select font family for your storefront</div>
                <div style="display:grid;gap:6px;">
                    <?php foreach ($fonts as $font_val => $font_label): ?>
                    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:6px;border:1px solid #CBD5E1;cursor:pointer;transition:all 0.15s ease-in-out;background:#FFFFFF;"
                        class="font-option" data-font="<?= $font_val ?>">
                        <input type="radio" name="_font_display" value="<?= $font_val ?>"
                            <?= ($shop['theme_font'] ?? 'Poppins') === $font_val ? 'checked' : '' ?>
                            onchange="selectFont('<?= $font_val ?>')"
                            style="accent-color:var(--primary);">
                        <div>
                            <div style="font-family:'<?= $font_val ?>',sans-serif;font-size:13.5px;font-weight:600;color:var(--text-primary);"><?= $font_val ?></div>
                            <div style="font-size:11px;color:var(--text-muted);"><?= explode(' — ', $font_label)[1] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Live Preview -->
        <div class="col-lg-7">
            <div class="card-glass animate-in d1" style="position:sticky;top:88px;">
                <div class="section-title" style="margin-bottom:2px;"><i class="bi bi-eye-fill" style="color:var(--primary);margin-right:6px;"></i>Live Storefront Preview</div>
                <div class="section-sub" style="margin-bottom:16px;">Real-time interactive preview of your shop theme</div>

                <!-- Preview Frame -->
                <div id="previewFrame" style="border-radius:8px;overflow:hidden;border:1px solid #CBD5E1;box-shadow:var(--shadow-sm);">

                    <!-- Preview Navbar -->
                    <div id="prev_navbar" style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(0,0,0,0.08);">
                        <div id="prev_brand" style="font-weight:800;font-size:15px;"><?= htmlspecialchars($shop['name']) ?></div>
                        <div style="display:flex;gap:6px;">
                            <div id="prev_btn" style="padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;color:#fff;">Shop Now</div>
                        </div>
                    </div>

                    <!-- Preview Hero -->
                    <div id="prev_hero" style="padding:30px 16px;text-align:center;border-bottom:1px solid rgba(0,0,0,0.06);">
                        <div id="prev_hero_title" style="font-size:20px;font-weight:800;margin-bottom:6px;"><?= htmlspecialchars($shop['name']) ?></div>
                        <div id="prev_hero_sub" style="font-size:13px;margin-bottom:16px;">Quality products delivered to your door</div>
                        <div id="prev_hero_btn" style="display:inline-block;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:600;color:#fff;">Browse Catalog</div>
                    </div>

                    <!-- Preview Products -->
                    <div id="prev_products" style="padding:16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="prev-card" style="border-radius:6px;overflow:hidden;border:1px solid rgba(0,0,0,0.08);">
                            <div style="height:60px;background:rgba(0,0,0,0.06);"></div>
                            <div style="padding:8px;">
                                <div style="height:9px;border-radius:3px;background:rgba(0,0,0,0.1);margin-bottom:5px;width:80%;"></div>
                                <div style="height:7px;border-radius:3px;background:rgba(0,0,0,0.06);width:50%;"></div>
                                <div class="prev-card-btn" style="margin-top:8px;height:24px;border-radius:4px;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;">Add to Cart</div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Save Button -->
                <div style="margin-top:18px;display:flex;gap:10px;">
                    <button type="submit" class="btn-orange-custom" style="flex:1;justify-content:center;padding:11px;">
                        <i class="bi bi-check-circle-fill"></i> Save Theme to Live Store
                    </button>
                    <a href="../shop/index.php?shop=<?= $shop['slug'] ?>" target="_blank" class="btn-ghost-custom" style="padding:11px 16px;">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$extra_scripts = '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=DM+Sans:wght@400;500;600&family=Syne:wght@700;800&family=Lato:wght@400;700&family=Playfair+Display:wght@400;700&family=Nunito:wght@400;600;700&family=Raleway:wght@400;600;700&family=Josefin+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<script>
let previewColors = {
    primary:   document.getElementById("inp_primary").value,
    secondary: document.getElementById("inp_secondary").value,
    bg:        document.getElementById("inp_bg").value,
    text:      document.getElementById("inp_text").value,
    font:      document.getElementById("inp_font").value
};

function updatePreview() {
    const p = previewColors;
    const frame = document.getElementById("previewFrame");
    frame.style.background  = p.bg;
    frame.style.color       = p.text;
    frame.style.fontFamily  = `"${p.font}", sans-serif`;

    document.getElementById("prev_navbar").style.background = p.bg;
    document.getElementById("prev_brand").style.color = p.text;
    document.getElementById("prev_btn").style.background = p.primary;

    document.getElementById("prev_hero").style.background = p.bg;
    document.getElementById("prev_hero_title").style.color = p.text;
    document.getElementById("prev_hero_sub").style.color = p.text + "99";
    document.getElementById("prev_hero_btn").style.background = p.primary;

    document.getElementById("prev_products").style.background = p.bg;
    document.querySelectorAll(".prev-card").forEach(c => { c.style.background = p.bg; });
    document.querySelectorAll(".prev-card-btn").forEach(b => { b.style.background = p.primary; });
}

function syncColor(picker, inputId) {
    const val = picker.value;
    document.getElementById(inputId).value = val;
    document.getElementById(picker.id + "_hex").textContent = val;

    if (inputId === "inp_primary")   previewColors.primary   = val;
    if (inputId === "inp_secondary") previewColors.secondary = val;
    if (inputId === "inp_bg")        previewColors.bg        = val;
    if (inputId === "inp_text")      previewColors.text      = val;
    updatePreview();
}

function applyPreset(p, s, bg, t) {
    document.getElementById("inp_primary").value   = p;
    document.getElementById("inp_secondary").value = s;
    document.getElementById("inp_bg").value        = bg;
    document.getElementById("inp_text").value      = t;
    document.getElementById("pick_primary").value   = p;
    document.getElementById("pick_secondary").value = s;
    document.getElementById("pick_bg").value        = bg;
    document.getElementById("pick_text").value      = t;
    ["pick_primary","pick_secondary","pick_bg","pick_text"].forEach(id => {
        const inp = document.getElementById(id);
        document.getElementById(id+"_hex").textContent = inp.value;
    });
    previewColors = { primary:p, secondary:s, bg:bg, text:t, font:previewColors.font };
    updatePreview();
}

function selectFont(font) {
    document.getElementById("inp_font").value = font;
    previewColors.font = font;
    updatePreview();
}

updatePreview();
</script>';

require 'includes/footer.php';
?>
