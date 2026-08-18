<?php
session_start();
require '../config/db.php';

$page_title    = 'Bulk Upload Products';
$page_subtitle = 'Import multiple products at once via CSV file';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== 0 || $file['size'] === 0) {
        $error = 'Please upload a valid CSV file.';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'Only CSV files are allowed.';
    } else {
        $handle  = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle); // skip header row
        $added = $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) { $skipped++; continue; }

            $name        = trim($row[0] ?? '');
            $price       = (float)($row[1] ?? 0);
            $stock       = (int)($row[2] ?? 0);
            $description = trim($row[3] ?? '');
            $category    = trim($row[4] ?? '');
            $disc_price  = isset($row[5]) && $row[5] !== '' ? (float)$row[5] : null;
            $image_url   = trim($row[6] ?? '');

            if (!$name || $price <= 0) { $skipped++; continue; }

            // Find or skip category
            $cat_id = null;
            if ($category) {
                $cr = $conn->query("SELECT id FROM categories WHERE shop_id=$shop_id AND name='" . $conn->real_escape_string($category) . "' LIMIT 1");
                if ($cr->num_rows > 0) $cat_id = $cr->fetch_assoc()['id'];
            }

            $is_url    = !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL);
            $img_file  = null;
            $img_url   = $is_url ? $image_url : null;

            $stmt = $conn->prepare("INSERT INTO products (shop_id, category_id, name, description, price, discount_price, image, image_url, stock, is_active) VALUES (?,?,?,?,?,?,?,?,?,1)");
            $stmt->bind_param("iissddssi", $shop_id, $cat_id, $name, $description, $price, $disc_price, $img_file, $img_url, $stock);
            if ($stmt->execute()) {
                $added++;
                $results[] = ['status'=>'ok','name'=>$name,'msg'=>'Added successfully'];
            } else {
                $skipped++;
                $results[] = ['status'=>'err','name'=>$name,'msg'=>'Failed to insert'];
            }
        }
        fclose($handle);
        $success = "$added product(s) imported successfully." . ($skipped ? " $skipped row(s) skipped." : '');
    }
}

