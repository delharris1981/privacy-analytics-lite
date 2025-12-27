# Changelog

All notable changes to Privacy-First Analytics Lite will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

## [Unreleased]

### Planned Features
- Export functionality for statistics
- Additional chart types
- REST API endpoints for external access
- The "Zero-PII" Heatmap

