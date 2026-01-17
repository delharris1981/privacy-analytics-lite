# Privacy-First Analytics Lite

A privacy-compliant, server-side analytics plugin for WordPress that tracks page views without storing any personally identifiable information (PII), using cookies, or requiring JavaScript.

Stable tag: 1.7.4
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

## Features

### Privacy-First Design
- **No PII Storage**: Never stores IP addresses or full user agents
- **Zero Cookies**: No cookie consent banner required
- **Anonymized Tracking**: Uses hashed visitor identifiers with 24-hour rotating salts
- **GDPR Compliant**: Designed to meet privacy regulations without consent banners

### Server-Side Tracking
- **PHP-Based**: Tracks page views server-side using WordPress hooks
- **No JavaScript Required**: Works even when JavaScript is disabled
- **Accurate Data**: Captures all page views, not just JavaScript-enabled visits

### Efficient Data Management
- **Aggregated Storage**: Raw hits are processed hourly into aggregated statistics
- **Minimal Database Impact**: Only stores aggregated data, keeping database size small
- **Automatic Pruning**: Raw hits are automatically cleaned up after aggregation

### Beautiful Dashboard
- **Visual Analytics**: Interactive charts powered by Frappe Charts
- **Date Filtering**: View statistics by Last 7, 30, 90 days, or custom date ranges
- **Key Metrics**: Total hits, unique visitors, top pages, and referral sources
- **Device Analytics**: Breakdown by device type (Mobile, Tablet, Desktop) and OS
- **Detailed Charts**: Hourly traffic, daily trends, device distribution, and more
- **Data Export**: Export your statistics as professional PDF certificates or CSV files
- **Easy to Use**: Clean, intuitive admin interface

## Requirements

- **PHP**: 8.2 or higher
- **WordPress**: 6.8 or higher
- **Composer**: For dependency management (autoloading)

## Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file
4. Click **Activate Plugin**

### Via Composer

```bash
composer require privacy-analytics-lite/privacy-analytics-lite
```

### Manual Installation

1. Clone or download this repository
2. Place the `privacy-analytics-lite` folder in your `wp-content/plugins/` directory
3. Run `composer install` in the plugin directory (if installing via Git)
4. Activate the plugin through the WordPress admin

## Usage

### Activation

1. Activate the plugin through the WordPress admin
2. The plugin will automatically create the necessary database tables
3. Tracking begins immediately upon activation

### Viewing Analytics

1. Navigate to **Privacy Analytics** in the WordPress admin menu
2. View your analytics dashboard with:
   - Summary statistics (total hits, unique visitors)
   - Daily trends chart
   - Top pages chart and table
   - Referral sources chart and table

### Exporting Reports

1. Click the **Export CSV** button to download a raw data spreadsheet
2. Click the **Export PDF** button to generate a professional, branded analytics report for the current date range

### How It Works

1. **Tracking**: When a visitor views a page, the plugin tracks the visit server-side
2. **Anonymization**: Visitor data is hashed using a 24-hour rotating salt
3. **Aggregation**: Raw hits are processed hourly into aggregated statistics
4. **Display**: The dashboard queries only aggregated data for fast performance

## Privacy Features

### What We Track
- Page paths (e.g., `/about`, `/contact`)
- Referral sources (normalized, e.g., "Google", "Twitter")
- Device types and Operating Systems (aggregated)
- Hit counts and unique visitor counts (anonymized)

### What We Don't Track
- Personal information
- Cookies or local storage

### Data Retention
- Raw hits are aggregated hourly and then deleted
- Only aggregated statistics are retained
- Data is grouped by date, page path, and referrer

## Technical Details

### Architecture
- **PHP 8.2+**: Uses modern PHP features (readonly classes, match expressions)
- **PSR-4 Autoloading**: Organized class structure
- **WordPress Standards**: Follows WordPress Coding Standards (WPCS)
- **OOP Design**: Object-oriented architecture with dependency injection

### Database Tables
- `{prefix}pa_hits_temp`: Temporary storage for raw hits (pruned after aggregation)
- `{prefix}pa_daily_stats`: Aggregated statistics by date, page, and referrer

### Cron Jobs
- Hourly aggregation task processes raw hits into statistics
- Automatically scheduled on plugin activation
- Automatically unscheduled on plugin deactivation

## Exclusions

The plugin automatically excludes:
- Logged-in administrators
- 404 pages
- Bot user agents (Googlebot, Bingbot, etc.)

## Performance

- **Minimal Overhead**: Server-side tracking adds negligible load time
- **Efficient Queries**: Dashboard queries only aggregated data
- **Optimized Storage**: Database size remains small through aggregation

### PDF Generation Security
- **Hardened Dompdf**: The PDF engine is configured in a restricted mode to prevent Server-Side Request Forgery (SSRF). 
- **No Remote Loading**: Fetching external images, fonts, or stylesheets is disabled.
- **No Scripting**: PHP execution and scripting within PDFs are explicitly disabled.

## Development

### Project Structure

```
privacy-analytics-lite/
├── src/
│   ├── Admin/          # Dashboard UI
│   ├── Core/           # Plugin orchestration
│   ├── Database/       # Table management & aggregation
│   ├── Models/         # Data transfer objects
│   └── Tracking/       # Tracking logic
├── assets/
│   ├── css/            # Admin styles
│   └── js/             # Dashboard JavaScript
├── composer.json
└── privacy-analytics-lite.php
```

### Code Standards
- PHP 8.2+ with strict types
- WordPress Coding Standards (WPCS)
- PSR-4 autoloading
- PHPDoc documentation

## Limitations

- **WordPress Cron**: Aggregation runs on WordPress cron, which requires site traffic
- **Cache Compatibility**: May require REST API fallback for heavily cached sites

## Support

For issues, feature requests, or contributions, please visit the plugin repository.

## License

GPL v2 or later

## Credits

Built with:
- [Frappe Charts](https://frappe.io/charts) for data visualization
- WordPress core APIs
- Modern PHP 8.2+ features

