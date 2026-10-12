# V9.4.2 — Prospects Search / Filters QA

## Functional acceptance
1. **Admin** searches business, contact name, email, city, service, phone: matching records appear and the search chip is shown. Clear the chip to restore the original result count.
2. **AE** sees only prospects assigned to or created by the current AE. Manually append `assigned_to=another-user` to the URL; the controller ignores this parameter for AE and does not expose other tenants' records.
3. Admin filters priority, pipeline status, qualification, and AE. These filters combine by AND, while text searches combine eligible columns by OR within the tenant-scoped query.
4. Choose **Jatuh tempo**. Items with follow-up before the current local end-of-day are returned (existing business policy); prospects without a follow-up are excluded.
5. From Import history, open `/prospects?import_batch=<valid batch ID>`. Only prospects with that `import_batch_id` appear; chip `Import #` shows the filter. Clearing the import chip removes only this constraint.
6. Switch sorting between priority, latest update, next follow-up, and potential deal value. Existing filters and pagination stay intact. Missing follow-up dates sort last.
7. Search with no matches: explanatory empty state and clear-filter button appear. Zero total records without filter: add/import actions appear.
8. Pagination preserves active URL filters and sorting; page 2 does not jump back to all prospects. Back/forward navigation synchronizes form input with applied filters.
9. Test Chrome desktop, mobile widths 390, 375, and 320 px; verify no horizontal overflow or obscured buttons.
10. Test with keyboard: search Enter, Tab/focus-visible, expandable filters, chip removal, pagination links.

## Security and production release
- Server applies `visibleTo()` before additional filtering; user input cannot change tenant boundaries.
- Input used for sorting is limited to a fixed set of four whitelisted values; no raw user SQL ordering.
- Search is truncated to 120 Unicode characters; array input is discarded.
- `import_batch` accepts only a positive decimal integer; filtering cannot bypass tenant scope.
- No schema changes or data writes from these listing changes.
- No production deployment by the APPLY runner. Production UI remains unchanged until an explicitly approved deploy.

## Next step
V9.4.3: detail/form UX audit, selective field groups and responsive prospect timeline; WhatsApp inbound lead capture follows after the UI foundation acceptance.
