# Santovate CRM V9.3.9 - Payment status accuracy & sandbox E2E audit

Baseline: GitHub master `5540f0e8433fed4d887db7a6b0797de0d3219895`.

## Scope
- Subscription payment `active` is true only when **this payment** is paid and its subscription is active.
- Add-on payment `active` is true only when **this payment** is paid and its order activated.
- SSE `pending_verification` is terminal; polling also stops on failed/expired/cancelled/pending_verification.
- JS and PHP regression tests cover completion state; read-only sandbox audit counts stored statuses without exposing references or provider credentials.

No new migrations and no change to callback signature verification. Actual iPaymu sandbox payment success cannot be simulated here. It must be checked on a reachable HTTPS test environment with a real signed callback, verifying pending -> paid and single entitlement activation; do NOT assume these tests prove gateway connectivity.

## Windows: Step 1 (source only)
Run `RUN_APPLY_TEST_PUSH_V939.ps1 -ProjectPath "C:\Projects\santovate-crm-final"`. It never contacts production.

## Windows: Step 2 (MANDATORY after successful Git push)
Run `DEPLOY_AFTER_PUSH_V939.ps1 -ProjectPath "C:\Projects\santovate-crm-final"`. It checks HEAD == origin/master, requires the V9.3.9 commit message and a clean working tree, and invokes the repository's existing `DEPLOY_SANTOVATE.ps1 -AutoCommit:$false` deployment pipeline. This pulls source, installs PHP dependencies, runs incremental migration, swaps Vite build and checks login + asset HTTP response. The second step must run only after the first step reports PASS and push success. Do not deploy from a failed APPLY/test/push.

## Sandbox read-only diagnostics (run on sandbox-configured local environment)
`php scripts/diagnostics/ipaymu-sandbox-preflight.php`
`php scripts/diagnostics/ipaymu-e2e-audit.php`

## Live E2E gate
Use a sandbox account on a HTTPS reachable environment and known test charge. Never change paid status manually and do not forge callbacks. Confirm a new order is pending; QRIS/VA is visible; actual gateway confirms payment; signed callback arrives; status becomes paid; subscription activated or add-on grant created exactly once; a retry does not duplicate grants. Current patch adds state accuracy and audit, **not** evidence of real provider success.

## Deploy limitations
- Production Hostinger deploy intentionally separated from Git push and requires explicit command.
- Existing deploy script has migration and frontend build swap; verify server DB backup and environment before first production deploy.
- No forced push, hard reset, migrate:fresh, or automatic provider payment creation.
