# Santovate CRM — V9.4.0 UI/UX Navigation Foundation

Baseline commit: `88b3b93a22a76b0854a2a88237cef73187e16610` (V9.3.12).

## Implemented
- `workspaceNavigation.js`: single navigation source across desktop and mobile for workspace, execution and admin. Existing `adminOnly`, entitlements, `is_internal`, and platform-owner behaviors preserved. All route middleware remains the real authorization enforcement.
- Navigation links provide `aria-current=page`; main and mobile nav have accessible labels.
- Mobile drawer exposes dialog semantics, `aria-expanded` and `aria-controls`. Escape closes the drawer or notification popover. Existing close-on-route-change and body scroll lock remain.
- Shared `workspace-navigation.css` provides focus states, touch targets, reduced-motion support, and tall-screen scroll resilience. No removal of historical CSS yet.
- Seven deterministic Node.js policy tests, without network or database.

## Deliberate exclusions
- No business logic, routes, migrations, user/permission changes, subscription/pricing or payment changes.
- No new CRM features, page-level redesigns, sidebar auto-collapse or destructive stylesheet cleanup.
- No Hostinger deployment or production database operations.

## Acceptance / UAT
1. Login as Admin and Sales, compare desktop sidebar with mobile drawer. Admin-only links must never show to Sales.
2. Confirm internal workspace hides product catalog. External demo tenant with entitlement can show product catalog.
3. Switch to tenant with targets/imports/WhatsApp feature disabled: gated menu entries must stay hidden.
4. Mobile (320, 390, 768 px): tap menu, tap outside, press Escape, switch pages; drawer closes and page scroll resumes.
5. Keyboard Tab navigation: focus visibly indicated; active menu link has `aria-current=page`.
6. Compare dashboard, prospects, Team & Access, Finance; no horizontal overflow or clipped topbar.

## Proposed next increments
- V9.4.1: dashboard density and responsive KPI blocks.
- V9.4.2: prospect list/detail UX and search/filter controls.
- V9.4.3: shared form/table primitives and CSS patch-stack consolidation (with visual regression coverage).

Do not interpret a code/build PASS as visual-UAT PASS. Production deploy requires a separate explicit action.
