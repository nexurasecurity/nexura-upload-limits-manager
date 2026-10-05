# Nexura Upload Limits Manager

<p align="center">
  <a href="https://wordpress.org/plugins/nexura-upload-limits-manager/">
    <img src="https://img.shields.io/badge/WORDPRESS.ORG-DOWNLOAD%20FREE-0073aa.svg?style=for-the-badge&logo=wordpress&logoColor=white" alt="WordPress.org Download Free">
  </a>
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/nexura-upload-limits-manager/"><img src="https://img.shields.io/badge/REQUIRES%20WP-5.6%2B-464646.svg?style=flat-square&logo=wordpress" alt="Requires WP"></a>
  <a href="https://wordpress.org/plugins/nexura-upload-limits-manager/"><img src="https://img.shields.io/badge/REQUIRES%20PHP-7.4%2B-777bb4.svg?style=flat-square&logo=php" alt="Requires PHP"></a>
  <a href="https://wordpress.org/plugins/nexura-upload-limits-manager/"><img src="https://img.shields.io/badge/TESTED%20UP%20TO-7.1-00d084.svg?style=flat-square&logo=wordpress" alt="Tested Up To"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/LICENSE-GPLv2-007acc.svg?style=flat-square" alt="License GPLv2"></a>
  <br>
  <a href="https://github.com/nexurasecurity/nexura-upload-limits-manager/actions"><img src="https://img.shields.io/badge/CI-passing-2ea44f.svg?style=flat-square&logo=githubactions&logoColor=white" alt="CI Status"></a>
  <a href="https://github.com/nexurasecurity/nexura-upload-limits-manager/stargazers"><img src="https://img.shields.io/github/stars/nexurasecurity/nexura-upload-limits-manager?style=flat-square&color=007acc" alt="Stars"></a>
  <a href="https://github.com/nexurasecurity/nexura-upload-limits-manager/network/members"><img src="https://img.shields.io/github/forks/nexurasecurity/nexura-upload-limits-manager?style=flat-square&color=007acc" alt="Forks"></a>
  <a href="https://github.com/nexurasecurity/nexura-upload-limits-manager/issues"><img src="https://img.shields.io/github/issues/nexurasecurity/nexura-upload-limits-manager?style=flat-square&color=2ea44f" alt="Issues"></a>
</p>

<p align="center">
  <b>Increase maximum upload file size, PHP memory, execution time, and storage quotas for roles and individual users in one ultra-fast, native WordPress plugin.</b>
</p>

---

