# V9.5.1 — WhatsApp inbound lead review: product & safety notes

## Flow

1. Existing V9.5.0 Meta-signed webhook inserts an inbound contact and message; this is independent of Prospect quota.
2. Admin from an eligible workspace opens `/whatsapp/leads` and reviews the sender, last message, and source/referral when present.
3. Admin classifies as individual (B2C), business (B2B), or unknown; may assign an active AE and save private notes.
4. If an existing tenant-owned Prospect has exactly the same normalized phone, admin can link it to the inbound contact.
5. For a truly known B2B business, admin can enter the verified company name and city, check duplicates/quota, and create a Prospect. Conversion does not automatically grant WhatsApp marketing opt-in.
6. Individual contacts stay in the WhatsApp lead staging model, **never** in fictitious `company_name` rows; full B2C-native CRM entity support requires further schema/UI changes.

## Security/behavior

- GET/actions sit inside `auth + account.active + subscription.active + role:admin + entitlement.feature:whatsapp_api`.
- Every request uses authenticated `organization_id` and checks contact ownership; active sales AE assignment is tenant-scoped.
- Prospect link demands both org match and normalized phone equality. Manual link remains preferred over automatic matching on future webhook arrivals.
- Company conversion locks organization/contact rows inside a DB transaction, checks duplicate company_key and Prospect quota; inbound events remain in storage when blocked by quota.
- No WhatsApp messages are sent. This is not Meta Coexistence signup, TikTok attribution, live API verification or automated cold outreach.
- The filter is a query display filter; the pagination preserves it. The interface is not an automated sales inbox yet.

## UAT after local migration

- Admin with WhatsApp entitlement: `/whatsapp/leads` opens and mobile layout works.
- Sales AE without admin role: direct URL is forbidden.
- Workspaces without WhatsApp entitlement: direct URL forbidden, but existing inbound messages remain stored.
- Individual sender -> classify B2C -> assign AE -> saved; verify no fake Prospect created.
- Business sender -> company name typed -> create native B2B Prospect -> open detail.
- Existing Prospect with matching number -> link -> later signed webhook should keep that link.
- Quota full -> reject Prospect conversion but retain original contact and messages.
- Distinct tenants cannot link/review one another's contacts; do not display raw Meta access tokens.

## Production gate

Requires explicit backup; migrate V9.5.0 and V9.5.1 in environment-specific release; app build; PHP regression + feature UAT; confirm Meta webhook token and HMAC configuration; check archival/privacy policy for PII. Never deploy a migration before a database backup has been verified.
