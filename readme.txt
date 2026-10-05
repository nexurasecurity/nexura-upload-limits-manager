=== Nexura Upload Limits Manager – Increase Maximum Upload File Size ===
Contributors: prokashsarker2026, nexurasecurity
Tags: increase upload limit, max upload size, big file uploads, storage quota, upload filesize
Requires at least: 5.6
Tested up to: 7.1
Stable tag: 1.0.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Raise upload size, memory, and time limits. Large files still upload when the host limit is 2 MB.

== Description ==

**Nexura Upload Limits Manager** raises the maximum upload file size and lets you set PHP memory, execution time, and storage quotas for roles and individual users.

Use it when uploads fail with "upload_max_filesize exceeded" or "Allowed memory size exhausted", or when you need to upload large media on shared hosting.

The chunked upload engine splits a large file into smaller pieces and sends them one at a time. That works around per-request limits such as `upload_max_filesize` and `post_max_size`, within your host's disk space and timeout.

> 💡 **Perfect for:** WooCommerce store owners, Video/Course creators (LMS), Photographers, Bloggers, Developers, and anyone tired of WordPress upload restrictions.

---

### 🚀 Key Features (Why Choose Nexura?)

* **Large File Uploads:** Splits big files into chunks so a single request stays under `upload_max_filesize` and `post_max_size`. The final file still has to fit on disk, and your host can block the upload for other reasons.
* **Upload Resume & Retry Support:** If your connection drops, Nexura will automatically retry the upload. If you need to restart the upload, it can resume compatible uploads using the same session exactly where they left off!
* **Increase PHP Limits in One Click:** Need to fix a quick timeout error? Easily extend your `memory_limit` and `max_execution_time` directly from the admin dashboard.
* **Role & Individual Storage Quotas:** Set absolute disk space limits (e.g., 500MB max storage) or single-file upload limits for specific user roles (Authors, Editors) or even custom individual users.
* **Concurrent Quota Protection:** Quota checks account for uploads that are still in progress, so overlapping uploads cannot skip a storage limit.
* **Media Storage Analytics:** A donut chart of Media Library storage, grouped as JPG, PNG, PDF, ZIP, SVG, and Others.
* **Smart Garbage Collection:** Automatic background cleanup of abandoned, failed, or expired upload sessions, keeping your server storage clean and optimized.
* **Admin-Only Scripts:** Settings and the chunk uploader load in wp-admin. The plugin does not add scripts or styles to the public site, and it does not send upload data to an external service.

---

### How It Works

1. **Server Override:** Where the host allows it, the plugin writes `upload_max_filesize`, `post_max_size`, `memory_limit`, and `max_execution_time` to `.htaccess` or `.user.ini`.
2. **Chunked Upload:** For a file above the chunk size, the browser slices it and the plugin receives each chunk, checks it against the user's quota, and stores it. After every chunk has arrived, the plugin merges the file and passes that complete file to WordPress.
3. **Usage Tracking:** Each attachment is recorded in `nexura_upload_logs`, with a short-lived cache, so quota checks do not rescan the uploads folder on every request.

---

### 🎯 Who Is This Plugin For?

* **WooCommerce Store Owners:** Easily import massive product CSV files or high-resolution product catalogs.
* **Photographers & Videographers:** Upload large RAW, PSD, or HD video files securely.
* **LMS Admins (LearnDash, TutorLMS):** Upload large course videos and PDF resources effortlessly.
* **Developers & Agencies:** Deploy large themes, plugin ZIPs, or site migration backups that exceed default limits.

---

### 🔐 Privacy & Security

This plugin does **not** collect, store, or transmit personal data to an external service. Quotas, logs, and temporary chunks stay in your WordPress database and on your server.

---

== Installation ==

1. Upload the `nexura-upload-limits-manager` directory to `/wp-content/plugins/`, or install directly from the WordPress Plugin Directory via **Plugins → Add New**.
2. Activate the plugin through the **Plugins** menu.
3. Navigate to **Upload Manager** in your WordPress admin sidebar.
4. Set your desired **Global Upload Limit**, **PHP Memory Limit**, and **Max Execution Time**.
5. Open the **Role-Based Limit** tab to set a quota for a role, or the **User Quotas** tab to set a quota for one user.
6. Simply upload your large files via the WordPress Media Library. The Chunked Engine will automatically activate for massive files!

== Frequently Asked Questions ==

= How does the Chunked Upload engine work? =
When a file is larger than the chunk size, Nexura splits it before upload. Each chunk is at most 5 MB, and smaller when that would exceed the server's post limit. The chunks are sent one by one. After the last chunk arrives, Nexura merges them into the original file and hands that file to WordPress.

= Can I upload a massive video file on cheap shared hosting? =
Chunking keeps each request small, so a low `upload_max_filesize` does not have to block the whole file. The upload can still fail if the host limits disk space, request time, or the web server body size (for example Nginx `client_max_body_size`).

