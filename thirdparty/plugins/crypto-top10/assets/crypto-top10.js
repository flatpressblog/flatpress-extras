/**
 * Crypto Top 10 Plugin JavaScript
 * Fetches cryptocurrency data from CoinGecko API and displays interactive charts
 */

(function($) {
	'use strict';
	
	// CoinGecko API endpoints (no API key required)
	const COINGECKO_API = 'https://api.coingecko.com/api/v3';
	const TOP10_ENDPOINT = `${COINGECKO_API}/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false`;
	const HISTORY_ENDPOINT = `${COINGECKO_API}/coins/{id}/market_chart?vs_currency=usd&days=7`;
	const AJAX_TIMEOUT = 10000; // 10 seconds timeout
	
	// Global variables
	let chart = null;
	let cryptoList = [];
	let currentCoinId = null;
	
	/**
	 * Initialize the plugin when document is ready
	 */
	$(document).ready(function() {
		initWidget();
	});
	
	/**
	 * Initialize the widget
	 */
	function initWidget() {
		const container = $('#crypto-top10-widget');
		if (!container.length) {
			return;
		}
		
		// Build the widget HTML structure
		buildWidgetHTML(container);
		
		// Fetch and display top 10 cryptocurrencies
		fetchTop10Cryptos();
	}
	
	/**
	 * Build the widget HTML structure
	 */
	function buildWidgetHTML(container) {
		const html = `
			<div class="crypto-select-container">
				<label for="crypto-select">${window.cryptoTop10Lang.select}:</label>
				<select id="crypto-select" class="crypto-select">
					<option value="">${window.cryptoTop10Lang.loading}...</option>
				</select>
			</div>
			<div id="crypto-error" class="crypto-error" style="display: none;"></div>
			<div id="crypto-chart-container" class="crypto-chart-container">
				<canvas id="crypto-chart"></canvas>
			</div>
		`;
		container.html(html);
		
		// Bind change event to dropdown
		$('#crypto-select').on('change', function() {
			const coinId = $(this).val();
			if (coinId) {
				fetchAndDisplayChart(coinId);
			}
		});
	}
	
	/**
	 * Fetch top 10 cryptocurrencies from CoinGecko
	 */
	function fetchTop10Cryptos() {
		showError('');
		
		// Check if mock data should be used
		if (window.USE_MOCK_DATA && window.MOCK_CRYPTO_LIST) {
			cryptoList = window.MOCK_CRYPTO_LIST;
			populateDropdown(window.MOCK_CRYPTO_LIST);
			fetchAndDisplayChart(window.MOCK_CRYPTO_LIST[0].id);
			return;
		}
		
		$.ajax({
			url: TOP10_ENDPOINT,
			method: 'GET',
			dataType: 'json',
			timeout: AJAX_TIMEOUT,
			success: function(data) {
				if (data && Array.isArray(data) && data.length > 0) {
					cryptoList = data;
					populateDropdown(data);
					// Load chart for the first coin by default
					fetchAndDisplayChart(data[0].id);
				} else {
					showError(window.cryptoTop10Lang.errorList);
				}
			},
			error: function(xhr, status, error) {
				console.error('Error fetching crypto list:', error);
				if (status === 'timeout') {
					showError(window.cryptoTop10Lang.errorList + ' (timeout)');
				} else {
					showError(window.cryptoTop10Lang.errorList);
				}
			}
		});
	}
	
	/**
	 * Populate the dropdown with cryptocurrency options
	 */
	function populateDropdown(coins) {
		const select = $('#crypto-select');
		select.empty();
		
		coins.forEach(function(coin) {
			const option = $('<option></option>')
				.val(coin.id)
				.text(`${coin.name} (${coin.symbol.toUpperCase()})`);
			select.append(option);
		});
		
		// Select the first coin
		if (coins.length > 0) {
			select.val(coins[0].id);
			currentCoinId = coins[0].id;
		}
	}
	
	/**
	 * Fetch price history and display chart
	 */
	function fetchAndDisplayChart(coinId) {
		if (!coinId) {
			return;
		}
		
		currentCoinId = coinId;
		showError('');
		
		// Check if mock data should be used
		if (window.USE_MOCK_DATA && window.MOCK_PRICE_DATA && window.MOCK_PRICE_DATA[coinId]) {
			displayChart(window.MOCK_PRICE_DATA[coinId], coinId);
			return;
		}
		
		const url = HISTORY_ENDPOINT.replace('{id}', coinId);
		
		$.ajax({
			url: url,
			method: 'GET',
			dataType: 'json',
			timeout: AJAX_TIMEOUT,
			success: function(data) {
				if (data && data.prices && Array.isArray(data.prices) && data.prices.length > 0) {
					displayChart(data.prices, coinId);
				} else {
					showError(window.cryptoTop10Lang.errorPrice);
				}
			},
			error: function(xhr, status, error) {
				console.error('Error fetching price data:', error);
				if (status === 'timeout') {
					showError(window.cryptoTop10Lang.unavailable + ' (timeout)');
				} else {
					showError(window.cryptoTop10Lang.unavailable);
				}
			}
		});
	}
	
	/**
	 * Display the price chart using Chart.js
	 */
	function displayChart(priceData, coinId) {
		const ctx = document.getElementById('crypto-chart');
		if (!ctx) {
			return;
		}
		
		// Destroy existing chart if present
		if (chart) {
			chart.destroy();
		}
		
		// Prepare data for Chart.js
		const labels = priceData.map(function(point) {
			const date = new Date(point[0]);
			return date.toLocaleDateString();
		});
		
		const prices = priceData.map(function(point) {
			return point[1];
		});
		
		// Get coin name for tooltip
		const coin = cryptoList.find(function(c) {
			return c.id === coinId;
		});
		const coinName = coin ? coin.name : coinId;
		
		// Create chart
		chart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: coinName,
					data: prices,
					borderColor: '#4CAF50',
					backgroundColor: 'rgba(76, 175, 80, 0.1)',
					borderWidth: 2,
					fill: true,
					tension: 0.4,
					pointRadius: 3,
					pointHoverRadius: 5,
					pointBackgroundColor: '#4CAF50',
					pointBorderColor: '#fff',
					pointBorderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				aspectRatio: 1.5,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						enabled: true,
						mode: 'index',
						intersect: false,
						callbacks: {
							label: function(context) {
								let label = context.dataset.label || '';
								if (label) {
									label += ': ';
								}
								if (context.parsed.y !== null) {
									label += '$' + context.parsed.y.toFixed(2);
								}
								return label;
							}
						}
					}
				},
				scales: {
					x: {
						display: false
					},
					y: {
						display: false
					}
				},
				interaction: {
					mode: 'nearest',
					axis: 'x',
					intersect: false
				}
			}
		});
	}
	
	/**
	 * Show error message
	 */
	function showError(message) {
		const errorDiv = $('#crypto-error');
		if (message) {
			errorDiv.text(message).show();
		} else {
			errorDiv.hide();
		}
	}
	
})(jQuery);
