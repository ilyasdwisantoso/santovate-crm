# Isolated database tests

The default PHPUnit configuration forces testing, SQLite :memory:, array cache/session, sync queues and null logging.
Tests\\TestCase installs IsolatedDatabaseGuard after configuration loads and before providers or RefreshDatabase can execute.
It rejects cached config, removes development connections, and validates dynamic connections before PDO/SQL.

Run: php vendor/bin/phpunit
JavaScript: node --test tests/js/*.test.mjs

CrmTestCase runs the migration chain in SQLite memory with two historical subscription-date migrations adapted for SQLite only.
The historical migration files remain unchanged. Native identity and regression tests run on this fixture.

## Real MySQL/MariaDB

phpunit.mysql.xml selects only tests/Integration/MySql and the mysql_testing connection. It never falls back to .env development credentials.
Explicit TEST_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD are required. Loopback only; schema must match santovate_crm_test_[a-z0-9_]+ and account santovate_test[_suffix].
The guard checks configuration/names, not server grants. Before DDL, separately prove the account has privileges only on its disposable schema and cannot access development.
The integration suite expects an empty disposable schema and runs the real migration chain. Never use an existing app schema.

The local runner tests/Support/run-isolated-mysql.php --provision-local additionally:
- Requires a separate MariaDB instance on 127.0.0.1:3317 with a fresh datadir named TEMP/santovate-mariadb-v952_<12 hex>.
- Uses root with empty password only on that manually initialized disposable instance, not on the development instance for DDL.
- Creates a random schema/account, generates a password in process memory and passes credentials only in child environment.
- Grants only the escaped exact schema, checks grants and rejects global privileges/GRANT OPTION.
- Checks the test account cannot select crm_santovate.prospects.
- Marks ownership and drops only its generated schema/account in finally.
- Uses local development .env credentials solely for read-only transactions and before/after fingerprints of Prospect rows/schema and migration history on port 3307.

The runner does not initialize, restart, repair or shut down an existing MariaDB server.
Its local root-without-password setup is restricted to the disposable TEMP instance; it is not a production setup procedure.
Creating or removing its test resources requires the operator's authorization. Server process shutdown and TEMP datadir cleanup are separate explicit steps.

The first provisioning attempt on the active local server failed with error 1034 (mysql.db index corruption).
Its temporary account/schema were removed without repairing system tables. Subsequent suites used the independent TEMP instance successfully.
No test command authorizes migrations on an active development or production database.

For another isolated MySQL/MariaDB instance, provision a least-privileged schema/account separately and invoke:
php vendor/bin/phpunit -c phpunit.mysql.xml
Supply TEST_DB_* in the process environment; never put passwords on command arguments or commit them.
Oracle MySQL compatibility still needs its own server run; a MariaDB pass is not a MySQL-server pass.