## 📌 Table of Contents
- [Overview](#-overview)
- [Key Features](#-key-features)
- [How It Works](#-how-it-works)
- [Target Use Cases](#-target-use-cases)
- [Installation](#-installation)
- [Configuration & Usage](#-configuration--usage)
- [Frequently Asked Questions (FAQ)](#-frequently-asked-questions-faq)
- [Changelog](#-changelog)
- [Privacy & Security](#-privacy--security)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🚀 Overview

**Nexura Upload Limits Manager** is an enterprise-grade WordPress plugin designed to solve hosting upload restrictions once and for all. By introducing a smart client-side **Chunked Upload Engine**, Nexura allows WordPress users to effortlessly upload large files (videos, high-res photos, themes, plugins, backups, CSVs) even on hosts with restrictive 2 MB server upload limits.

Additionally, Nexura gives administrators full control over PHP limits (`upload_max_filesize`, `post_max_size`, `memory_limit`, `max_execution_time`) and introduces granular **Role-Based** and **Individual User Storage Quotas**.

---

## ⚡ Key Features

- 🧩 **Chunked Upload Engine:** Slices massive files into micro-chunks (under 512 KB) to bypass server limits (`upload_max_filesize`, `post_max_size`, Nginx `client_max_body_size`).
- 🔄 **Deterministic Upload Resume & Retry:** Drops in connection will not corrupt your uploads; automatically retries and resumes seamlessly right where it left off.
- ⚡ **One-Click PHP Limit Overrides:** Increase `upload_max_filesize`, `memory_limit`, and `max_execution_time` directly via `.htaccess` or `.user.ini` from the WordPress dashboard.
- 👥 **Role & User Storage Quotas:** Enforce per-role or per-user storage caps (e.g., max 500 MB storage for Authors) and per-file size restrictions.
- 🔒 **Concurrent Quota Protection:** Atomic quota checks prevent simultaneous uploads from bypassing user storage limits.
- 📊 **Media Storage Analytics:** Interactive dashboard chart breaking down Media Library usage by file types (JPG, PNG, WebP, PDF, ZIP, SVG, Video, Audio).
- 🧹 **Automatic Garbage Collection:** Daily background cleanup purges abandoned or expired chunk files to preserve server disk space.
- 🛡️ **Zero Admin Bloat:** Ultra-lightweight footprint. Zero scripts or styles are loaded on the public front-end.

---

## 🛠️ How It Works

1. **Server-Level Configuration Overrides:** When permitted by your host, Nexura automatically updates `.htaccess` or `.user.ini` to elevate `upload_max_filesize`, `post_max_size`, `memory_limit`, and `max_execution_time`.
2. **Micro-Chunk Slicing Engine:** Files exceeding default server limits are sliced into small chunks on the client side. The chunks are transmitted individually to `admin-ajax.php`, verified against quota limits, and stored in a secure temporary directory.
3. **Stream Merging & Media Registration:** Once all chunks arrive, Nexura reassembles the complete file in stream mode, passes it to WordPress `media_handle_sideload()`, and registers it in the Media Library.
4. **Cached Usage Tracking:** Custom database tables (`wp_nexura_upload_logs`) keep fast, non-blocking logs of attachment usage without re-scanning disk folders on every request.

---

## 🎯 Target Use Cases

- **WooCommerce Merchants:** Import massive CSV catalog files and high-res product photos without timeout errors.
- **Course Creators & LMS Owners:** Upload large MP4 videos, course ZIP bundles, and HD PDFs cleanly.
- **Photographers & Video Editors:** Upload high-resolution RAW, PNG, PSD, and 4K video assets effortlessly.
- **Developers & Site Managers:** Upload heavy site backups, custom themes, and large plugin packages directly in `wp-admin`.

---

## 💻 Installation

### Method 1: WordPress Dashboard
1. Go to **Plugins > Add New** in your WordPress Admin area.
2. Search for `Nexura Upload Limits Manager`.
3. Click **Install Now** and then **Activate**.

### Method 2: Manual FTP / Zip Upload
1. Download the latest plugin ZIP file from [WordPress.org](https://wordpress.org/plugins/nexura-upload-limits-manager/) or [GitHub Releases](https://github.com/nexurasecurity/nexura-upload-limits-manager/releases).
2. Unzip and upload the `nexura-upload-limits-manager` directory to your server's `/wp-content/plugins/` folder.
3. Activate the plugin via **Plugins > Installed Plugins** in WordPress.

---

## ⚙️ Configuration & Usage

1. Navigate to **Upload Manager** from the WordPress admin sidebar.
2. Under **Global Limits**, set your desired Global Upload File Size, PHP Memory Limit, and Max Execution Time.
3. Under **Role-Based Limits**, assign custom storage caps and maximum upload sizes per user role.
4. Under **User Quotas**, set custom limits for specific individual users.
5. Use the **WordPress Media Library** as normal — chunking will automatically trigger for large files!

---

## ❓ Frequently Asked Questions (FAQ)

<details>
<summary><b>How does the Chunked Upload engine bypass host limits?</b></summary>
<p>Nexura slices large files into tiny parts (under 512 KB each) on the browser side. Each part is uploaded separately. Because each individual part is far smaller than host limits, server firewalls and Nginx limits do not reject the upload.</p>
</details>

<details>
<summary><b>Will this work on cheap shared hosting with a 2 MB upload limit?</b></summary>
<p>Yes! Nexura was specifically architected to handle restrictive shared hosts. Files up to several gigabytes can be uploaded in parts, as long as your host has enough total disk space to store the file.</p>
</details>

<details>
<summary><b>What happens if my connection drops mid-upload?</b></summary>
<p>Nexura features built-in network retry and resume logic. If a connection fails, Nexura automatically retries. If you re-upload the same file later, it resumes from the last successfully received chunk.</p>
</details>

<details>
<summary><b>Does this plugin affect front-end website speed?</b></summary>
<p>Not at all. Nexura only loads its assets inside <code>wp-admin</code>. Front-end page speeds and core web vitals remain completely untouched.</p>
</details>

---

## 📜 Changelog

### Version 1.0.6 (Latest)
- **Enhanced Block Editor Uploads:** Seamless chunking support for block editor media uploads on 2 MB limit hosts.
- **Plugin & Theme Package Chunking:** Large plugin and theme ZIP installs now upload in chunks before handing over to the core installer.
- **Nginx Body Limit Compatibility:** Enforced sub-512 KB chunk size to ensure compatibility with strict Nginx `client_max_body_size` and CDN rules.
- **Storage Analytics Upgrade:** Added WebP, AVIF, GIF, Video, and Audio file breakdown in storage analytics.

### Version 1.0.5
- **Storage Quota Fixes:** Standardized quota tracking across all Media Library upload methods.
- **Automated DB Migrations & Cron:** Improved database schema updates and cron scheduling.

---

## 🔒 Privacy & Security

Nexura Upload Limits Manager adheres strictly to privacy standards:
- ❌ **No External Tracking:** No telemetry, user data, or upload content is ever transmitted to external servers.
- 🔐 **Local Processing:** All chunks, temporary files, quotas, and logs stay entirely on your server and inside your WordPress database.
- 🧹 **Automatic Data Purging:** Uninstallation safely removes custom database tables, options, and temporary directories.

---

## 🤝 Contributing

We welcome contributions! Please refer to our [Contributing Guidelines](CONTRIBUTING.md) and follow our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## 📄 License

This project is licensed under the **GNU General Public License v2.0 or later** - see the [LICENSE](LICENSE) file for details.

Developed with ❤️ by [Nexura Security](https://nexurasecurity.com/).
