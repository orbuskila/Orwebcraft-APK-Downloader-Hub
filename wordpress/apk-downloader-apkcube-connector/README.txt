APK Downloader 1.0.0 → APKCube + APKCombo Connector 1.3.1

Separate companion plugin for original APK Downloader 1.0.0. Keep [apk_downloader_form] on the page and activate both plugins.

This correction labels APKCombo links accurately. It makes a package-specific link to APKCombo's downloader, but it does not generate APKCombo's final signed file URL on WordPress. APKCombo's browser flow handles verification, device selection, and final download, and may offer XAPK/APKS rather than a plain APK. Its downloader does not expose a supported public API for the connector to mint final links.

The connector also shows APKCube's public app card. Replace the previous connector ZIP and clear the site's script cache. Do not expect an APKCombo file URL in the connector result.
