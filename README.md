# WP Import Remote Images

A one-way WordPress plugin for test and development environments.

This plugin processes **WordPress XML (WXR) imports** and rewrites image URLs in imported content so that images are loaded from the **production site**, without downloading media files locally.

The plugin is intentionally **one-way** and must be installed **only on the test server**.

---

## Why this plugin exists

When importing content from production into a test environment, WordPress usually tries to:

- download all images into `uploads`
- create attachment records
- quickly consume disk space

This plugin solves that by:

- keeping images remote (loaded from production)
- preventing media downloads
- allowing repeated imports without growing disk usage
- preserving realistic front-end behavior for theme development

---

## Key principles

- **One-way only** (test server → no write access to production)
- **Import-time only** (does nothing outside WXR import)
- **No media downloads**
- **No attachments created**
- **Production is read-only**

This is a development tool, not a migration plugin.

---

## How it works

During a standard WordPress import (`Tools → Import → WordPress` or `wp import`):

- image URLs inside imported content are rewritten to the production domain
- relative and test-domain image URLs are replaced
- media files are not downloaded
- the media library remains untouched

Normal post editing and saving are not affected.

---

## ⚠️ Important Importer Option (Must Be Disabled)

When using the WordPress Importer, **make sure this option is disabled**:

> **“Change all imported URLs that currently link to the previous site so that they now link to this site”**

### Why?

This option blindly rewrites all URLs to the test domain **before** the plugin runs, which breaks the intended behavior and may result in incorrect image links.

**Always keep this option disabled when using this plugin.**

---

## Installation

1. Copy the plugin directory to:
2. Activate the plugin in the WordPress admin panel.

---

## Usage

1. Go to **Tools → Import → WordPress**
2. Upload a WordPress XML (WXR) file
3. When prompted:
- ❌ Do NOT enable  
  “Change all imported URLs that currently link to the previous site so that they now link to this site”
4. Complete the import

That’s it.

---

## Intended use cases

- Theme and layout development
- Testing imported content
- Repeated imports in dev environments
- Low-disk or containerized setups

---

## Not intended for

- Production websites
- Full site migrations
- Media synchronization
- CDN replacement

---

## Compatibility

- WordPress 6.x
- PHP 8.1+
- WordPress Importer (UI and WP-CLI)

---

## License

GPL-2.0-or-later
