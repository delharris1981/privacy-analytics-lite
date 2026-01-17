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
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // For remote images if needed
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
        $daily_trends = $data['daily_trends'] ?? [];
        $top_pages = $data['top_pages'] ?? [];
        $referrers = $data['referrers'] ?? [];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <title>Privacy Analytics Report</title>
            <style>
                <?php echo $css; ?>
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
                <h2>Daily Trends (Last 30 Days)</h2>
                <?php $this->render_bar_chart($daily_trends, 'Date', 'Visits'); ?>
            </div>

            <div class="page-break"></div>

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
    private function render_bar_chart(array $data, string $x_axis, string $y_axis): void
    {
        if (empty($data['labels']) || empty($data['datasets'])) {
            echo '<p>No data for chart.</p>';
            return;
        }

        $labels = $data['labels'];
        // Assuming datasets[0] contains the primary metric (Hits/Views)
        $values = $data['datasets'][0]['values'] ?? [];
        $max_value = !empty($values) ? max($values) : 0;
        if ($max_value === 0)
            $max_value = 1; // Prevent division by zero

        echo '<div class="chart-container">';
        echo '<div class="chart-bars">';

        foreach ($values as $index => $value) {
            $height_pct = ($value / $max_value) * 100;
            // Ensure minimal visibility for non-zero values
            if ($value > 0 && $height_pct < 1)
                $height_pct = 1;

            $label = $labels[$index] ?? '';
            // Simplify label if too long (e.g. date)
            $short_label = substr($label, 0, 5);

            echo '<div class="bar-group">';
            echo '<div class="bar-value">' . number_format_i18n($value) . '</div>';
            echo '<div class="bar" style="height: ' . $height_pct . '%;"></div>';
            echo '<div class="bar-label">' . esc_html($short_label) . '</div>';
            echo '</div>';
        }

        echo '</div>'; // chart-bars
        echo '</div>'; // chart-container
    }

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
			
			.chart-container { margin: 20px 0; height: 200px; width: 100%; position: relative; border-bottom: 1px solid #ccc; padding-bottom: 20px; }
			.chart-bars { display: table; width: 100%; height: 100%; table-layout: fixed; }
			.bar-group { display: table-cell; vertical-align: bottom; height: 100%; padding: 0 2px; text-align: center; }
			.bar { background-color: #2271b1; width: 100%; min-height: 1px; display: block; margin: 0 auto; }
			.bar-value { font-size: 9px; color: #666; margin-bottom: 2px; }
			.bar-label { font-size: 9px; color: #666; margin-top: 5px; overflow: hidden; white-space: nowrap; }
			
			.page-break { page-break-after: always; }
			.footer { margin-top: 50px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
		';
    }
}
