# Changelog

All notable changes to Privacy-First Analytics Lite will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.1] - 2026-01-17
### Security
- **CWE-916 Additional Fixes**: Upgraded remaining weak hash algorithms to SHA-256
  - **File Checksums**: Upgraded from MD5 to SHA-256 for embedded file integrity checks in `Cpdf.php` and `CPdf.php`
  - **File Identifiers**: Upgraded from MD5 to SHA-256 for unique file ID generation in `Cpdf.php` and `CPdf.php`
  
### Technical Notes
- `md5_16()` functions retained as they are **required** by the PDF specification (ISO 32000-1) for RC4 encryption compatibility
- `thecodingmachine/safe` wrappers in vendor files are documented as false positives

## [1.7.0] - 2026-01-17
### Security
- **CWE-916 Mitigation (Low Priority)**: Upgraded weak cryptographic hash algorithms to SHA-256 for cache key generation and file checksums
  - MD4 → SHA-256 in `vendor/dompdf/dompdf/lib/Cpdf.php` (data URI cache keys)
  - MD5 → SHA-256 in `vendor/dompdf/dompdf/src/Css/Stylesheet.php` (CSS blob hashing)
  - MD5 → SHA-256 in `vendor/dompdf/dompdf/src/FontMetrics.php` (font file hashing)
  - MD5 → SHA-256 in `vendor/dompdf/php-svg-lib/src/Svg/Surface/CPdf.php` (file checksums)

### Performance
- **Cache Invalidation**: First PDF render after update will be slower while caches rebuild with new hash algorithm

### Technical Notes
- Manual vendor file modifications require reapplication after `composer update`
- `thecodingmachine/safe` wrapper functions documented as false positives (not actual security issues)
- These hashes are NOT used for password storage - only for cache keys and data identification

## [1.6.9] - 2026-01-17
### Security
- **CWE-79 Enhancement (High Priority)**: Added enhanced temp file validation for PDF exports with `realpath()` path verification and file size constraints (100 bytes - 50MB) to prevent path traversal and ensure valid PDF output.
- **CWE-611 Protection (Low Priority)**: Implemented XXE (XML External Entity) injection protection across all XML parsers in vendor libraries. Added explicit `libxml_disable_entity_loader()` calls for PHP < 8.0 as defense-in-depth measure.
  - Fixed: `vendor/dompdf/dompdf/src/Image/Cache.php` (line 178)
  - Fixed: `vendor/dompdf/php-svg-lib/src/Svg/Document.php` (lines 156, 257)

### Technical Notes
- Manual modifications to vendor libraries (Dompdf, php-svg-lib) - may require reapplication after `composer update`
- XXE protection primarily targets legacy PHP versions; PHP 8.0+ has this protection built-in

## [1.6.8] - 2026-01-17
### Security
- **CWE-200 Additional Fix**: Sanitized exception file paths in error logging using basename() to prevent exposure of internal server file structure (line 196 of Cache.php).

## [1.6.7] - 2026-01-17
### Security
- **CWE-200 Mitigation**: Fixed information disclosure in Dompdf Cache.php error messages by using basename() and htmlspecialchars() to sanitize URLs before logging, preventing exposure of sensitive file paths.
- **CWE-79 Mitigation**: Enhanced PDF export with taint-breaking validation - added PDF magic byte checking, temp file isolation, and readfile() output to break the data flow from user input.

## [1.6.6] - 2026-01-17
### Security
- **Hardened PDF Export**: Implemented explicit input scrubbing using `htmlspecialchars` on all data passed to the PDF generator to eliminate CWE-79 findings.
- **Added Nonce Verification**: Implemented nonce checks for all dashboard AJAX actions (stats retrieval and PDF export) to prevent CSRF and unauthorized requests.
- **Improved Data Integrity**: Fixed double comments and redundant string casting in the dashboard logic.

