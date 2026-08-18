<?php // shop/includes/shop_foot.php // ── This script is made by Siva Balaji sms ────────────────────── ?>
</main>

<!-- Footer -->
<footer class="shop-footer">
    <div class="footer-inner">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="footer-brand-name"><?= htmlspecialchars($shop['name']) ?></div>
                <?php if ($shop['description']): ?>
                <p class="footer-desc"><?= htmlspecialchars($shop['description']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-2 col-6">
                <div style="font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;color:var(--text-muted);">Shop</div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="index.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Home</a>
                    <a href="products.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">All Products</a>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div style="font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;color:var(--text-muted);">Account</div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="orders.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">My Orders</a>
                    <a href="profile.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Profile</a>
                    <?php else: ?>
                    <a href="../auth/login.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Sign In</a>
                    <a href="../auth/register.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Register</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $phone   = $settings_map['phone']   ?? null;
            $address = $settings_map['address'] ?? null;
            if ($phone || $address):
            ?>
            <div class="col-md-4">
                <div style="font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;color:var(--text-muted);">Contact</div>
                <?php if ($phone): ?>
                <div style="font-size:14px;color:var(--text-muted);display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <i class="bi bi-telephone" style="color:var(--primary);font-size:14px;"></i>
                    <?= htmlspecialchars($phone) ?>
                </div>
                <?php endif; ?>
                <?php if ($address): ?>
                <div style="font-size:14px;color:var(--text-muted);display:flex;align-items:flex-start;gap:8px;line-height:1.5;">
                    <i class="bi bi-geo-alt" style="color:var(--primary);font-size:14px;margin-top:1px;flex-shrink:0;"></i>
                    <?= htmlspecialchars($address) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <hr class="footer-divider">
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($shop['name']) ?>. All rights reserved.</span>
            <span class="footer-powered">Powered by <strong>SM Tech</strong></span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Navbar scroll effect
window.addEventListener('scroll', () => {
    document.getElementById('shopNavbar').classList.toggle('scrolled', window.scrollY > 10);
});

// Mobile menu
function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
    document.body.style.overflow = document.getElementById('mobileMenu').classList.contains('open') ? 'hidden' : '';
}

// Popup
function closePopup() {
    const p = document.getElementById('shopPopup');
    if (p) { p.style.opacity='0'; p.style.transition='opacity 0.3s'; setTimeout(()=>p.remove(),300); }
    sessionStorage.setItem('popup_dismissed_<?= $shop['id'] ?>', '1');
}
// Don't show popup twice in same session
if (sessionStorage.getItem('popup_dismissed_<?= $shop['id'] ?>')) {
    const p = document.getElementById('shopPopup');
    if (p) p.remove();
}

// Toast helper
function showToast(msg, icon='check-circle-fill') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = 'toast-item';
    t.innerHTML = `<i class="bi bi-${icon}"></i>${msg}`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3100);
}

