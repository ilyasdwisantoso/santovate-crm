# Santovate CRM V9.3.10 — Registration UX Hotfix

Source baseline: `29f3d07a4318fd0fc5d876468e6c9d67abf0d28b` (V9.3.9).

Focus:
- Indonesian validation messages (`lang/id/validation.php`), including password confirmation mismatch.
- Inline password mismatch warning before registration submits; Laravel server-side `confirmed` stays intact.
- Input autocomplete, errors, and accessibility on the registration page.
- Fix mojibake / encoding on public login UI.

No change to payment, subscriptions, registration price, backend credential policy, migrations or production data.

## Workflow (two separate commands)

1. Run `RUN_APPLY_TEST_PUSH_V9310.ps1 -ProjectPath "C:\Projects\santovate-crm-final"` on Windows.
2. Only after `V9310_PUSH_OK`, run `DEPLOY_AFTER_PUSH_V9310.ps1 -ProjectPath "C:\Projects\santovate-crm-final"`.
3. Visually verify `/login` and `/register?plan=growth&config=software-agency`; test confirmation mismatch first. For complete payments, run official iPaymu Sandbox E2E separately.

Deployment uses the repository's existing Hostinger SSH pipeline with `AutoCommit=$false`. Verify production database backup before deployment; the existing pipeline may run `artisan migrate --force` (incremental only). No new migration is part of V9.3.10.

V9.3.10-R1 packaging note: corrected app.jsx baseline SHA and anchor for V9.3.9.
