# Website Skins: foundation and production integration contract

This change is additive only. No Blade templates, routes, migrations, controllers, live settings or existing homepage behavior are changed. Production has known local Sunrise/instant-switch changes not represented in main; do not deploy or overwrite its layout from this baseline.

## Scheduling
- Legacy (default): retain existing Classic/Sunrise choice and public design.
- Automatic: daily switch at midnight Asia/Phnom_Penh. Optional explicitly configured inclusive date ranges (first match wins); no hard-coded holiday dates. Manual override persists until Automatic or Legacy is restored.
- Manual: any of the nine allowlisted skins. Administrative POST with CSRF, role checks, validated configuration and audit; no second administrator approval.
- Resolve one skin per request, and keep it throughout header, content and footer. Cache keys include skin and locale; expiration no later than the next local midnight or invalidate on configuration change. Preserve user light/dark preference.
- Treat Angkor Heritage and Phumi Spirit as selectable special skins and optional date ranges.

## Rendering contract (future phases)
Nine distinct visual variants are declared in the registry, not implemented templates. Build shared accessible semantic components with allowlisted per-skin templates for header, footer, homepage, catalog, detail, articles, people, static/support pages, and auth. Do not replace authenticated assessment timers, answer forms, video controls or role dashboards. Preserve route names, forms, validation, content, multilingual copy, search and pagination. No untrusted HTML, script, URL or view name from admin inputs. Skin art direction is not an existing image asset. Produce optimized local art in a subsequent phase. Validate light/dark contrast, Khmer and Chinese typography, mobile menus, focus, reduced motion, no layout shift, no horizontal overflow. Test representative real populated pages for all skins and both modes.

## Integration gate
Before touching production-owned public layouts, inventory the live public/auth layouts and their actual inherited views/partials, reconcile local changes in a reviewed source branch, then validate in an isolated database/network environment. Do not reuse stale CI vendor/frontend bundles. Build the admin manager separately from approval-based homepage content. Ensure switching does not change campaigns or existing layout choices when reverting to Legacy. No scheduled background job is necessary for date resolution; CDN caching must honor skin/date changes.
