# Santovate CRM V9.4.1 — Dashboard & KPI UI/UX Revamp

**Exact baseline:** `88a622ee4aab8ffb51b584477f163b48407f4337` (V9.4.0).

This increment makes dashboard actions role-aware and gives follow-up work clear
priority. Existing backend sales and finance calculations are unchanged.

## Change list

- New shared display policy `resources/js/Utils/dashboardView.js`.
- Admin KPI: Pipeline, Deal Won, Pembayaran Diterima, Piutang.
- Sales KPI: Pipeline Saya, Deal Won Saya, Opportunity Saya, Follow-up.
- Conditional Target AE links based on workspace entitlement.
- Prioritas kerja panel with direct Follow-up and Pipeline actions.
- Dashboard-only responsive CSS, focus-visible keyboard states.
- 6 new Node regression tests, run alongside existing tests.

## Apply one command after CheckOnly

```powershell
cd C:\Users\User\Downloads\<extracted-folder>\santovate_v9410
Set-ExecutionPolicy -Scope Process Bypass -Force

.\RUN_ALL_V9_4_1_AND_PUSH.ps1 -ProjectPath "C:\Projects\santovate-crm-final" -CheckOnly
# Continue only after V941_CHECKONLY_PASS
.\RUN_ALL_V9_4_1_AND_PUSH.ps1 -ProjectPath "C:\Projects\santovate-crm-final"
```

Do not run the APPLY command a second time after a successful push.
The runner checks HEAD, `origin/master`, a clean working tree and Git file blobs,
backs up changed files, tests, builds, commits only changed target files, pushes.

**No migration, seeder, production deployment, payment operation or API calls.**
Manual role-based and mobile UAT is required. See `docs/DASHBOARD_UX_V941.md`.
