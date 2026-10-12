# V9.5.0 — WA-01A Technical Notes

## Pipeline
Signed Meta webhook → strict active phone-number channel mapping → service transaction → tenant-scoped incoming contact → unique provider message → existing-prospect activity only when uniquely matched.

## Data lifecycle
- `whatsapp_inbound_contacts` is **capture/staging**, not a substitute for `prospects`; this avoids fake company names.
- Duplicate webhook messages are idempotent by `whatsapp_messages.provider_message_id` (existing unique index). On concurrent duplicate insert, the service only swallows a constraint violation if the exact message ID exists.
- Unknown contact messages retain `prospect_id = NULL`; nothing disappears due to prospect entitlement or missing company data.
- Raw inbound `provider_payload` and `referral` contain personal information. Restrict DB access and define retention policy before production.
- `source_name = Meta Ads` only when Meta supplies `referral.source_type = ad`. No unsupported TikTok or FB-vs-IG assumptions.
- Existing one-channel-per-organization restriction remains; multi-number tenant support is a later migration.

## Production deployment gates
1. Confirm verified production backup and recovery plan; check pending migrations and retention.
2. Deploy new code and perform `php artisan migrate --force` **only during an explicitly planned deploy** (not by APPLY runner). Check table schema and indexes.
3. Ensure `WHATSAPP_APP_SECRET` and `WHATSAPP_VERIFY_TOKEN` are configured; on an HTTPS domain test HMAC failure cases.
4. Confirm channel phone number ID uniqueness and its active status; verify tenant routing with test numbers.
5. Run real Meta webhook inbound tests after official API onboarding; test replay and out-of-order timestamps.
6. Test data-access policy before adding the review interface in V9.5.1. Do not expose a public endpoint for staged contacts.
7. Back up and monitor logs, error handling, and capacity. Set up Coexistence using an eligible WhatsApp Business App number only after official Meta/provider onboarding.

## Future V9.5.1
Tenant-only review UI, B2C/customer contact model vs existing B2B company model, entitlement-safe conversion to Prospect, AE assignment, inbox unread states, and consent handling.
