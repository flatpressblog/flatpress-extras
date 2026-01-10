<?php
/**
 * Plugin Name: Crypto Top 10
 * Type: Block
 * Version: 2.0.0
 * Plugin URI: https://www.flatpress.org
 * Author: macadoum
 * Author URI: https://www.flatpress.org
 * Description: Displays top 10 cryptocurrencies by market cap with interactive price charts using CoinGecko API
 * Requires: FlatPress 1.5 RC or later, Smarty 5.5+
 */

/**
 * Enqueue plugin assets (CSS, JS)
 * Compatible with FlatPress 1.5 RC and later
 */
function plugin_crypto_top10_head() {
	$random_hex = RANDOM_HEX;
	$pdir = plugin_geturl('crypto-top10');
	
	// Build asset URLs with version handling for cache busting
	$chartjs_raw = $pdir . 'assets/chart.min.js';
	$css_raw = $pdir . 'assets/crypto-top10.css';
	$js_raw = $pdir . 'assets/crypto-top10.js';
	
	// Get version for cache busting
	$version = defined('SYSTEM_VER') ? SYSTEM_VER : '2.0.0';
	
	// Use utils_asset_ver() if available (FlatPress 1.5+), otherwise fallback
	if (function_exists('utils_asset_ver')) {
		$chartjs = utils_asset_ver($chartjs_raw, $version);
		$css = utils_asset_ver($css_raw, $version);
		$js = utils_asset_ver($js_raw, $version);
	} else {
		// Fallback for older FlatPress versions - use plugin version
		$ver = rawurlencode($version);
		$chartjs = $chartjs_raw . '?v=' . $ver;
		$css = $css_raw . '?v=' . $ver;
		$js = $js_raw . '?v=' . $ver;
	}
	
	// Enqueue Chart.js from local file
	echo '<script nonce="' . $random_hex . '" src="' . htmlspecialchars($chartjs, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
	
	// Enqueue plugin CSS
	echo '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '">' . "\n";
	
	// Enqueue plugin JS
	echo '<script nonce="' . $random_hex . '" src="' . htmlspecialchars($js, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
	
	// Pass localized strings to JavaScript
	$lang = lang_load('plugin:crypto-top10');
	
	// Properly encode language strings for JavaScript
	$langData = array(
		'title' => $lang['plugin']['crypto-top10']['title'],
		'select' => $lang['plugin']['crypto-top10']['select'],
		'loading' => $lang['plugin']['crypto-top10']['loading'],
		'unavailable' => $lang['plugin']['crypto-top10']['unavailable'],
		'errorList' => $lang['plugin']['crypto-top10']['error_list'],
		'errorPrice' => $lang['plugin']['crypto-top10']['error_price']
	);
	
	echo '<script nonce="' . $random_hex . '">
		window.cryptoTop10Lang = ' . json_encode($langData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';
	</script>' . "\n";
}

/**
 * Widget function - renders the crypto top 10 widget
 * Compatible with Smarty 5.5+ and FlatPress 1.5 RC
 */
function plugin_crypto_top10_widget() {
	global $smarty;
	
	// Load plugin strings
	$lang = lang_load('plugin:crypto-top10');
	
	$entry = array();
	$entry['subject'] = $lang['plugin']['crypto-top10']['title'];
	
	// Assign variables for template (Smarty 5.5+ compatible)
	$smarty->assign('plugin_dir', plugin_geturl('crypto-top10'));
	
	// Fetch template content using plugin namespace syntax (without .tpl extension)
	$entry['content'] = $smarty->fetch('plugin:crypto-top10/widget');
	
	return $entry;
}

// Hook into wp_head to enqueue assets
add_action('wp_head', 'plugin_crypto_top10_head', 1);

// Register the widget
register_widget('crypto-top10', 'Crypto Top 10', 'plugin_crypto_top10_widget');
?>
