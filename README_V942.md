# Santovate CRM V9.4.2 — Prospect Search & Filter UX

**Exact source baseline:** `c2dfad4ca099aac00dd831f06ca60e1c0814a648` (V9.4.1).

This release improves the prospect listing while preserving tenant visibility, sales permissions, status transitions, and underlying CRM data.

## Changes
- Search business, PIC, city, service, email, phone, normalized WhatsApp number.
- Import-batch URL filter now really filters `import_batch_id`, instead of only showing a banner.
- Sort by priority score, last updated, next follow-up (NULL last), or largest potential value.
- Desktop/mobile compact toolbar, optional advanced filters, active-filter removable chips, reset, accessible pagination, better empty results.
- Whitelisted, bounded backend query filters; only Admin can filter by Account Executive.
- Listing avoids eager-loading product relations not displayed in the index.
- Unit tests for PHP filter normalization and 7 JavaScript filter tests; full PHP tests and JS build run in local APPLY.

## Windows workflow
Extract ZIP, open PowerShell inside `santovate_v9420`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\RUN_ALL_V9_4_2_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final' -CheckOnly
# Only after V942_CHECKONLY_PASS:
.\RUN_ALL_V9_4_2_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final'
```

Run the APPLY exactly once after CheckOnly. Verify `V942_SUCCESS` and saved commit ID. The runner stops if HEAD/origin/master or the exact file blobs differ; it preserves user changes by refusing dirty working trees. On pre-commit failure, only the patch targets are restored from backup; after commit, no reset/re-apply (push the existing commit if needed).

**Not included:** DB migrations, destructive operations, seeding, data import, WhatsApp API calls, gateway operations, Hostinger deploy.

## Manual QA
Follow `docs/PROSPECT_UX_V942.md` after local push. Regression tests and build do not replace human visual checks on actual client devices.
