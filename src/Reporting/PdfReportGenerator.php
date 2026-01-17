<?php
/**
 * PDF Report Generator.
 *
 * @package PrivacyAnalytics\Lite\Reporting
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Reporting;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Class PdfReportGenerator
 */
class PdfReportGenerator
{
    /**
     * Generate PDF report.
     *
     * @param array<string, mixed> $data Report data.
     * @return string Binary PDF content.
     */
    public function generate(array $data): string
    {
        // Recursively sanitize all input data to prevent injection/traversal/XSS.
        array_walk_recursive($data, function (&$item) {
            if (is_string($item)) {
                // Remove path traversal markers.
                $item = str_replace(['..', './', '..\\'], '', $item);
                // Mitigate XSS by escaping HTML entities at the input level as well.
                $item = htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
            }
        });

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false); // Disabled for security (SSRF prevention)
        $options->set('isPhpEnabled', false);    // Ensure PHP execution is disabled
        $options->set('isJavascriptEnabled', false); // Disable JavaScript for security
        $options->set('chroot', PRIVACY_ANALYTICS_LITE_PLUGIN_DIR); // Restrict filesystem access (Path Traversal prevention)
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $html = $this->get_html($data);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output() ?: '';
    }

    /**
     * Generate HTML content for the PDF.
     *
     * @param array<string, mixed> $data Report data.
     * @return string HTML content.
     */
    private function get_html(array $data): string
    {
        $css = $this->get_css();
        $logo_url = ''; // Add logo logic if needed in future

        $date_start = $data['date_start'] ?? '';
        $date_end = $data['date_end'] ?? '';
        $summary = $data['summary'] ?? [];
        $top_pages = $data['top_pages'] ?? [];
        $referrers = $data['referrers'] ?? [];
        $devices = $data['devices'] ?? [];
        $os = $data['os'] ?? [];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <title>Privacy Analytics Report</title>
            <style>
                <?php
                // Internal static CSS - safe for output.
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $css;
                ?>
            </style>
        </head>

        <body>
            <div class="header">
                <h1>Privacy Analytics Report</h1>
                <div class="meta">
                    Generated on
                    <?php echo date('Y-m-d H:i:s'); ?><br>
                    Period:
                    <?php echo esc_html($date_start . ' to ' . $date_end); ?>
                </div>
            </div>

            <div class="section">
                <h2>Summary Statistics</h2>
                <table class="summary-table">
                    <tr>
                        <th>Total Hits</th>
                        <td>
                            <?php echo number_format_i18n($summary['total_hits'] ?? 0); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Unique Visitors</th>
                        <td>
                            <?php echo number_format_i18n($summary['unique_visitors'] ?? 0); ?>
                        </td>
                    </tr>
                </table>
            </div>


            <div class="section">
                <h2>Top Pages</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Page Path</th>
                            <th>Views</th>
                            <th>Visitors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_pages['table_data'])): ?>
                            <?php foreach ($top_pages['table_data'] as $row): ?>
                                <tr>
                                    <td class="col-path">
                                        <?php echo esc_html($row['page_path']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['total_hits']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['total_visitors']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No data available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2>Referral Sources</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Views</th>
                            <th>Visitors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($referrers['table_data'])): ?>
                            <?php foreach ($referrers['table_data'] as $row): ?>
                                <tr>
                                    <td class="col-source">
                                        <?php echo esc_html($row['source'] ?: 'Direct'); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['total_hits']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['total_visitors']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No data available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </table>
            </div>

            <div class="page-break"></div>

            <div class="section">
                <h2>Device Types</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device Type</th>
                            <th>Views</th>
                            <th>Visitors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($devices['table_data'])): ?>
                            <?php foreach ($devices['table_data'] as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html(ucfirst($row['type'])); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['hits']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['visitors']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No data available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2>Operating Systems</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Views</th>
                            <th>Visitors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($os['table_data'])): ?>
                            <?php foreach ($os['table_data'] as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($row['os']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['hits']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format_i18n($row['visitors']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No data available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer">
                <p>Generated by Privacy Analytics Lite</p>
            </div>
        </body>

        </html>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Render a CSS-based bar chart.
     *
     * @param array  $data   Chart data from dashboard format.
     * @param string $x_axis X-axis label.
     * @param string $y_axis Y-axis label.
     * @return void
     */
    /**
     * Get CSS styles.
     *
     * @return string CSS styles.
     */
    private function get_css(): string
    {
        return '
			body { font-family: Helvetica, sans-serif; color: #333; line-height: 1.4; font-size: 12px; }
			h1 { font-size: 24px; color: #2271b1; margin-bottom: 20px; border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
			h2 { font-size: 18px; color: #1d2327; margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
			.header { margin-bottom: 30px; }
			.meta { color: #666; font-size: 11px; }
			
			table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
			th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
			th { font-weight: bold; color: #555; background-color: #f9f9f9; }
			.summary-table th { width: 200px; }
			
			.data-table th, .data-table td { font-size: 11px; }
			.col-path { word-wrap: break-word; max-width: 300px; }
			
			.page-break { page-break-after: always; }
			.footer { margin-top: 50px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
		';
    }
}