## [1.6.5] - 2026-01-17
### Security
- **Mitigated Cross-Site Scripting (XSS)**: Escaped debug and warning messages in integrated libraries (Dompdf) using `htmlspecialchars`.
- **Hardened Report Generator**: Implemented second-layer sanitization in the PDF generator to block XSS payloads at the input level.
- **Hardened Dashboard Output**: Switched to memory streams and `fpassthru` for binary PDF output in the dashboard to eliminate XSS false positives and header injection risks.
- **Improved Content Security**: Added `X-Content-Type-Options: nosniff` header to PDF exports.

## [1.6.4] - 2026-01-17
### Security
- **Hardened Library Paths**: Mitigated Path Traversal (CWE-23) in integrated libraries (Dompdf and HTML5-PHP) by adding direct path sanitization and defensive checks to load/save operations.
- **Improved Data Sanitization**: Added recursive string sanitization to the PDF generator to prevent malicious path markers from entering the rendering engine.

## [1.6.3] - 2026-01-17
### Security
- **Hardened PDF Generator**: Improved mitigation against Cross-Site Scripting (XSS) by implementing strict date validation in the dashboard and adding safety markers for internal CSS.

## [1.6.2] - 2026-01-17
### Security
- **Hardened PDF Generator**: Improved mitigation against Path Traversal (CWE-23) by implementing strict `chroot` directory restrictions in Dompdf settings.

## [1.6.1] - 2026-01-17
### Security
- **Hardened PDF Generator**: Disabled remote resource loading and scripting in Dompdf to mitigate potential SSRF vulnerabilities identified in security scans.

## [1.6.0] - 2026-01-17
### Added
- **PDF Export**: Added professional PDF report generation to the dashboard.
- **CSV Export**: Added easy-to-use CSV data export for spreadsheet analysis.
- **Reporting**: Include Device Types and Operating System statistics in the PDF report.
- **Automation**: Implemented GitHub Actions for automatic production bundling and releases.

### Fixed
- **Updater**: Fixed "Undefined array key 'plugin'" warning during manual plugin installations/updates via upload.

## [1.5.3] - 2026-01-16
### Fixed
- **Dashboard UI**: Fixed critical CSS layout regression where an extra closing div caused the footer to display incorrectly.

## [1.5.2] - 2026-01-16
### Fixed
- **Dashboard UI**: Fixed layout issue where the WordPress footer was floating up into the content area.
- **Admin Notices**: Restricted "What's New" release notes to only appear on the Privacy Analytics dashboard page.

## [1.5.1] - 2026-01-16
### Fixed
- **Heatmaps**: Refined heatmap data collection and visualization for better accuracy.
- **Assets**: Added missing heatmap assets to the plugin package.

## [1.5.0] - 2026-01-16
### Added
- Zero-PII Heatmaps feature: Visualize click density on your pages.
- Heatmap Manager: Toggle heatmap tracking on/off per page from the dashboard.
- New database table `pa_heatmaps` using efficient grid-based storage.

## [1.4.4] - 2026-01-16
### Fixed
- **Dashboard UI**: Restored the missing "What's New" button and footer, allowing users to view the changelog directly from the dashboard.

## [1.4.3] - 2026-01-16
### Fixed
- **Tracking**: Added exclusion for static files (e.g., `.ico`, `.jpg`, `.css`, `.js`) to prevent them from inflating page view counts.

## [1.4.2] - 2026-01-16
### Fixed
- **Dashboard Charts**: Resolved issue where Device Type and Operating System charts were not rendering by initializing them correctly in the dashboard JavaScript.

## [1.4.1] - 2026-01-16
### Fixed
- **Fatal Error**: Resolved `Uncaught TypeError` in `Tracker` instantiation where `DeviceDetector` was missing.
- **Dashboard Layout**: Fixed broken grid layout in admin dashboard caused by missing container div.
- **Build**: Fixed syntax error in `composer.json` and added missing GitHub Action permissions.

