# Contributing to Nexura Upload Limits Manager

Thank you for your interest in contributing to **Nexura Upload Limits Manager**! We welcome bug reports, feature requests, code contributions, and documentation improvements.

## Code Standards

* All PHP code must adhere to [WordPress Coding Standards (WPCS)](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
* Ensure security sanitization (`sanitize_text_field`, `absint`, `wp_nonce_field`) and escaping (`esc_html`, `esc_attr`) are strictly applied.
* Test with PHP 7.4 through PHP 8.3 and WordPress 5.6 through latest.

## How to Submit Changes

1. Fork the repository on GitHub.
2. Create a feature branch: `git checkout -b feature/my-new-feature`.
3. Commit your changes: `git commit -m 'Add new feature'`.
4. Push to the branch: `git push origin feature/my-new-feature`.
5. Submit a Pull Request targeting the `main` branch.
