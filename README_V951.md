# Santovate CRM V9.5.1 — WhatsApp Lead Review & B2B Conversion

**Exact GitHub baseline:** `2b7c6d16e5b18f60309287e74f775ca3acb0117b` (V9.5.0). This is an additive, tenant-safe follow-up to verified local migration batch 17.

## Includes

- Admin-only `/whatsapp/leads` review interface, gated by the tenant's `whatsapp_api` entitlement.
- Search and status filters; 15 contacts per page; source/last-message context, no access token exposure.
- Review status (pending/qualified/ignored), lead kind (individual/business/unknown), notes and assignment to an active Account Executive in the same organization.
- Link an existing business Prospect **only when phone number and organization match**. Backfill the incoming message's Prospect ID without duplicating messages.
- Create a new native Prospect **only when the user supplies the real company name** and the tenant has enough Prospect entitlement capacity. No invented business names or automatic subscription-limit bypass.
- B2C/individual leads remain first-class *WhatsApp inbound contacts*, available to review/assign even when Prospect capacity is exhausted. Native B2C Prospect schema is deferred to a planned follow-up.
- Preserve manually linked Prospect association during later webhook events.
- New additive `whatsapp_lead_reviews` table; isolated in-memory SQLite tests.

## Running (Windows)

Unzip, open PowerShell in `santovate_v9510`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\RUN_ALL_V9_5_1_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final' -CheckOnly
# Continue ONLY after V951_CHECKONLY_PASS
.\RUN_ALL_V9_5_1_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final'
```

Do not reapply after successful commit/push. Runner refuses changed Git HEAD, origin/master, dirty working tree or unexpected file blobs, checks SHA-256 payloads, backups sources, runs PHP tests and Vite, stages only approved paths, then commits/pushes.

**The runner DOES NOT run `php artisan migrate` or deploy Hostinger.** After success, separately inspect `php artisan migrate:status`, backup the local database, check `php artisan migrate --pretend`, and run the new migration intentionally in development:

```powershell
php artisan migrate --path=database/migrations/2026_10_12_160000_create_whatsapp_lead_reviews_table.php
php artisan migrate:status
php artisan test --filter=WhatsAppLeadReviewTest
```

Do not enable production inbound traffic for un-migrated production code; plan a production backup/deploy/migration gate separately. Existing Meta WhatsApp Business phone Coexistence onboarding remains future work.