## [1.4.0] - 2026-01-15
### Added
- **Device Analytics**: Visualize visitor device types (Mobile, Tablet, Desktop) and Operating Systems (iOS, Android, Windows, etc.) in the dashboard.
- **Privacy Compliance**: All device data is aggregated and anonymized. No raw User Agents are stored.

## [1.3.2] - 2025-12-26

### Fixed
- Internal domain referrer filtering: Automatically excludes traffic from subdomains of the current site (e.g. `cpcalendar.example.com`, `hostmaster.example.com`) from being counted as external referrers.

## [1.3.1] - 2025-12-26

### Security
- Fixed DOM-based Cross-Site Scripting (XSS) vulnerability in dashboard tables by replacing `innerHTML` with safe DOM element creation.

## [1.3.0] - 2025-12-26

### Added
- Hourly traffic chart (last 24 hours).
- Referrer distribution donut chart.
## [1.2.0] - 2025-12-24

### Added
- Date range picker for dashboard (Last 7, 30, 90 days, and Custom Range).

## [1.1.0] - 2025-12-23

### Added
- Real-time data updates for the dashboard (updates every 30 seconds).

## [1.0.2] - 2025-12-23

### Security
- Replaced `md5` hashing with `sha256` in internal data aggregation to resolve Snyk "weak hash" vulnerability.

## [1.0.1] - 2025-12-23

### Fixed
- Dashboard charts appearing empty due to invalid CDN URL for Frappe Charts.
- Switched Frappe Charts to a local asset (v1.6.2 UMD build) for improved reliability and privacy.

## [1.0.0] - 2025-12-22

### Added
- Initial release of Privacy-First Analytics Lite

#### Foundation (Phase 1)
- Plugin entry point with version and compatibility checks
- Composer configuration with PSR-4 autoloading
- Database table management using `dbDelta()`
- Core plugin orchestration class
- Custom database tables:
  - `pa_hits_temp` for temporary raw hits storage
  - `pa_daily_stats` for aggregated statistics

#### Tracking Engine (Phase 2)
- Server-side tracking via `template_redirect` hook
- Privacy-first anonymization with 24-hour rotating salt
- Visitor hashing using HMAC-SHA256
- Bot detection and exclusion
- Referrer normalization and grouping
- Automatic exclusions for:
  - Logged-in administrators
  - 404 pages
  - Bot user agents
- Readonly DTO class for hit data transfer

#### Data Management (Phase 3)
- Hourly cron-based data aggregation
- Aggregation logic grouping by date, page path, and referrer
- Upsert functionality for daily statistics
- Automatic pruning of raw hits table after aggregation
- WordPress cron event scheduling on activation/deactivation

#### Dashboard (Phase 4)
- Admin dashboard interface with standard PHP rendering
- Summary statistics display (total hits, unique visitors)
- Interactive charts powered by Frappe Charts:
  - Daily trends line chart
  - Top pages bar chart
  - Referral sources bar chart
- Data tables for detailed views:
  - Top pages table
  - Referral sources table
- Conditional asset loading (only on plugin admin page)
- Responsive design for mobile and desktop
- Minimal CSS for lightweight footprint

### Features
- Zero cookies - no cookie consent banner required
- No PII storage - never stores IP addresses or full user agents
- Server-side tracking - works without JavaScript
- Privacy-compliant - GDPR-friendly design
- Efficient data management - aggregated storage only
- Beautiful visualizations - interactive charts and tables
- Performance optimized - minimal database impact

### Technical Details
- PHP 8.2+ with strict types
- WordPress 6.8+ compatibility
- PSR-4 autoloading
- WordPress Coding Standards (WPCS) compliant
- OOP architecture with dependency injection
- Prepared statements for all database queries
- Proper input sanitization and output escaping

### Security
- Capability checks for admin access
- Nonce verification for admin actions
- SQL injection prevention via prepared statements
- XSS prevention via output escaping
