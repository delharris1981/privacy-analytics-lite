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
		referrerDonut: null,
		device: null,
		os: null
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
		initExportButton();

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

		// Initialize Device Type Chart.
		initDeviceChart();

		// Initialize OS Chart.
		initOsChart();

		// Start polling for real-time updates (every 30 seconds).
		setInterval(fetchStats, 30000);

		// Check for update success flag.
		if (document.body.classList.contains('pa-just-updated')) {
			launchConfetti();
		}

		// Handle Modal Close
		const modalClose = document.getElementById('pa-modal-close');
		const modalOverlay = document.querySelector('.pa-modal-overlay');
		if (modalClose && modalOverlay) {
			modalClose.addEventListener('click', () => {
				modalOverlay.classList.remove('is-active');
			});
		}

		// Handle Notice Dismiss
		const dismissBtn = document.querySelector('.pa-premium-notice .notice-dismiss');
		if (dismissBtn) {
			dismissBtn.addEventListener('click', function () {
				const notice = this.closest('.pa-premium-notice');
				if (notice) {
					notice.style.display = 'none';
					// Use standard WP AJAX to dismiss if we wanted persistence, 
					// but for now local transient handling in PHP handles strict "one-time" show.
					// We can just hide it here.
				}
			});
		}

		// Handle What's New button
		const whatsNewBtn = document.getElementById('pa-whats-new-btn');
		if (whatsNewBtn && modalOverlay) {
			whatsNewBtn.addEventListener('click', (e) => {
				e.preventDefault();
				modalOverlay.classList.add('is-active');
			});
		}

		// Handle Tabs
		initTabs();

		// Handle Heatmap Toggles
		initHeatmapToggles();
	}

	/**
	 * Initialize Tab Navigation.
	 */
	function initTabs() {
		const tabs = document.querySelectorAll('.nav-tab');
		tabs.forEach(tab => {
			tab.addEventListener('click', (e) => {
				e.preventDefault();

				// Deactivate all
				document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
				document.querySelectorAll('.pa-tab-content').forEach(c => c.style.display = 'none');

				// Activate clicked
				tab.classList.add('nav-tab-active');
				const targetId = 'view-' + tab.getAttribute('data-tab');
				const target = document.getElementById(targetId);
				if (target) {
					target.style.display = 'block';
				}
			});
		});
	}

	/**
	 * Initialize Heatmap Toggles.
	 */
	function initHeatmapToggles() {
		const toggles = document.querySelectorAll('.pa-toggle-heatmap');
		toggles.forEach(btn => {
			btn.addEventListener('click', (e) => {
				e.preventDefault();
				const page = btn.getAttribute('data-page');
				const currentState = btn.getAttribute('data-state');

				btn.disabled = true;

				const params = new URLSearchParams({
					action: 'pa_toggle_heatmap',
					page: page,
					state: currentState
				});

				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: params
				})
					.then(response => response.json())
					.then(data => {
						btn.disabled = false;
						if (data.success) {
							// Toggle UI state locally
							const newState = data.data.new_state;
							btn.setAttribute('data-state', newState);

							// Update button text and class
							if (newState === 'on') {
								btn.textContent = 'Disable';
								btn.classList.remove('button-primary');
								btn.classList.add('button-secondary');
								// Update status label
								const statusCell = btn.closest('tr').querySelector('td:nth-child(2)');
								if (statusCell) {
									statusCell.innerHTML = '<span class="dashicons dashicons-yes" style="color: #00a32a;"></span> Active';
								}
							} else {
								btn.textContent = 'Enable';
								btn.classList.remove('button-secondary');
								btn.classList.add('button-primary');
								// Update status label
								const statusCell = btn.closest('tr').querySelector('td:nth-child(2)');
								if (statusCell) {
									statusCell.innerHTML = '<span class="dashicons dashicons-no-alt" style="color: #d63638;"></span> Inactive';
								}
							}
						} else {
							alert('Failed to update tracking setting.');
						}
					})
					.catch(err => {
						console.error(err);
						btn.disabled = false;
					});
			});
		});
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
	 * Initialize export report button.
	 */
	function initExportButton() {
		const exportBtn = document.getElementById('pa-export-report-btn');
		const exportPdfBtn = document.getElementById('pa-export-pdf-btn');

		if (exportBtn) {
			exportBtn.addEventListener('click', function () {
				if (typeof ajaxurl === 'undefined') return;

				// Construct export URL
				const params = new URLSearchParams({
					action: 'privacy_analytics_export_report',
					date_start: currentDateRange.start,
					date_end: currentDateRange.end
				});

				// Trigger download
				window.location.href = ajaxurl + '?' + params.toString();
			});
		}

		if (exportPdfBtn) {
			exportPdfBtn.addEventListener('click', function () {
				if (typeof ajaxurl === 'undefined') return;

				// Construct export URL
				const params = new URLSearchParams({
					action: 'privacy_analytics_export_pdf_report',
					date_start: currentDateRange.start,
					date_end: currentDateRange.end,
					nonce: pa_dashboard_params.export_pdf_nonce
				});

				// Trigger download
				window.location.href = ajaxurl + '?' + params.toString();
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
	 * Initialize Device Type chart.
	 */
	function initDeviceChart() {
		const chartElement = document.getElementById('pa-device-chart');
		if (!chartElement) {
			return;
		}

		const chartData = getChartData(chartElement);
		if (!chartData || !chartData.labels || chartData.labels.length === 0) {
			chartElement.innerHTML = '<p>No data available.</p>';
			return;
		}

		charts.device = new frappe.Chart(chartElement, {
			data: chartData,
			type: 'bar',
			height: 300,
			colors: ['#2271b1']
		});
	}

	/**
	 * Initialize Operating System chart.
	 */
	function initOsChart() {
		const chartElement = document.getElementById('pa-os-chart');
		if (!chartElement) {
			return;
		}

		const chartData = getChartData(chartElement);
		if (!chartData || !chartData.labels || chartData.labels.length === 0) {
			chartElement.innerHTML = '<p>No data available.</p>';
			return;
		}

		charts.os = new frappe.Chart(chartElement, {
			data: chartData,
			type: 'bar', // Using bar chart as there might be many OS versions
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
			date_end: currentDateRange.end,
			nonce: pa_dashboard_params.get_stats_nonce
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
		if (charts.device && data.device_stats && data.device_stats.chart_data) {
			charts.device.update(data.device_stats.chart_data);
		}
		if (charts.os && data.os_stats && data.os_stats.chart_data) {
			charts.os.update(data.os_stats.chart_data);
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

	/**
	 * Launch confetti celebration.
	 */
	function launchConfetti() {
		const colors = ['#2271b1', '#00a32a', '#f0c33c', '#d63638', '#72aee6'];
		for (let i = 0; i < 100; i++) {
			const confetti = document.createElement('div');
			confetti.className = 'pa-confetti';
			confetti.style.left = Math.random() * 100 + 'vw';
			confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
			confetti.style.width = Math.random() * 8 + 4 + 'px';
			confetti.style.height = confetti.style.width;
			confetti.style.animationDelay = Math.random() * 2 + 's';
			confetti.style.animationDuration = Math.random() * 2 + 2 + 's';
			document.body.appendChild(confetti);

			// Clean up
			setTimeout(() => confetti.remove(), 5000);
		}
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCharts);
	} else {
		initCharts();
	}
})();

