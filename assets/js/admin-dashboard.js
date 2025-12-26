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
		hourly: null,
		topPages: null,
		referrer: null,
		referrerDonut: null
	};

	// State for date range
	let currentDateRange = {
		start: '',
		end: '',
		mode: '30' // 7, 30, 90, or 'custom'
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

		// Initialize UI controls
		initDateControls();

		// Initialize Daily Trends Chart (Line Chart).
		initDailyTrendsChart();

		// Initialize Hourly Chart (Bar Chart).
		initHourlyChart();

		// Initialize Top Pages Chart (Bar Chart).
		initTopPagesChart();

		// Initialize Referrer Chart (Bar Chart).
		initReferrerChart();

		// Initialize Referrer Donut Chart.
		initReferrerDonutChart();

		// Start polling for real-time updates (every 30 seconds).
		setInterval(fetchStats, 30000);
	}

	/**
	 * Initialize date picker controls.
	 */
	function initDateControls() {
		const selector = document.getElementById('pa-date-range-selector');
		const customInputs = document.getElementById('pa-custom-date-inputs');
		const applyBtn = document.getElementById('pa-date-apply');
		const startDateInput = document.getElementById('pa-date-start');
		const endDateInput = document.getElementById('pa-date-end');

		if (!selector) return;

		// Initialize state from server-rendered values if possible.
		if (startDateInput && endDateInput) {
			currentDateRange.start = startDateInput.value;
			currentDateRange.end = endDateInput.value;
		}

		// Selector change handler
		selector.addEventListener('change', function () {
			const value = this.value;
			currentDateRange.mode = value;

			if (value === 'custom') {
				customInputs.style.display = 'flex';
			} else {
				customInputs.style.display = 'none';
				// Calculate dates for presets
				const end = new Date();
				const start = new Date();
				start.setDate(end.getDate() - (parseInt(value) - 1));

				const formatDate = (d) => d.toISOString().split('T')[0];

				currentDateRange.end = formatDate(end);
				currentDateRange.start = formatDate(start);

				// Update inputs for consistency
				if (startDateInput) startDateInput.value = currentDateRange.start;
				if (endDateInput) endDateInput.value = currentDateRange.end;

				// Fetch new stats
				fetchStats();
			}
		});

		// Apply button handler
		if (applyBtn) {
			applyBtn.addEventListener('click', function () {
				if (startDateInput && endDateInput) {
					currentDateRange.start = startDateInput.value;
					currentDateRange.end = endDateInput.value;
					fetchStats();
				}
			});
		}
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
	 * Initialize hourly chart.
	 */
	function initHourlyChart() {
		const chartElement = document.getElementById('pa-hourly-chart');
		if (!chartElement) {
			return;
		}

		const chartData = getChartData(chartElement);
		if (!chartData || !chartData.labels || chartData.labels.length === 0) {
			chartElement.innerHTML = '<p>No data available for the last 24 hours.</p>';
			return;
		}

		charts.hourly = new frappe.Chart(chartElement, {
			data: chartData,
			type: 'bar',
			height: 300,
			colors: ['#2271b1']
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
	 * Initialize referrer donut chart.
	 */
	function initReferrerDonutChart() {
		const chartElement = document.getElementById('pa-referrer-donut-chart');
		if (!chartElement) {
			return;
		}

		const chartData = getChartData(chartElement);
		if (!chartData || !chartData.labels || chartData.labels.length === 0) {
			chartElement.innerHTML = '<p>No data available.</p>';
			return;
		}

		charts.referrerDonut = new frappe.Chart(chartElement, {
			data: chartData,
			type: 'donut',
			height: 300,
			colors: ['#00a32a', '#2271b1', '#d63638', '#f0c33c', '#3582c4', '#646970'] // WordPress core colors
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
			if (typeof dataAttr === 'object') return dataAttr;
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
			action: 'privacy_analytics_get_stats',
			date_start: currentDateRange.start,
			date_end: currentDateRange.end
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
		if (charts.hourly && data.hourly_stats) {
			charts.hourly.update(data.hourly_stats);
		}
		if (charts.topPages && data.top_pages && data.top_pages.chart_data) {
			charts.topPages.update(data.top_pages.chart_data);
		}
		if (charts.referrer && data.referrer_stats && data.referrer_stats.chart_data) {
			charts.referrer.update(data.referrer_stats.chart_data);
		}
		if (charts.referrerDonut && data.referrer_stats && data.referrer_stats.chart_data) {
			charts.referrerDonut.update(data.referrer_stats.chart_data);
		}

		// Update Tables
		if (data.top_pages && data.top_pages.table_data) {
			updateTable('.pa-tables-grid .pa-table-container:nth-child(1) tbody', data.top_pages.table_data, ['page_path', 'total_hits', 'total_visitors']);
		}

		if (data.referrer_stats && data.referrer_stats.table_data) {
			updateTable('.pa-tables-grid .pa-table-container:nth-child(2) tbody', data.referrer_stats.table_data, ['source', 'total_hits', 'total_visitors']);
		}
	}

	/**
	 * Helper to update table body.
	 * @param {string} selector CSS selector for tbody
	 * @param {Array} data Array of row objects
	 * @param {Array} fields Array of field keys
	 */
	function updateTable(selector, data, fields) {
		const tbody = document.querySelector(selector);
		if (!tbody) return;

		// Clear existing content
		while (tbody.firstChild) {
			tbody.removeChild(tbody.firstChild);
		}

		if (!data || data.length === 0) {
			const row = document.createElement('tr');
			const cell = document.createElement('td');
			cell.colSpan = 3;
			cell.textContent = 'No data available.';
			row.appendChild(cell);
			tbody.appendChild(row);
			return;
		}

		const formatNumber = (num) => new Intl.NumberFormat().format(num);

		data.forEach(rowData => {
			const row = document.createElement('tr');

			// Col 1: Label/Source
			const cell1 = document.createElement('td');
			const val1 = rowData[fields[0]] || (fields[0] === 'source' ? 'Direct' : '');
			cell1.textContent = val1;
			row.appendChild(cell1);

			// Col 2: Hits
			const cell2 = document.createElement('td');
			const val2 = rowData[fields[1]] || 0;
			cell2.textContent = formatNumber(val2);
			row.appendChild(cell2);

			// Col 3: Unique Visitors
			const cell3 = document.createElement('td');
			const val3 = rowData[fields[2]] || 0;
			cell3.textContent = formatNumber(val3);
			row.appendChild(cell3);

			tbody.appendChild(row);
		});
	}

	/**
	 * Simple HTML escape
	 */
	function escapeHtml(text) {
		if (!text) return '';
		return text
			.toString()
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#039;");
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCharts);
	} else {
		initCharts();
	}
})();

