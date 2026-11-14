# LearnCambodia approved visual identity (14 November 2026)

Brand mark: blue open book and graduation cap with gold tassel and band.

The official wordmark spells LearnCambodia. On light backgrounds, Learn is NAVY
and Cambodia is GOLD. On dark backgrounds, ALL FIVE letters of Learn are WHITE
(including L) and Cambodia is GOLD. Logos are transparent PNGs. The favicon
uses only the education mark, never the wordmark.

The approved ZIP is named LearnCambodia_Approved_Brand_All_Devices_2026-11-14.zip.
SHA256: c5c485cb6c886672be4575ab779b20923b60cc0b69dc80dc05d0ee36af06bfbb.

Included static assets: horizontal logos, PNG favicons at 16/32/48/96/128/192/
256/512, multi-size favicon.ico, iOS Apple touch icon 180px, Android Chrome
icons 192/512, PWA maskable 192/512, Windows tile 144, app icon source, and
site.webmanifest. SHA256SUMS.json lists hash and size of all approved files.

The app uses resources/views/partials/brand-head.blade.php on all public,
auth and account layouts. The responsive x-brand-logo component chooses the
approved light/dark logo according to current UI surface. Fixed navy auth
headers, account sidebar and dark footer always use the white/gold variant.

Operator installation:
1. Download the approved ZIP from the ChatGPT conversation.
2. Upload to /www/backup/ or /root on aaPanel (outside the public web root).
3. Download the SHA-pinned support/operator/learncambodia-brand-stage-github.sh
   from the PR branch. Run check then publish against the absolute ZIP path.
4. The helper checks the exact ZIP and per-image hashes, and pushes the exact
   binaries plus root favicon and Apple icon to GitHub review branch.
5. Do not merge PR #18 until PHP, isolated MySQL, browser, security and frontend
   acceptance all pass on the same GitHub head. The brand asset hash test
   intentionally fails when the binaries are not yet present.
6. Deploy only after review through site-only guarded upgrade: back up
   source, static assets and existing database. Preserve other sites, .env,
   installer lock, Nginx Basic Auth and production credentials.
7. Check desktop/mobile/Apple/Android/PWA icons, light/dark wordmark, favicon
   caching, all five auth pages, all role headers and public footer.

The review-branch asset staging helper changes neither the live website nor DB.
