# Santovate CRM V9.4.1 — Dashboard UX & Role-Aware KPI

Base Git commit: `88a622ee4aab8ffb51b584477f163b48407f4337` (V9.4.0).

## Why this increment exists

V9.4.0 standardized navigation. The Dashboard still rendered two clickable cards
(`Collected`, `Outstanding`) linking to `/finance` for Account Executives, while
`/finance` is protected by `role:admin`. Some target links appeared despite the
`targets` entitlement being disabled. These were navigation UX defects, not an
authorization bypass.

## Changes

- Adds a visually prominent but restrained **Prioritas kerja** panel directing
  sales to follow-ups and pipeline.
- Admin primary KPI: pipeline estimate, won deal value, net client payments,
  outstanding receivables.
- Account Executive primary KPI: visible pipeline estimate, won deal value,
  visible opportunity value, follow-up queue count.
- Secondary metrics follow Admin/AE needs; neither group sees an invalid
  `/finance` link. AE commission links to `/finance/commissions`.
- Target links in personal performance and admin team cards are shown only when
  the `targets` entitlement is true.
- Responsive layout and keyboard focus states for dashboard-specific elements.
- Extracted a tiny pure `dashboardView.js` module that chooses descriptors,
  without changing how the backend calculates amounts.

## Non-goals

- No formula or finance calculation changes; numbers continue coming from
  `DashboardController` and `FinanceService`.
- No migrations, data modification, extra role types, or API integrations.
- No Hostinger deployment and no claim that unit tests constitute visual UAT.
- No CSS deletion: old stylesheets remain unchanged.

## Verification

1. Login Admin → Dashboard: 4 primary KPIs include `Pembayaran Diterima` and
   `Piutang Belum Dibayar`. Finance links work.
2. Login Account Executive → Dashboard: see `Opportunity Saya` and
   `Follow-up Perlu Tindakan`; no `/finance` link.
3. Tenant without `targets` entitlement: no clickable Target AE in performance
   card. Tenant with it: card links correctly.
4. Test 320px, 390px, 768px, 1366px: no horizontal overflow in focus panel or KPI.
5. Test keyboard focus with Tab; focus outline is visible.
6. Confirm finance/commission/quotation numbers match **before** patch.
7. Review sales monitoring table and prospect cards unchanged.

## Windows safe workflow

From extracted `santovate_v9410`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\RUN_ALL_V9_4_1_AND_PUSH.ps1 -ProjectPath "C:\Projects\santovate-crm-final" -CheckOnly
.\RUN_ALL_V9_4_1_AND_PUSH.ps1 -ProjectPath "C:\Projects\santovate-crm-final"
```

The command requires a clean `master` checkout at the exact base SHA, verifies
source Git blob IDs, creates an OS temporary backup, applies ONLY 8 planned
paths, runs Laravel and Node tests, executes Vite build, stages exactly the
8 paths, commits and pushes after successful checks. Git and build output
are interpreted using exit codes, **not by whether stderr contains a warning**.

If failure occurs before commit, backup restores only the patch target files.
If push fails after commit, retain commit; do not rerun the patch or reset:
check `git status` and retry `git push origin master`.

`-SkipPush` allows intentionally stopping after commit, if desired.
