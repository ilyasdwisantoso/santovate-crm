# Santovate CRM — Master Roadmap 2026–2027

Updated: 2026-10-12. Status below is not proof of Hostinger production deployment.

## Policy
- Core stability and tenant isolation outrank feature count.
- Every UI/UX improvement uses shared design tokens and responsive tests; avoid adding version-specific CSS when a shared component fits.
- Features may be hidden, simplified, modularized, or retired **only after** dependency and data-retention analysis.
- Assess all new requests by business impact, risk, effort, and industry coverage.
- Source audit is read-only; rollout uses guarded APPLY → VERIFY → COMMIT → PUSH. Hostinger deployment and live database migrations remain separate approval gates.

## Currently verified in source
- V9.3.7 invitation and V9.3.8 invitation lifecycle tests; V9.3.9 payment state accuracy; V9.3.10 registration validation; V9.3.11 registration UI/pricing.
- Existing CRM Prospects, Follow-ups, Pipeline, Opportunities, Quotations, Deals, Finance, Entitlements, WhatsApp Cloud API foundation.
- Production UAT for the latest versions and genuine payment callback E2E still require separate evidence.

## Priorities
| Order | Proposed release | Workstream | Exit criterion |
|---|---|---|---|
| P0 | V9.3.12 | Security/readiness foundation | Webhook rejects unsigned events, production seeder guarded, baseline diagnostics and tests |
| P0 | V9.3.x QA follow-up | Payment, registration, tenant boundaries | Real sandbox payment E2E and independent tenant access verification |
| P1 | V9.4.x | UI/UX Design System 2.0 | Shared navigation, responsive forms/cards, consolidated CSS, usability on 320px–desktop |
| P1 | V9.5.x WA-01 | Inbound lead capture | Unknown WhatsApp numbers form tenant-scoped contacts/leads; duplicate webhook creates only one lead/event; quota-overflow messages retained |
| P1 | V9.6.x WA-02 | Team Inbox | Tenant-safe conversations, assignment, unread/response states, message reply |
| P1 | V9.7.x WA-03/04 | Meta Ads attribution / Coexistence pilot | Preserve referral when provided, honest unknown-source handling, official onboarding and HP sync validated |
| P2 | V9.8.x | Sales flow optimization | Lead → opportunity → quotation → deal → payment with less re-entry |
| P2 | V10.x | Industry extensions | Optional logistics, project delivery and distributor workflows based on tenant entitlement |
| Cross-cutting | Each release | Quality / security / performance | Tests, schema safety, release notes, rollback, deployment and UAT evidence |

## Feature simplification candidates (not yet removed)
- Sales Guide as in-flow guidance on Opportunities and Quotations.
- Lead scoring and Targets as optional tenant modules.
- Hide WhatsApp Campaign until verified channel and entitlement are ready.
- Advanced business settings remain behind a clearly labeled advanced section.
- Logistics/Delivery modules visible only to tenants that need them.
- Review `resources/css/*v*.css` patch stack; do not delete historical CSS before coverage checks.

## Request intake and prioritization
| Type | Urgency | Processing |
|---|---|---|
| Security/data/payment/login production incident | P0 | Fix first, regression tests mandatory |
| UX blocker for common task | P1 | Include in next UI increment; measure clicks/time |
| Feature with verified customer demand | P1/P2 | Define acceptance criteria and tenant-specific capability |
| Unused/duplicate feature | P2 | Hide → measure → simplify → remove only with migration plan |

States: Proposed → Analyzed → Approved → In development → Automated QA → UAT → Released; or Deferred/Rejected.

## Explicit non-goals for V9.3.12
- No payment provider changes, manual payment confirmation or production callbacks.
- No schema changes, seeding, deletion, mass updates or demo-user password rotation.
- No WhatsApp number onboarding, message echo sync or automatic lead creation yet.
- No claim that localhost tests prove production readiness.
