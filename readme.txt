=== WP Import Remote Images ===
Contributors: Ersan Egorov
Tags: import, xml, wxr, images, media, development
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A one-way plugin for test environments that rewrites image URLs to production during WordPress XML import without downloading media files.

== Description ==

WP Import Remote Images is a one-way helper plugin designed for **test and development environments**.

During a standard WordPress XML (WXR) import, the plugin rewrites image URLs in imported content so that they point to the production site instead of downloading media files locally.  
No attachments are created, and no media files are stored on the test server.

The plugin operates **only during the import process** and does not affect normal post editing, saving, or updates.

Production is treated as a **read-only source**.

== Important Importer Option ==

When using the WordPress Importer, the following option **must be disabled**:

"Change all imported URLs that currently link to the previous site so that they now link to this site"

If this option is enabled, WordPress will rewrite URLs before the plugin runs, which breaks the intended behavior and may result in incorrect image links.

Always keep this option disabled when using this plugin.

== Features ==

* One-way behavior (test server only)
* Works with standard WordPress XML (WXR) imports
* Rewrites image URLs to production
* Does not download or store media files
* Does not create attachments
* Leaves normal WordPress behavior untouched

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Use the standard WordPress Importer to import a WXR file.

== Usage ==

1. Go to **Tools → Import → WordPress**
2. Upload your WordPress XML (WXR) file
3. When prompted, make sure the following option is **disabled**:
   - "Change all imported URLs that currently link to the previous site so that they now link to this site"
4. Proceed with the import

During import, image URLs will be rewritten to the production site, and no media files will be downloaded.

== Intended Use ==

This plugin is intended for:

* Theme and layout development
* Testing imported content without increasing disk usage
* Repeated imports in development environments

This plugin is **not intended for production use**.

== Frequently Asked Questions ==

= Does this plugin download images? =

No. Images are not downloaded, and no attachments are created.

= Does this plugin affect existing posts or manual edits? =

No. The plugin only runs during the import process.

= Do I need to install this plugin on the production site? =

No. The plugin must be installed **only on the test server**.

= Can this be used with WP-CLI import? =

Yes. The plugin works with both the WordPress Importer UI and `wp import`.

== Changelog ==

= 0.1 =
* Initial release

== Upgrade Notice ==

= 0.1 =
Initial release.
