# Owner Debugging Workspace

URL: `/owner/debugging.php`

The page and sidebar link require both `OWNER_DEBUG_TOOLS=1` and `APP_ENV=development` or `APP_ENV=test`. Unset flags, staging, and production disable the endpoint, including POST requests. Do not enable the development Docker configuration against a production database. Set `OWNER_DEBUG_TOOLS=0` before production deployment.

Every action verifies the session owner against the shop, validates a CSRF token, and runs in a database transaction. An operation history entry commits with each action. Repeated submissions with the same request key do not run twice.

Stock controls set exact quantities or add quantities for all products, one product, or one category. Sample customer accounts are inactive, use `.invalid` email addresses and random passwords. Sample orders are direct synthetic database records: they do not send messages, charge payments, or change inventory.

Full reset removes shop operational records, branding and settings. It keeps the owner account, shop ID/name/URL and audit histories. Subscription history is retained unless its checkbox is selected. Shop and owner suspension flags are never cleared. Deleting products or customers separately requires clearing orders first.

Uploaded-file removal is optional and runs after the database commit. It only removes direct filenames in known upload folders, skips symbolic links and external URLs, and retains files referenced elsewhere. File-system failures do not undo a completed database reset. External media and shared application logs are not deleted.

The SQL file `databasefile/add_owner_debug_logs.sql` is installed on first page use. The reset refuses unknown shop-owned tables so future schema additions cannot silently escape cleanup. Do not disable foreign-key checks or truncate shared tables for shop resets.

The page's CSS and JavaScript are embedded in `owner/debugging.php`; no owner assets folder is needed. To remove the tools entirely, remove this page, `owner/includes/debug_tools.php`, the sidebar's testing link and debug-access include, and the two environment variables. Retain historical debug logs as needed.

Run integration checks with `docker exec -w /var/www/html tamizhmart_php php tests/owner_debug_test.php`. The test copies table definitions (not shop records) into a randomly named temporary database, verifies isolation and reset behavior with synthetic data, then drops that database. The database account needs permission to create and drop test databases. Add `--render-fixture` to generate `/tmp/tamizhmart-debug-preview.html` inside the PHP container for read-only UI review.
