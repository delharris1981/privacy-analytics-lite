/**
 * Admin Dashboard JavaScript
 *
 * @package PrivacyAnalytics\Lite
 */

(function() {
	'use strict';

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

		new frappe.Chart(chartElement, {
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

		new frappe.Chart(chartElement, {
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
		new frappe.Chart(chartElement, {
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

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCharts);
	} else {
		initCharts();
	}
})();