// Get categories for reference
$cats = $conn->query("SELECT name FROM categories WHERE shop_id=$shop_id AND is_active=1 ORDER BY name");
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Upload Form -->
    <div class="col-lg-6">
        <div class="card-glass animate-in">
            <div style="font-weight:700;font-size:16px;color:var(--text-primary);margin-bottom:4px;">
                <i class="bi bi-cloud-upload-fill" style="color:var(--primary);margin-right:6px;"></i>Upload Product CSV File
            </div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:20px;">Upload a structured CSV file to add products in bulk</div>

            <form method="POST" enctype="multipart/form-data">
                <div style="border:2px dashed #CBD5E1;background:#F8FAFC;border-radius:var(--radius);padding:28px 20px;text-align:center;margin-bottom:18px;transition:all 0.15s ease-in-out;"
                     id="dropZone"
                     ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='#EFF6FF';"
                     ondragleave="this.style.borderColor='#CBD5E1';this.style.background='#F8FAFC';"
                     ondrop="event.preventDefault();this.style.borderColor='#CBD5E1';this.style.background='#F8FAFC';document.getElementById('csvInput').files=event.dataTransfer.files;updateFileName(event.dataTransfer.files[0].name)">
                    <i class="bi bi-file-earmark-spreadsheet" style="font-size:36px;color:var(--primary);display:block;margin-bottom:10px;"></i>
                    <div style="font-weight:600;font-size:13.5px;color:var(--text-primary);margin-bottom:4px;">Drag & drop your CSV here</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">or browse from your device</div>
                    <input type="file" name="csv_file" id="csvInput" accept=".csv" style="display:none;"
                        onchange="updateFileName(this.files[0]?.name)">
                    <button type="button" onclick="document.getElementById('csvInput').click()" class="btn-ghost-custom" style="font-size:12.5px;">
                        <i class="bi bi-folder2-open"></i> Browse File
                    </button>
                    <div id="fileName" style="margin-top:10px;font-size:12.5px;color:var(--primary);font-weight:600;"></div>
                </div>
                <button type="submit" class="btn-orange-custom" style="width:100%;justify-content:center;padding:10px;">
                    <i class="bi bi-cloud-upload"></i> Upload & Import Catalog
                </button>
            </form>
        </div>

        <!-- Results -->
        <?php if (!empty($results)): ?>
        <div class="card-glass animate-in d1" style="margin-top:16px;max-height:280px;overflow-y:auto;">
            <div style="font-weight:700;font-size:14px;color:var(--text-primary);margin-bottom:12px;">Import Log Results</div>
            <?php foreach ($results as $r): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F1F5F9;font-size:12.5px;">
                <i class="bi bi-<?= $r['status']==='ok' ? 'check-circle-fill' : 'x-circle-fill' ?>"
                   style="color:<?= $r['status']==='ok' ? 'var(--success-text)' : 'var(--danger-text)' ?>;flex-shrink:0;"></i>
                <span style="flex:1;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($r['name']) ?></span>
                <span style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($r['msg']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Instructions & Template -->
    <div class="col-lg-6">
        <div class="card-glass animate-in d1" style="margin-bottom:16px;">
            <div style="font-weight:700;font-size:15px;color:var(--text-primary);margin-bottom:14px;"><i class="bi bi-info-circle" style="color:var(--primary);margin-right:6px;"></i>CSV Structure Requirements</div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">Your CSV must have these columns in this exact sequence:</div>
            <div style="background:#F8FAFC;border-radius:6px;overflow:hidden;border:1px solid #E2E8F0;">
                <?php
                $cols = [
                    ['name','Product Title','Required'],
                    ['price','Regular price in ₹','Required'],
                    ['stock','Stock quantity','Required'],
                    ['description','Detailed description','Optional'],
                    ['category','Existing category name','Optional'],
                    ['discount_price','Discounted price in ₹','Optional'],
                    ['image_url','Public image URL','Optional'],
                ];
                foreach ($cols as $i => $col):
                ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;<?= $i > 0 ? 'border-top:1px solid #E2E8F0' : '' ?>">
                    <div style="width:20px;height:20px;border-radius:4px;background:#EFF6FF;color:#2563EB;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #BFDBFE;"><?= $i+1 ?></div>
                    <div style="flex:1;">
                        <span style="font-weight:700;font-size:12.5px;color:var(--text-primary);"><?= $col[0] ?></span>
                        <span style="font-size:12px;color:var(--text-muted);margin-left:4px;">&mdash; <?= $col[1] ?></span>
                    </div>
                    <span style="font-size:10.5px;font-weight:700;padding:2px 6px;border-radius:4px;<?= $col[2]==='Required' ? 'background:var(--danger-bg);color:var(--danger-text);border:1px solid var(--danger-border);' : 'background:#F1F5F9;color:var(--text-muted);border:1px solid #E2E8F0;' ?>"><?= $col[2] ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Download template -->
            <a href="data:text/csv;charset=utf-8,name,price,stock,description,category,discount_price,image_url%0AExample Product,299,50,This is a sample product description,<?= urlencode($cats->num_rows > 0 ? $cats->fetch_assoc()['name'] : 'CategoryName') ?>,,https://example.com/image.jpg"
               download="tamizhmart_product_template.csv"
               class="btn-ghost-custom" style="margin-top:14px;width:100%;justify-content:center;font-size:12.5px;">
                <i class="bi bi-download"></i> Download CSV Template File
            </a>
        </div>

        <!-- Your categories -->
        <div class="card-glass animate-in d2">
            <div style="font-weight:700;font-size:14px;color:var(--text-primary);margin-bottom:8px;"><i class="bi bi-tags-fill" style="color:var(--primary);margin-right:6px;"></i>Available Categories</div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Use these exact names in your CSV file's `category` column:</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php
                $cats->data_seek(0);
                while ($cat = $cats->fetch_assoc()):
                ?>
                <span style="background:#F1F5F9;border:1px solid #E2E8F0;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600;color:var(--text-secondary);">
                    <?= htmlspecialchars($cat['name']) ?>
                </span>
                <?php endwhile; ?>
                <?php if ($cats->num_rows === 0): ?>
                <span style="color:var(--text-muted);font-size:12.5px;">No categories yet. <a href="categories.php" style="color:var(--primary);font-weight:600;">Add one first</a></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>

<script>
function updateFileName(name) {
    if (name) document.getElementById('fileName').textContent = 'Selected: ' + name;
}
</script>