# Superadmin Audit Integration Tests

Run against the local Docker environment:

```powershell
docker exec -w /var/www/html tamizhmart_php php tests/superadmin_audit_test.php
```

The suite creates a randomly named `tamizhmart_audit_test_*` database, copies table structures (not customer data), seeds synthetic records, and exercises the actual superadmin PHP handlers. It drops only that temporary database when finished. The database account needs permission to create databases and test triggers.

Coverage includes all audited action families, before/after values, deleted-record references, authentication, CSRF rejection, password redaction, no-op and rejected actions, rollback on audit insertion failure, search, shop filters, pagination, HTML escaping, and IST date boundaries. Table structures are copied with `CREATE TABLE LIKE`; source foreign-key constraints are not copied.

Add `--render-fixtures` to generate synthetic HTML in the container's `/tmp/tamizhmart-audit-preview` for browser layout checks.

For another installation, apply `databasefile/add_superadmin_audit_logs.sql` first and `databasefile/add_superadmin_audit_shop_links.sql` second. The application also creates these tables on first audit use. Existing activity is not backfilled. The audit screens have no edit or delete controls; database administrators still control the underlying tables.