= What happens if my internet disconnects during a huge upload? =
The plugin features retry mechanics and upload resume support. If the upload drops, you can often drag and drop the exact same file into the Media Library again to resume compatible uploads using the same upload session.

= How do Role-based Storage Quotas work? =
You can assign specific maximum file upload sizes and total storage quotas for each WordPress user role (e.g., Authors can upload a max of 10MB per file and are limited to 500MB of total storage). Once a user reaches their assigned quota, they cannot upload any more files until they free up space.

= What happens when I uninstall the plugin? =
Nexura follows strict WordPress clean-up standards. Upon uninstallation, it safely removes all its custom database tables, transient caches, user metadata, and temporary upload directories, leaving your server completely clean.

= Will this plugin slow down my website? =
The public site does not load this plugin's scripts or styles. Chunk uploads and quota checks run in wp-admin and on the logged-in upload request.

== Screenshots ==

1. **Upload Manager Dashboard:** View a complete audit of your WordPress PHP configuration and configure Global Limits.
2. **Media Storage Analytics:** Storage grouped as JPG, PNG, PDF, ZIP, SVG, and Others.
3. **User Quotas:** Assign specific maximum upload limits and storage caps for user roles or individual users.


== Changelog ==

= 1.0.6 =
* Images, videos, and other media still upload when the host PHP limit stays at 2 MB, including uploads from the block editor.
* Theme and plugin ZIP installs are sent in small parts, then handed to the normal WordPress installer.
* The default per-file limit is 512 MB until you save a different one, so chunking is not capped by the host's 2 MB limit.
* Each upload part stays at or below 512 KB, so a 1 MB Nginx or CDN body limit does not reject the file before PHP sees it.
* A theme or plugin package that is only documentation, not an installable zip, is rejected with a clear message.
* Image processing asks WordPress for the plugin memory limit through the core image_memory_limit filter.
* Storage Usage Analysis shows the real total in MB or GB, and lists WebP, AVIF, GIF, video, and audio separately.
* Role upload limits are checked again on the server, and Default shows the current global limit.
* Plugin Check nonce, input, and short-description warnings are cleared.

= 1.0.5 =
* Fix: Storage quotas now apply to normal Media Library uploads, not only chunked uploads.
* Fix: Activation now creates tables and schedules cleanup and disk-alert cron events.
* Fix: Chunked uploads send the file size to the server even when the media scripts load late, and the chunk size stays under post_max_size.
* Fix: A failed merge no longer sticks the upload in processing or holds the reserved quota.
* Fix: An Unlimited role is no longer overridden by another role's numeric quota. The dashboard shows custom user quotas.
* Fix: Recalculate Storage refreshes sizes without zeroing quotas first.
* Fix: Chunk files are stored outside the web root when the system temp directory is writable, and leftover temp folders are actually deleted.
* Fix: Uninstall removes the options this plugin saves.

= 1.0.4 =
* Fix: Addressed an edge-case fatal error related to improper `.htaccess` configuration values when selecting "Unlimited" upload size.
* Fix: Resolved a "headers already sent" PHP warning triggered under specific server conditions during background storage calculations.
* Enhancement: Hardened UI logic for safer limits modifications.

= 1.0.3 =
* Feature: Introduced the Smart Chunked Upload Engine. Helps work around per-request server upload limits by securely splitting and merging large files.
* Feature: Implemented True Deterministic Upload Resume and Network Retry mechanics (resume instantly even after page reloads).
* Feature: Added Database-level duplicate chunk protection (`nexura_upload_chunks`) to mathematically prevent concurrent chunk race conditions.
* Feature: Added Concurrent Quota Protection to prevent malicious users from bypassing their storage limits during multi-file uploads.
* Feature: Implemented comprehensive Garbage Collection to automatically clean up orphaned or failed temporary chunk sessions.
* Feature: Added Individual User Storage Quotas to set custom limits for specific users, overriding role-based limits.
* Feature: Added Smart 85% Warning Alerts with automatic reset logic when users free up disk space.
* Enhancement: Completely refactored plugin architecture for Enterprise-grade security and WordPress.org guideline compliance.
* Fix: Resolved multiple PHPCS (WordPress Coding Standards) warnings, UI alignment issues, and database upgrade path bugs.

= 1.0.2 =
* Fix: Prevented 500 Internal Server Error when writing to `.htaccess` on non-Apache PHP handlers.
* Fix: Role-based upload limits now properly apply the maximum allowed limit for users with multiple roles.
* Enhancement: Added admin notices to gracefully handle and warn users when `.htaccess` or `.user.ini` lacks write permissions.
* Enhancement: Refactored bulk media sync queries in Activity Logger.

= 1.0.1 =
* Expanded SEO metadata, tags, and description for improved WordPress.org discoverability.
* Enriched FAQ section with hosting-specific guidance and GDPR compliance notes.
* Rebranded plugin name and slug for WordPress.org repository compliance.

= 1.0.0 =
* Initial stable release.
* Global upload size control, PHP memory limit override, and execution time management.
* Role-based upload limit assignments.
* Real-time media storage analytics dashboard with visual chart breakdown.