// Add to cart via AJAX
function addToCart(productId, qty=1) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=add&product_id=${productId}&quantity=${qty}&shop=<?= $slug ?>`
    })
    .then(r=>r.json())
    .then(d=>{
        if(d.success) {
            showToast(d.message || 'Added to cart!');
            // Update cart count
            document.querySelectorAll('.cart-badge,.mobile-cart-badge').forEach(el => {
                el.textContent = d.cart_count;
                el.style.display = d.cart_count > 0 ? 'flex' : 'none';
            });
        } else {
            showToast(d.message || 'Failed to add', 'exclamation-circle-fill');
        }
    });
}

// Popup close on overlay click
document.getElementById('shopPopup')?.addEventListener('click', function(e) {
    if (e.target === this) closePopup();
});
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>

<?php if (!empty($_SESSION['show_profile_tour'])): ?>
<?php unset($_SESSION['show_profile_tour']); ?>
<!-- ── Profile Completion Tour (shown once after Google signup) ── -->
<div id="profileTourOverlay" style="
    position:fixed;inset:0;z-index:99999;
    background:rgba(0,0,0,0.65);
    backdrop-filter:blur(6px);
    display:flex;align-items:center;justify-content:center;padding:20px;
    animation:fadeInOverlay 0.4s ease;">
<style>
@keyframes fadeInOverlay{from{opacity:0}to{opacity:1}}
@keyframes slideUpTour{from{opacity:0;transform:translateY(32px)}to{opacity:1;transform:translateY(0)}}
#profileTourCard{
    background:#fff;border-radius:20px;padding:36px 32px;max-width:420px;width:100%;
    position:relative;box-shadow:0 24px 60px rgba(0,0,0,0.3);
    animation:slideUpTour 0.45s cubic-bezier(.22,.68,0,1.2) forwards;
    font-family:'Inter','DM Sans',sans-serif;
}
.ptour-icon{width:64px;height:64px;border-radius:16px;
    background:linear-gradient(135deg,#f97316,#fb923c);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 20px;font-size:28px;color:#fff;box-shadow:0 8px 24px rgba(249,115,22,0.35);}
.ptour-title{font-size:19px;font-weight:700;color:#1e293b;text-align:center;margin-bottom:8px;letter-spacing:-0.4px;}
.ptour-sub{font-size:13.5px;color:#64748b;text-align:center;line-height:1.6;margin-bottom:24px;}
.ptour-steps{display:flex;flex-direction:column;gap:10px;margin-bottom:24px;}
.ptour-step{display:flex;align-items:center;gap:12px;background:#f8fafc;
    border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;}
.ptour-step-icon{width:34px;height:34px;border-radius:9px;
    background:linear-gradient(135deg,#2563eb,#3b82f6);
    display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;flex-shrink:0;}
.ptour-step-text strong{display:block;font-size:13px;font-weight:600;color:#1e293b;}
.ptour-step-text span{font-size:12px;color:#94a3b8;}
.ptour-btn-go{width:100%;padding:13px;background:linear-gradient(135deg,#f97316,#ea580c);
    border:none;border-radius:12px;color:#fff;font-size:14.5px;font-weight:700;
    cursor:pointer;transition:transform 0.15s,box-shadow 0.15s;
    box-shadow:0 4px 16px rgba(249,115,22,0.35);margin-bottom:10px;}
.ptour-btn-go:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(249,115,22,0.45);}
.ptour-btn-skip{width:100%;padding:10px;background:transparent;border:none;
    color:#94a3b8;font-size:13px;cursor:pointer;transition:color 0.2s;}
.ptour-btn-skip:hover{color:#64748b;}
</style>
<div id="profileTourCard">
    <button onclick="dismissTour()" style="position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:#cbd5e1;font-size:20px;line-height:1;" title="Close">&#10005;</button>
    <div class="ptour-icon">&#127881;</div>
    <div class="ptour-title">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'there') ?>!</div>
    <div class="ptour-sub">Your account is ready. Complete your profile so we can deliver orders to the right place.</div>
    <div class="ptour-steps">
        <div class="ptour-step">
            <div class="ptour-step-icon"><i class="bi bi-telephone-fill"></i></div>
            <div class="ptour-step-text">
                <strong>Phone Number</strong>
                <span>For order updates &amp; delivery confirmation</span>
            </div>
        </div>
        <div class="ptour-step">
            <div class="ptour-step-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div class="ptour-step-text">
                <strong>Delivery Address</strong>
                <span>So we know exactly where to deliver</span>
            </div>
        </div>
    </div>
    <button class="ptour-btn-go" onclick="goToProfile()">
        <i class="bi bi-person-fill me-2"></i>Complete My Profile
    </button>
    <button class="ptour-btn-skip" onclick="dismissTour()">I'll do this later</button>
</div>
</div>
<script>
function dismissTour() {
    const el = document.getElementById('profileTourOverlay');
    if (el) { el.style.opacity='0'; el.style.transition='opacity 0.3s'; setTimeout(()=>el.remove(),300); }
}
function goToProfile() {
    dismissTour();
    window.location.href = 'profile.php?shop=<?= htmlspecialchars($slug ?? '') ?>';
}
</script>
<?php endif; ?>

</body>
</html>
