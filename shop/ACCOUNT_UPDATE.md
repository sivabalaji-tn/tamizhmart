# Customer Account Update

The profile is now a shop-specific My Account area. Its CSS and JavaScript are embedded in `shop/profile.php`.

## Deployment

Back up the database and deploy these files together, preserving their paths:

- `shop/profile.php`, `shop/account_photo.php`
- `shop/includes/customer_account.php`, `shop/includes/shop_head.php`
- `shop/product.php`, `shop/checkout.php`, `shop/reciept.php`
- `shop/cart.php`, `shop/cart_action.php`, `shop/coupon_check.php`
- `shop/index.php`, `shop/orders.php`
- `shop/razorpay_create_order.php`, `shop/razorpay_verify.php`
- `auth/login.php`, `auth/register.php`, `auth/google_callback.php`, `auth/reset_password.php`
- `owner/customer_support.php`, `owner/campaigns.php`, `owner/includes/sidebar.php`
- `owner/includes/debug_tools.php` if debugging is installed
- `superadmin/shops.php`, `superadmin/owners.php` (cleanup on permanent deletion)

Do not replace the production database configuration with local credentials. No debugging access changes are required for the customer account features.

Use PHP 8.2+ with mysqli/mysqlnd, mbstring, fileinfo and GD (JPEG, PNG and WebP support). The updated Docker PHP image includes GD; rebuild with `docker compose up -d --build php` locally. On Plesk, ensure GD is enabled for the site's PHP runtime.

The helper creates five additive InnoDB tables automatically: `customer_accounts`, `customer_addresses`, `customer_wishlist`, `customer_support`, and `customer_support_messages`. No separate SQL file is required. The deployment database account needs CREATE permission for initial installation. Existing tables and customer records are retained. Back up these new tables along with the rest of the database.

Email verification uses the existing PHPMailer and SMTP configuration in `shop/includes/notifications.php`. Ensure the PHPMailer files and working SMTP settings are present on the host. No real verification emails are sent by the automated tests.

## Behavior

- Addresses are limited to ten per customer. The selected default synchronizes to the existing delivery-address field, and checkout offers a saved-address picker.
- Product pages have a wishlist button. Saved products can be added to the cart; unavailable products remain removable. Buy Again uses current prices and caps quantities at available stock; it never places an order or charges a payment.
- Marketing is opt-in. Customers enable shop campaigns under Email preferences. Every campaign audience and the final send loop honor this setting; transactional order and security emails are unaffected. Customers without a preference record are not automatically subscribed.
- Profile photos accept JPG, PNG or WebP under 5 MB. The browser normally normalizes orientation and compresses the center crop before upload. The server independently validates and re-encodes a 256 by 256 JPEG, stored in the database and served only to the authenticated customer. No executable uploads or public photo directory is used. Without browser compression, PHP upload/post limits must allow the original file; server-side dimensions must be between 64 and 4096 pixels per side.
- Password changes, password recovery, verified email changes and Sign out other sessions increment a server-checked session version. The current session remains active for changes made in My Account. Legacy sessions are accepted only while the version remains zero. Customer entrypoints must be deployed together to enforce revocation everywhere.
- Email changes require the current password and a six-digit code sent to the new address. Codes expire after ten minutes, allow five incorrect attempts, and have a sixty-second resend cooldown. Changing email disconnects Google sign-in; a Google-only customer must first set a password using the existing email-recovery workflow.
- Receipts use stored order amounts and prices and the existing printable receipt page. Print / Save as PDF uses the browser's print dialog. These are not newly generated tax invoices. Unpaid orders are clearly labeled as order summaries.
- Support and returns have an owner inbox under Customer Support, threaded replies, and status updates. Return requests require a delivered order. Approval does not automatically issue a refund or reverse inventory; the shop handles those operations separately.
- Full debugging resets now include the new account records and photos. Selective order cleanup detaches support order links; customer cleanup removes their support conversations.

## Verification

`docker exec -w /var/www/html tamizhmart_php php tests/customer_account_test.php --render-fixture`

The test creates an isolated database from table definitions, uses synthetic users and a fake verification mailer, checks authorization and account workflows, and drops the temporary database. The optional flag renders the real pages into `/tmp/tm-account-preview` for browser layout tests. Existing shop data is not modified.

For local HTTP integration tests only, `--http-fixture` instead preserves a synthetic database and prints its name and login details. `tests/customer_account_router.php` accepts only PHP's development server with `APP_ENV=test` and a matching `CUSTOMER_ACCOUNT_TEST_DB`. Bind that server to localhost, stop it after testing, and drop only the exact temporary database printed by the fixture command. Do not deploy the test files to production.
