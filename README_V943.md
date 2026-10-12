# Santovate CRM V9.4.3 — Prospect Detail & Form UX Optimization

Exact audited GitHub master baseline: `b3c53480bb2fa9e3928060de58f45e8e04a428bd` (V9.4.2).

## Functional scope
- Prospect form: jump navigation between Profile, Contact and Sales; show optional sales detail fields only when requested for new prospects. Existing prospects open the detail section by default; validation errors in hidden fields reopen it.
- Form: native company-name validation, telephone/email autocomplete, accessible score selection, dirty-form notice and removal of nested form labels.
- Detail: filter activity timeline by type, initially show six matching events with a 'show all' action, and give distinct empty states.
- WhatsApp quick action normalizes Indonesian local numbers (08...) to an international wa.me link; malformed numbers cannot produce a link.
- External source link is shown only for valid HTTP(S) URL.
- New CSS is scoped to `p943-` classes; previous styles retained.
- JavaScript display-policy tests; existing PHP and JS tests rerun.

No migrations or backend business-rule changes. This is not a WhatsApp Cloud API integration or auto lead capture.

## Execution on Windows PowerShell
Unzip, open the `santovate_v9430` directory, then:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\RUN_ALL_V9_4_3_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final' -CheckOnly
# Continue only when V943_CHECKONLY_PASS is shown:
.\RUN_ALL_V9_4_3_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final'
```

The Node runner requires a clean `master` exactly at the baseline SHA and checks the remote and exact source blob hashes. It creates a temp backup, patches only 4 existing files and adds 5 new files, runs project tests and Vite build, stages only approved paths, then commits and pushes. It never runs DB migrations, seeding or Hostinger deployment.

If a check fails before the commit, the runner restores only its own file changes. If push fails after commit, **do not reapply**; inspect git status and push the existing commit.

## UAT requirements
Follow `docs/PROSPECT_UX_V943.md`. On create mode, hidden optional fields must not be cleared from submitted form data. On edit mode, previously populated fields must be visible and unchanged. Tests running in the local project are not production UAT.
