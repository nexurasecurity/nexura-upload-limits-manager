<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Nexura_Upload_Limits_Manager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Drop Custom Tables
$nexura_tables = array(
	$wpdb->prefix . 'nexura_upload_logs',
	$wpdb->prefix . 'nexura_upload_sessions',
	$wpdb->prefix . 'nexura_upload_chunks'
);

foreach ( $nexura_tables as $nexura_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$nexura_table}`" );
}

// 2. Delete Options
$nexura_options = array(
	'nexura_global_upload_limit',
	'nexura_max_execution_time',
	'nexura_memory_limit',
	'nexura_global_execution_time',
	'nexura_global_memory_limit',
	'nexura_role_upload_limits',
	'nexura_role_storage_quotas',
	'nexura_sync_complete',
	'nexura_sync_offset',
	'nexura_sync_status',
	'nexura_sync_total',
	'nexura_sync_processed',
	'nexura_recalc_mode',
	'nexura_recalc_last_id',
	'nexura_stats_cache_version',
	'nexura_90_alert_sent',
	'nexura_db_version',
);

wp_clear_scheduled_hook( 'nexura_daily_disk_check' );
wp_clear_scheduled_hook( 'nexura_chunk_cleanup_event' );
wp_clear_scheduled_hook( 'nexura_sync_media_event' );

foreach ( $nexura_options as $nexura_option ) {
	delete_option( $nexura_option );
}

// 3. Delete Transients
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_nexura_storage_stats%'" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_nexura_storage_stats%'" );

// 4. Delete User Meta (Custom Storage Quotas & Alert Flags)
delete_metadata( 'user', 0, 'nexura_custom_storage_quota', '', true );
delete_metadata( 'user', 0, '_nexura_85_alert_sent', '', true );

// 5. Delete Temporary Chunk Directories
require_once plugin_dir_path( __FILE__ ) . 'includes/class-chunked-upload.php';

$nexura_temp_dirs = array();
$nexura_active    = Nexura_Chunked_Upload::get_temp_base_dir();
if ( $nexura_active ) {
	$nexura_temp_dirs[] = $nexura_active;
}
$nexura_upload_dir = wp_upload_dir();
if ( empty( $nexura_upload_dir['error'] ) ) {
	$nexura_temp_dirs[] = trailingslashit( $nexura_upload_dir['basedir'] ) . 'nexura-temp';
}

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
$nexura_fs = new WP_Filesystem_Direct( null );

foreach ( $nexura_temp_dirs as $nexura_temp_dir ) {
	if ( $nexura_temp_dir && file_exists( $nexura_temp_dir ) ) {
		$nexura_fs->rmdir( $nexura_temp_dir, true );
	}
}
