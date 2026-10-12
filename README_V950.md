# Santovate CRM V9.5.0 — WhatsApp Inbound Capture Foundation (WA-01A)

**Baseline (strict):** `314bc63f35fae8abae495d496f79809a2821e18a` (V9.4.3).

This is the **inbound capture layer**, not yet end-to-end WhatsApp Business phone onboarding or automatic Prospect creation. It protects previously-unknown senders without inventing company names in the B2B `prospects` schema.

## Included
- New `whatsapp_inbound_contacts` tenant-scoped staging table. One row per normalized sender number and tenant; first Meta ad referral retained when present.
- All verified inbound messages saved in existing `whatsapp_messages`, including unknown numbers (`prospect_id = NULL`).
- Duplicate message ID replay is ignored instead of creating duplicate activities.
- Ambiguous `phone_number_id` channel ownership returns 503 for retry and is rejected (no cross-tenant routing), inactive channels ignored.
- Existing known-prospect activity and stage transition retained after a newly created inbound message.
- 5 isolated SQLite integration tests for unknown contacts, replay, tenant routing, ambiguous channels, Meta referral.

## Safe APPLY (Windows PowerShell)

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\RUN_ALL_V9_5_0_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final' -CheckOnly
# Continue only after V950_CHECKONLY_PASS
.\RUN_ALL_V9_5_0_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final'
```

The runner **does not run `artisan migrate`** and does not deploy to Hostinger. It runs PHP lint, all Laravel tests (with an isolated SQLite database for this new integration suite), existing JS regression and Vite build. Only the eight approved source files are staged, committed and pushed if all checks succeed.

## Migration release gate (separate)

A database migration is required before processing live signed inbound webhooks. DO NOT connect Meta events to this version before backup and migration have been completed and reviewed. On your *local development database*, after confirming a verified backup and that `php artisan migrate:status` identifies the new migration as pending, apply the new migration deliberately; production requires a separate approved release plan.

If production still runs V9.4.x, do not point production webhooks at this un-migrated code. Pre-existing `WHATSAPP_APP_SECRET` and verify token are still required.

## Not implemented yet
- No automatic conversion of WhatsApp contacts into native CRM prospects, no assignment, no Team Inbox UI, no Coexistence Embedded Signup, no WhatsApp Business phone-app sync, no outbound replies.
- Contact lead stage is **persisted in the database** even when subscription prospect quota is full. Prospect conversion and a review queue will be developed in V9.5.1.
- `referral` may be missing; a Meta source is not proof that placement was Facebook vs Instagram; TikTok campaign attribution remains separate.

Read `docs/WHATSAPP_INBOUND_V950.md` for the production checklist.
