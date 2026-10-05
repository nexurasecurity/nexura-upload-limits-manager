<?php
/**
 * Server Status Check Class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nexura_Upload_Manager_Server_Status {

	/**
	 * Get WordPress Status details.
	 *
	 * @return array
	 */
	public static function get_wp_status() {
		global $wp_version;

		// WooCommerce active check
		$wc_version = 'Not Active';
		$wc_status = 'warning';
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_version = WC()->version;
			$wc_status = 'ok';
		}

		$wp_max_upload = wp_max_upload_size();

		return array(
			'wp_version' => array(
				'title'   => 'WordPress Version',
				'status'  => 'ok',
				'message' => $wp_version . ' - ok',
			),
			'wc_version' => array(
				'title'   => 'WooCommerce Version',
				'status'  => $wc_status,
				'message' => $wc_status === 'warning' ? 'Not Active WooCommerce<br><small>Recommend: 3.2</small>' : $wc_version . ' - ok',
			),
			'wp_max_upload' => array(
				'title'   => 'Maximum Upload Limit set by WordPress',
				'status'  => 'ok',
				'message' => size_format( $wp_max_upload ) . ' - ok',
			),
			'host_max_upload' => array(
				'title'   => 'Maximum Upload Limit Set By Hosting Provider',
				'status'  => 'ok',
				'message' => size_format( wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) ) ) . ' - ok',
			),
			'php_limit_time' => array(
				'title'   => 'PHP Limit Time',
				'status'  => 'ok',
				'message' => 'Current Limit Time: ' . ini_get( 'max_execution_time' ) . ' - ok',
			),
		);
	}

	/**
	 * Get Server & PHP Status details.
	 *
	 * @return array
	 */
	public static function get_server_status() {
		$status = array();

		// PHP Version
		$status['php_version'] = array(
			'title'   => 'PHP Version',
			'status'  => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'ok' : 'error',
			'message' => version_compare( PHP_VERSION, '7.4', '>=' ) ? PHP_VERSION . ' - ok' : PHP_VERSION . ' - Needs attention (7.4+ required)',
		);

		// Server Software
		$status['server_software'] = array(
			'title'   => 'Server Software',
			'status'  => 'ok',
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'message' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) . ' - ok' : 'Unknown',
		);

		// Get Target Options
		$target_upload = get_option( 'nexura_global_upload_limit', Nexura_Upload_Manager_Limits::DEFAULT_UPLOAD_LIMIT );
		if ( $target_upload === 'Unlimited' ) {
			$target_upload_bytes = PHP_INT_MAX;
		} else {
			$target_upload_bytes = wp_convert_hr_to_bytes( $target_upload );
		}

		$target_exec = get_option( 'nexura_max_execution_time', 120 );
		$target_memory = get_option( 'nexura_memory_limit', '512M' );
		$target_memory_bytes = wp_convert_hr_to_bytes( $target_memory );

		$current_memory_str = ini_get( 'memory_limit' );
		$current_memory_bytes = wp_convert_hr_to_bytes( $current_memory_str );
		$memory_status = ( $current_memory_bytes >= $target_memory_bytes || $current_memory_bytes <= 0 ) ? 'ok' : 'warning';

		// Memory Limit
		$status['memory_limit'] = array(
			'title'   => 'Memory Limit',
			'status'  => $memory_status,
			'message' => "Target: {$target_memory} | Effective: {$current_memory_str} " . ( $memory_status === 'ok' ? '- ok' : '<br><small>Lower than Target</small>' ),
		);

		// PHP Memory Usage
		$memory_usage = memory_get_usage(true);
		$php_memory_limit_str = ini_get( 'memory_limit' );
		$php_memory_limit = wp_convert_hr_to_bytes($php_memory_limit_str);
		if ($php_memory_limit <= 0) { $php_memory_limit = 256 * 1024 * 1024; } // fallback to 256MB if unlimited or undefined
		$status['php_memory_usage'] = array(
			'title'   => 'PHP Memory Usage',
			'status'  => 'ok',
			'message' => size_format($memory_usage) . ' used of limit ' . $php_memory_limit_str,
			'raw_used' => $memory_usage,
			'raw_total' => $php_memory_limit,
		);

		// System RAM Usage Attempt
		$system_ram_msg = 'Not available (blocked by hosting)';
		$ram_used = 0;
		$ram_total = 0;
		$disabled = explode( ',', ini_get( 'disable_functions' ) );
		$disabled = array_map( 'trim', $disabled );
		if ( function_exists('exec') && ! in_array( 'exec', $disabled, true ) ) {
			if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
				exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value', $output);
				if ( ! empty($output) ) {
					$free = 0; $total = 0;
					foreach ($output as $line) {
						if (strpos($line, 'FreePhysicalMemory=') !== false) {
							$free = (int) str_replace('FreePhysicalMemory=', '', $line) * 1024;
						}
						if (strpos($line, 'TotalVisibleMemorySize=') !== false) {
							$total = (int) str_replace('TotalVisibleMemorySize=', '', $line) * 1024;
						}
					}
					if ($total > 0) {
						$ram_used = $total - $free;
						$ram_total = $total;
						$percent = round(($ram_used / $total) * 100, 2);
						$system_ram_msg = size_format($ram_used) . ' used of ' . size_format($total) . ' (' . $percent . '%)';
					}
				}
			} else {
				if ( is_readable( '/proc/meminfo' ) ) {
					$meminfo = file_get_contents('/proc/meminfo');
					if ( $meminfo ) {
						$total = 0; $free = 0;
						if ( preg_match( '/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
							$total = (int) $matches[1] * 1024;
						}
						if ( preg_match( '/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
							$free = (int) $matches[1] * 1024;
						} elseif ( preg_match( '/MemFree:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
							$free = (int) $matches[1] * 1024;
						}
						if ( $total > 0 ) {
							$ram_used = $total - $free;
							$ram_total = $total;
							$percent = round(($ram_used / $total) * 100, 2);
							$system_ram_msg = size_format($ram_used) . ' used of ' . size_format($total) . ' (' . $percent . '%)';
						}
					}
				}
			}
		}

		$status['system_ram_usage'] = array(
			'title'   => 'Server-Reported RAM Capacity',
			'status'  => 'ok',
			'message' => $system_ram_msg,
			'raw_used' => $ram_used,
			'raw_total' => $ram_total,
		);

		// System Disk Usage
		$disk_total = function_exists( 'disk_total_space' ) ? disk_total_space( ABSPATH ) : false;
		$disk_free  = function_exists( 'disk_free_space' ) ? disk_free_space( ABSPATH ) : false;
		$disk_raw_used = 0;
		if ( $disk_total !== false && $disk_free !== false && $disk_total > 0 ) {
			$disk_used    = $disk_total - $disk_free;
			$disk_raw_used = $disk_used;
			$disk_percent = round( ( $disk_used / $disk_total ) * 100, 2 );
			$disk_status  = $disk_percent >= 90 ? 'warning' : 'ok';
			$status['system_disk_usage'] = array(
				'title'   => 'Detected Filesystem Capacity (Shared environments may vary)',
				'status'  => $disk_status,
				'message' => size_format( $disk_used ) . ' used out of ' . size_format( $disk_total ) . ' (' . $disk_percent . '%)',
				'raw_used' => $disk_raw_used,
				'raw_total' => $disk_total,
			);
		}

		// Database Size
		global $wpdb;
		$db_name = DB_NAME;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$db_size = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(data_length + index_length) as db_size FROM information_schema.TABLES WHERE table_schema = %s", $db_name ) );
		if ( $db_size > 0 ) {
			$status['database_size'] = array(
				'title'   => 'Database Disk Usage',
				'status'  => 'ok',
				'message' => size_format( $db_size ),
				'raw_used' => $db_size,
			);
		}

		// Server IP Address
		$server_ip = 'Unknown';
		if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
			$server_ip = sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) );
		} elseif ( isset( $_SERVER['LOCAL_ADDR'] ) ) {
			$server_ip = sanitize_text_field( wp_unslash( $_SERVER['LOCAL_ADDR'] ) );
		}
		if ( $server_ip === '::1' ) $server_ip = '127.0.0.1 (Localhost)';
		$status['server_ip'] = array(
			'title'   => 'Server IP Address',
			'status'  => 'ok',
			'message' => $server_ip,
		);

		// Bandwidth
		$status['bandwidth'] = array(
			'title'   => 'Bandwidth Usage',
			'status'  => 'ok',
			'message' => 'Not measurable via PHP (Check Host)',
		);

		// Max Execution Time
		$current_exec = (int) ini_get( 'max_execution_time' );
		$exec_status = ( $current_exec >= $target_exec || $current_exec === 0 ) ? 'ok' : 'warning';
		$status['max_execution_time'] = array(
			'title'   => 'Max Execution Time',
			'status'  => $exec_status,
			'message' => "Target: {$target_exec}s | Effective: {$current_exec}s " . ( $exec_status === 'ok' ? '- ok' : '<br><small>Lower than Target</small>' ),
		);

		// Max Input Vars
		$status['max_input_vars'] = array(
			'title'   => 'Max Input Vars',
			'status'  => 'ok',
			'message' => ini_get( 'max_input_vars' ) . ' - ok',
		);

		// Post Max Size
		$current_post_str = ini_get( 'post_max_size' );
		$current_post_bytes = wp_convert_hr_to_bytes( $current_post_str );
		$target_post_bytes = $target_upload_bytes === PHP_INT_MAX ? PHP_INT_MAX : $target_upload_bytes * 1.25;
		$post_status = ( $current_post_bytes >= $target_post_bytes || $current_post_bytes <= 0 ) ? 'ok' : 'warning';
		
		$status['post_max_size'] = array(
			'title'   => 'Post Max Size',
			'status'  => $post_status,
			'message' => "Target: " . ( $target_post_bytes === PHP_INT_MAX ? 'Unlimited' : size_format( $target_post_bytes ) ) . " | Effective: {$current_post_str} " . ( $post_status === 'ok' ? '- ok' : '<br><small>Host still reports this limit. Large uploads are sent in parts.</small>' ),
		);

		// Upload Max Filesize
		$current_upload_str = ini_get( 'upload_max_filesize' );
		$current_upload_bytes = wp_convert_hr_to_bytes( $current_upload_str );
		$upload_status = ( $current_upload_bytes >= $target_upload_bytes || $current_upload_bytes <= 0 ) ? 'ok' : 'warning';
		
		$status['upload_max_filesize'] = array(
			'title'   => 'Upload Max Filesize',
			'status'  => $upload_status,
			'message' => "Target: {$target_upload} | Effective: {$current_upload_str} " . ( $upload_status === 'ok' ? '- ok' : '<br><small>Host still reports this limit. Large uploads are sent in parts.</small>' ),
		);

		// Display Errors
		$display_errors = ini_get( 'display_errors' );
		$status['display_errors'] = array(
			'title'   => 'Display Errors',
			'status'  => ( $display_errors && strtolower( $display_errors ) !== 'off' ) ? 'warning' : 'ok',
			'message' => ( $display_errors && strtolower( $display_errors ) !== 'off' ) ? 'On<br><small>Needs attention</small>' : 'Off - ok',
		);

		// cURL
		$status['curl'] = array(
			'title'   => 'cURL Enabled',
			'status'  => function_exists( 'curl_version' ) ? 'ok' : 'warning',
			'message' => function_exists( 'curl_version' ) ? 'Yes - ok' : 'No - Optional',
		);

		// MBString
		$status['mbstring'] = array(
			'title'   => 'MBString Enabled',
			'status'  => extension_loaded( 'mbstring' ) ? 'ok' : 'warning',
			'message' => extension_loaded( 'mbstring' ) ? 'Yes - ok' : 'No - Optional',
		);

		// OpenSSL
		$status['openssl'] = array(
			'title'   => 'OpenSSL Enabled',
			'status'  => extension_loaded( 'openssl' ) ? 'ok' : 'warning',
			'message' => extension_loaded( 'openssl' ) ? 'Yes - ok' : 'No - Optional',
		);

		// Zip
		$status['zip'] = array(
			'title'   => 'Zip Enabled',
			'status'  => extension_loaded( 'zip' ) ? 'ok' : 'warning',
			'message' => extension_loaded( 'zip' ) ? 'Yes - ok' : 'No - Optional',
		);

		// DOM
		$status['dom'] = array(
			'title'   => 'DOM Enabled',
			'status'  => class_exists( 'DOMDocument' ) ? 'ok' : 'warning',
			'message' => class_exists( 'DOMDocument' ) ? 'Yes - ok' : 'No - Optional',
		);

		// GD Library
		$status['gd'] = array(
			'title'   => 'GD Library',
			'status'  => extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ? 'ok' : 'warning',
			'message' => extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ? 'Yes - ok' : 'No<br><small>Needs attention</small>',
		);

		// Fileinfo
		$status['fileinfo'] = array(
			'title'   => 'Fileinfo Enabled',
			'status'  => extension_loaded( 'fileinfo' ) ? 'ok' : 'warning',
			'message' => extension_loaded( 'fileinfo' ) ? 'Yes - ok' : 'No<br><small>Needs attention</small>',
		);

		// Allow URL fopen
		$status['allow_url_fopen'] = array(
			'title'   => 'Allow URL fopen',
			'status'  => ini_get( 'allow_url_fopen' ) ? 'ok' : 'warning',
			'message' => ini_get( 'allow_url_fopen' ) ? 'Enabled - ok' : 'Disabled<br><small>Needs attention</small>',
		);

		return $status;
	}
}
