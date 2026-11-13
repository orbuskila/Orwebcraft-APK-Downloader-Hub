Version 2.3.2 app discovery improvements

Update the existing WPDM APK Source Connector plugin. WordPress Download Manager and the original APK Downloader remain required.

- Archive filters: title/content search, exact developer/company, category text, Android requirement text, store version text, date range, and reviewed ecology topics. Sort by title, added date, modified date, total WPDM downloads or recorded page views this week. Filters are combined and preserved during pagination. Missing metadata is not invented.
- Clicking a developer name opens a dedicated filtered listing on /archive/.
- App detail panels distinguish Google Play, developer support, APK mirrors and user-submitted/externally linked files. SHA-256 is calculated on package save for eligible local APKs in uploads/WPDM storage, at most 10 files of 100 MB each. Remote files are not downloaded. Signer SHA-256 and report URL are administrator-supplied after actual apksigner verification; no automatic signature or malware scan is claimed.
- Store versions observed after this update are retained as metadata history. Historical APK binaries are not created.
- Signed-in favorites, private named collections and following lists are stored separately per WordPress account. REST writes require WordPress authentication and nonce. Lists respect publication state and passwords.
- /app-collections/ shows favorites, collection remove controls, followed versions and a notice when the current version differs from the version observed when following began. Notifications are in-site; no unsolicited emails or push messages are sent.
- The existing /my-apps-apk-contributor-dashboard/ includes paginated contribution cards with publication status, creation date, total downloads and newly recorded page views, plus authorized edit links. Add-new/editor flows remain handled by WPDM.
- Page views are approximate aggregate counts, limited by a one-hour per-app browser cookie. Popular this week means recorded app-page views since Monday, not APK downloads. Bots or disabled scripts can affect counts.
- Download trends are cumulative WPDM totals sampled hourly when WP-Cron runs; there is no retroactive history. Initial sampling covers up to 500 published packages.
- Ecology taxonomy has Supports conservation, Nature education, Ocean, Forest, Wildlife, Community recommended and Astronomy. Exact iNaturalist/Seek/BirdNET package IDs receive wildlife/education tags; other assignments require editorial review.
- /support-ecology/ links to the existing Earth ecosystem GiveWP fundraiser. /ecology-thank-you/ provides an archive return path. If GiveWP uses a configured WordPress success page, the thank-you section is appended without replacing its receipt. Third-party hosted payment redirects require configuration at that provider.
- New pages are created once when an administrator next opens WordPress; existing pages are not overwritten. Deactivation leaves pages/data available for rollback, with shortcodes inactive until reactivation.
- App screenshots and archive thumbnails use lazy loading. New CSS/JS loads only on relevant app/archive/account/support pages. The archive stylesheet is similarly scoped.
- PWA uses the site's existing SuperPWA installation; this connector does not register a second manifest or service worker.

Validation: PHP 7.4 syntax parsing and JavaScript syntax check. Live developer listing returned three BirdNET-Team packages; authenticated favorite saving returned Saved. Live combined EDUCATION and Wildlife filters returned five packages. Private collections saved successfully. Custom ordering filters are suppressed in the archive/contribution queries to honor requested sort order. Signature fields are restricted to administrators. Validate signed-out behavior and actual donation checkout/confirmation with the payment provider; no donation was made during testing. Donations and signature verification are not simulated.

Version 2.2.0 archive improvements

- Tools > App archive maintenance refreshes all WPDM packages in batches of five.
- Old packages match by exact Android package ID or one unambiguous Google Play / APKCombo URL.
- Legacy files, links, titles, status, and content remain intact.
- Archive metadata matching box supports package and developer overrides.
- [owc_app_archive] provides published packages, search, pagination, A-Z and other sorting.
- Store version is labelled separately from uploaded APK version.
- Zero and unitless file sizes display as unavailable; known units are normalized.
- Maintenance results distinguish skipped/unavailable records. WP-Cron requires site traffic.
- Google Play may withhold fields or remove listings. No company names are guessed.

WPDM APK Source Connector 2.0.6

Open source WordPress plugin licensed under GPL-2.0-or-later. WordPress Download Manager and the original [apk_downloader_form] plugin should remain active.

Install this ZIP through Plugins > Add New > Upload Plugin and replace the previous WPDM APK Source Connector. Clear the page cache after updating. Keep [apk_downloader_form] on the existing APK Downloader page. No separate submission form is needed.

To add the researched apps, open Settings > WPDM APK Connector and click "Add these apps and sync". The screen shows the 13 exact Google Play package IDs and links before you run it. The action merges missing IDs into the existing managed list, leaves existing entries intact, and syncs WPDM packages. Repeating it will not add duplicate IDs. The APK Downloader page displays recent archive apps beneath its original form, with links to their Downloads pages and Google Play. WP-Cron continues refreshing the managed list hourly.

The connector renders a "Connect and sync with archive" button and short instructions inside the original form. Enter a package name or Google Play URL above the button. Optionally paste the matching APKCombo app page link. Click Connect and sync with archive to publish or update a WPDM package and return to the downloader page. The success panel provides the app/archive links, prepared package name and Play URL to copy into APKCombo Downloader, and the APKCombo app page link when supplied.

This button uses an ordinary same-site form POST to WordPress admin-post.php. It does not depend on capture.js loading, so it keeps working if a browser extension or optimizer blocks the optional script. The optional script still attempts automatic background sync when visitors use the original Generate Download Link button. If that script is blocked, visitors can use the separate Connect and sync button.

The connector fetches app title, icon, description and preview images from Google Play when available. No APK binary is stored. Settings > WPDM APK Connector supports a managed list that syncs on demand or hourly through WP-Cron. Manually Private packages remain Private. [owc_apk_downloads] lists published connector apps; it accepts limit="5".

Developer/company names appear on connector app pages and in [owc_apk_downloads]. The plugin reads the name from Google Play structured data or its developer link, then refreshes existing connector apps in WP-Cron batches after upgrading. If no name is available, it displays “Not listed”; edit the WPDM package and enter Developer/company name in App details to supply or correct it. This does not alter unrelated WPDM downloads or the theme's separate /archive/ listing cards.

For an immediate retry, open Settings > WPDM APK Connector and click “Sync developer names from Google Play.” Repeat until the message says finished; it processes 5 existing connector apps per click and bypasses cached Play results. This action changes only the fetched company field. The automatic refresh also runs in batches as visitors trigger WP-Cron.

An APKCombo app page link must identify the same package. APKCombo Downloader can take a package name or Google Play link pasted into its search box; the plugin does not bypass APKCombo verification or download files from it.
