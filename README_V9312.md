# Santovate CRM V9.3.12 — Security Baseline & Product Audit Foundation

Base commit: `f44778379ce3ae563a83285bcf849e2cd00cce8f` (V9.3.11).

## Implementasi pada increment ini
1. `WhatsAppWebhookController`: verification GET requires a nonempty configured verify token. POST strictly requires a configured HMAC app secret and a matching `X-Hub-Signature-256`. Returns 503 if not configured, 401 if signed incorrectly. No changes to message processing or data schema.
2. `DemoAccountSeeder`: refuses execution when app environment/config is production, before making DB writes or resetting passwords to demo values. Development seeder remains usable locally. **Existing demo accounts in production are NOT disabled/rotated by this patch**; do so manually with backup and access review.
3. `VERSION`: 9.3.12. Existing `README.md` explicitly marked legacy, old default credential block replaced with safe guidance.
4. Unit tests for webhook verification/signatures and demo seeder production guard.
5. A read-only, sanitized CLI configuration diagnostic (`scripts/diagnostics/v9312-readiness.php`).
6. A prioritized roadmap/backlog document (`docs/ROADMAP_MASTER_2026_2027.md`).

## Apply on Windows (user-requested approach)
```powershell
cd C:\Users\User\Downloads\Santovate_CRM_V9.3.12_20261012\santovate_v9312
Set-ExecutionPolicy -Scope Process Bypass -Force
.\RUN_ALL_V9_3_12_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final' -CheckOnly
.\RUN_ALL_V9_3_12_AND_PUSH.ps1 -ProjectPath 'C:\Projects\santovate-crm-final'
```
The second command checks Git baseline and clean tree, applies the guarded patch with backup, lints PHP, runs Unit/JS tests, performs Vite build, stages only patch files, commits and pushes to `origin/master`. Resume is supported for exactly matching fully applied patch files before commit. No `git reset --hard`, no force push.

## Production boundaries
- Git push **does not** deploy to Hostinger.
- No migration and no DB write by the patch runner or diagnostic. Existing tests run with their own test policy/configuration.
- SMTP credentials, app secrets, payment references and access tokens are not printed in diagnostics.
- Run `php scripts/diagnostics/v9312-readiness.php` locally for review. Run `... --strict` on a configured target when you intend to gate required production settings.
- Verify production `.env`: `WHATSAPP_VERIFY_TOKEN` and `WHATSAPP_APP_SECRET` must be configured before WhatsApp webhook onboarding. The POST webhook now refuses unsigned requests instead of silently accepting them.
- Real iPaymu sandbox signed-callback E2E, real SMTP and WhatsApp Coexistence onboarding remain **not verified**, and must not be represented as PASS based on these tests.
- If the username/password of existing demo accounts has been left as the seeder default on any public deployment, rotate/deactivate those accounts independently; this patch does not modify them.

## Remaining roadmap
See `docs/ROADMAP_MASTER_2026_2027.md`; immediate next candidates are V9.4.x Design System / UX cleanup and V9.5.x secure WhatsApp auto lead capture (including deduplication, tenant attribution and safe queue processing).
