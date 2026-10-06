<?php
/**
 * Plugin Name:       Nexura Upload Limits Manager – Increase Maximum Upload File Size
 * Plugin URI:        https://wordpress.org/plugins/nexura-upload-limits-manager
 * Description:       Increase maximum upload file size, PHP memory, and execution time. Large images, videos, files, themes, and plugins upload in parts when the host limit is 2 MB.
 * Version:           1.0.7
 * Author:            Nexura Security
 * Author URI:        https://nexurasecurity.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nexura-upload-limits-manager
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Tested up to:      7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'NEXURA_UPLOAD_MANAGER_VERSION', '1.0.7' );
define( 'NEXURA_UPLOAD_MANAGER_FILE', __FILE__ );
define( 'NEXURA_UPLOAD_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXURA_UPLOAD_MANAGER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
class Nexura_Upload_Manager {

	/**
	 * Instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-server-status.php';
		require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-upload-limits.php';
		require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-activity-logger.php';
		require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-chunked-upload.php';
		
		if ( is_admin() ) {
			require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/class-admin-menu.php';
		}
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'nexura_daily_disk_check', array( 'Nexura_Upload_Manager_Limits', 'check_disk_space' ) );
		// Run DB migration check directly since we are already inside plugins_loaded.
		$this->maybe_upgrade();
		// Sites activated before 1.0.5 never scheduled these events.
		$this->maybe_schedule_events();

		// Initialize Large File Chunking Engine.
		Nexura_Chunked_Upload::init();
	}

	/**
	 * Schedule cron events when they are missing.
	 */
	public function maybe_schedule_events() {
		if ( ! wp_next_scheduled( 'nexura_daily_disk_check' ) ) {
			wp_schedule_event( time(), 'daily', 'nexura_daily_disk_check' );
		}
		if ( ! wp_next_scheduled( 'nexura_chunk_cleanup_event' ) ) {
			wp_schedule_event( time(), 'daily', 'nexura_chunk_cleanup_event' );
		}
	}

	/**
	 * Check and run database migrations.
	 */
	public function maybe_upgrade() {
		$current_db_version = get_option( 'nexura_db_version', '1.0.0' );
		if ( version_compare( $current_db_version, NEXURA_UPLOAD_MANAGER_VERSION, '<' ) ) {
			require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-activity-logger.php';
			
			// 1.0.4 DB Migration: Clean up duplicate attachment_id logs before applying UNIQUE KEY
			if ( version_compare( $current_db_version, '1.0.4', '<' ) ) {
				global $wpdb;
				$logs_table = $wpdb->prefix . 'nexura_upload_logs';
				
				// Ensure table exists before querying
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs_table ) ) === $logs_table ) {
					// Delete older duplicate records, keeping the one with the highest ID. Table name is not a value placeholder.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->query( "DELETE t1 FROM {$logs_table} t1 INNER JOIN {$logs_table} t2 WHERE t1.attachment_id = t2.attachment_id AND t1.id < t2.id" );
				}
			}

			Nexura_Upload_Activity_Logger::create_tables();
			update_option( 'nexura_db_version', NEXURA_UPLOAD_MANAGER_VERSION );
		}
	}
}

/**
 * Runs on plugin activation.
 * Registered from the main file so it fires during activation, when plugins_loaded has already passed.
 *
 * @since 1.0.0
 */
function nexura_upload_manager_activate() {
	require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-activity-logger.php';
	Nexura_Upload_Activity_Logger::create_tables();
	update_option( 'nexura_db_version', NEXURA_UPLOAD_MANAGER_VERSION );
	if ( ! wp_next_scheduled( 'nexura_daily_disk_check' ) ) {
		wp_schedule_event( time(), 'daily', 'nexura_daily_disk_check' );
	}
	if ( ! wp_next_scheduled( 'nexura_chunk_cleanup_event' ) ) {
		wp_schedule_event( time(), 'daily', 'nexura_chunk_cleanup_event' );
	}
}

/**
 * Runs on plugin deactivation.
 * Removes server configuration overrides (.htaccess / .user.ini).
 *
 * @since 1.0.1
 */
function nexura_upload_manager_deactivate() {
	require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-upload-limits.php';
	Nexura_Upload_Manager_Limits::remove_server_config();
	wp_clear_scheduled_hook( 'nexura_daily_disk_check' );
	wp_clear_scheduled_hook( 'nexura_chunk_cleanup_event' );
	wp_clear_scheduled_hook( 'nexura_sync_media_event' );
}

register_activation_hook( NEXURA_UPLOAD_MANAGER_FILE, 'nexura_upload_manager_activate' );
register_deactivation_hook( NEXURA_UPLOAD_MANAGER_FILE, 'nexura_upload_manager_deactivate' );

// Initialize the plugin.
function nexura_upload_manager_init() {
	Nexura_Upload_Manager::get_instance();
}
add_action( 'plugins_loaded', 'nexura_upload_manager_init' );
