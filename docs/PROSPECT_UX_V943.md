# V9.4.3 Prospect Form / Detail UX Acceptance

## Source audit and constraints
- `Prospects/Form.jsx` previously showed all optional budget, timeline, probability, source and notes fields at once.
- `Prospects/Show.jsx` previously displayed all activities at once and generated `wa.me` links from raw digits (which can start with `0`).
- Backend `ProspectController`, `ProspectRequest`, `ProspectResource`, scoring logic and tenant-scoped visibility are left untouched.
- V9.4.2 search, sorting, pagination and import-batch filtering remain untouched.

## UAT (localhost before production)
1. **Admin / new prospect:** Open `/prospects/create`, view the 3 section jump links. Core fields and the Save button should remain visible. Optional sales details remain collapsed until expanded.
2. **Persisting advanced data:** Expand the optional section, enter budget, source, timeline and notes; save. Edit and verify all values are unchanged. Expand/collapse changes display only, never mutates `useForm.data`.
3. **Validation:** Blank company name must be rejected. If an advanced field fails server validation, optional section opens automatically and shows its error.
4. **Edit existing prospect:** All detail fields open by default; values including scoring, pipeline, assignment remain unchanged until explicitly saved.
5. **AE role:** Admin-only AE selection remains hidden; form submit and score radio-like `aria-pressed` buttons work with keyboard.
6. **Detail timeline:** Empty timeline shows empty state. With >6 activities, default display is first six, Show all expands. Filtering by type shows exact count and allows reset to all.
7. **Contact actions:** Phone `0812...` opens `https://wa.me/62812...`; +62 works as provided; malformed phone yields no WhatsApp link. Email/phone direct actions remain available as applicable.
8. **External link safety:** Source URL with valid http(s) opens in new tab. Invalid or non-HTTP(S) URL is not linked.
9. **Responsive:** At 320/375/768/1280 px, jump nav, optional fields, scoring, timeline and action buttons fit without horizontal overflow.
10. **Regression:** Check V9.4.2 filters and navigation; target role boundaries and finance access are unaffected.

## Release gate
- `V943_CHECKONLY_PASS`; Laravel tests; all JS tests; Vite build; exact-path Git commit/push; manual Admin/AE UAT.
- No DB migrations, seeders, Webhook/Meta integration, live gateway, Hostinger deploy.
- Follow-up roadmap: WA lead auto capture V9.5.0 requires a separate schema/security review.
