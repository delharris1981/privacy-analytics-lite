/**
 * Admin Dashboard JavaScript
 *
 * @package PrivacyAnalytics\Lite
 */

(function () {
	'use strict';

	// Store chart instances.
	const charts = {
		dailyTrends: null,
		topPages: null,
		referrer: null
	};

	/**
	 * Initialize all charts when DOM is ready.
	 */
	function initCharts() {
		// Check if Frappe Charts is available.
		if (typeof frappe === 'undefined' || !frappe.Chart) {
			console.error('Frappe Charts library not loaded');
			return;
		}

		// Initialize Daily Trends Chart (Line Chart).
		initDailyTrendsChart();

		// Initialize Top Pages Chart (Bar Chart).
		initTopPagesChart();

		// Initialize Referrer Chart (Bar Chart).
		initReferrerChart();

		// Start polling for real-time updates (every 30 seconds).
		setInterval(fetchStats, 30000);
	}

	/**
	 * Initialize daily trends line chart.
	 */
	function initDailyTrendsChart() {
		const chartElement = document.getElementById('pa-daily-trends-chart');
		if (!chartElement) {
			return;
		}

		const chartData = getChartData(chartElement);
		if (!chartData || !chartData.labels || chartData.labels.length === 0) {
			chartElement.innerHTML = '<p>No data available for the selected period.</p>';
			return;
		}

		charts.dailyTrends = new frappe.Chart(chartElement, {
			data: chartData,
			type: 'line',
			height: 300,
			colors: ['#2271b1', '#00a32a'],
			axisOptions: {
				xIsSeries: true
			},
			lineOptions: {
				regionFill: 1
			}
		});
	}

	/**
	 * Initialize top pages bar chart.
	 */
	function initTopPagesChart() {
		const chartElement = document.getElementById('pa-top-pages-chart');
		if (!chartElement) {
			return;
		}

		const chartData = getChartData(chartElement);
		if (!chartData || !chartData.labels || chartData.labels.length === 0) {
			chartElement.innerHTML = '<p>No data available.</p>';
			return;
		}

		charts.topPages = new frappe.Chart(chartElement, {
			data: chartData,
			type: 'bar',
			height: 300,
			colors: ['#2271b1']
		});
	}

	/**
	 * Initialize referrer sources chart.
	 */
	function initReferrerChart() {
		const chartElement = document.getElementById('pa-referrer-chart');
		if (!chartElement) {
			return;
		}

		const chartData = getChartData(chartElement);
		if (!chartData || !chartData.labels || chartData.labels.length === 0) {
			chartElement.innerHTML = '<p>No data available.</p>';
			return;
		}

		// Use bar chart for referrers (more readable than pie for many sources).
		charts.referrer = new frappe.Chart(chartElement, {
			data: chartData,
			type: 'bar',
			height: 300,
			colors: ['#00a32a']
		});
	}

	/**
	 * Get chart data from data attribute.
	 *
	 * @param {HTMLElement} element Chart container element.
	 * @return {Object|null} Chart data object or null.
	 */
	function getChartData(element) {
		const dataAttr = element.getAttribute('data-chart-data');
		if (!dataAttr) {
			return null;
		}

		try {
			return JSON.parse(dataAttr);
		} catch (e) {
			console.error('Failed to parse chart data:', e);
			return null;
		}
	}

	/**
	 * Fetch latest stats from the server.
	 */
	function fetchStats() {
		if (typeof ajaxurl === 'undefined') {
			return;
		}

		const params = new URLSearchParams({
			action: 'privacy_analytics_get_stats'
		});

		fetch(ajaxurl + '?' + params.toString())
			.then(response => response.json())
			.then(response => {
				if (response.success && response.data) {
					updateDashboard(response.data);
				}
			})
			.catch(error => console.error('Error fetching stats:', error));
	}

	/**
	 * Update dashboard with new data.
	 *
	 * @param {Object} data New stats data.
	 */
	function updateDashboard(data) {
		// Update Summary Stats
		if (data.summary_stats) {
			const totalHitsEl = document.querySelector('.pa-stat-card:nth-child(1) .pa-stat-value');
			const uniqueVisitorsEl = document.querySelector('.pa-stat-card:nth-child(2) .pa-stat-value');

			// We need a helper to format numbers to match PHP's number_format_i18n (simplified here for JS)
			const formatNumber = (num) => new Intl.NumberFormat().format(num);

			if (totalHitsEl) totalHitsEl.textContent = formatNumber(data.summary_stats.total_hits);
			if (uniqueVisitorsEl) uniqueVisitorsEl.textContent = formatNumber(data.summary_stats.unique_visitors);
		}

		// Update Charts
		if (charts.dailyTrends && data.daily_trends) {
			charts.dailyTrends.update(data.daily_trends);
		}
		if (charts.topPages && data.top_pages && data.top_pages.chart_data) {
			charts.topPages.update(data.top_pages.chart_data);
		}
		if (charts.referrer && data.referrer_stats && data.referrer_stats.chart_data) {
			charts.referrer.update(data.referrer_stats.chart_data);
		}
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCharts);
	} else {
		initCharts();
	}
})();

