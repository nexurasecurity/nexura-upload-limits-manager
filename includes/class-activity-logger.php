<?php
/**
 * Upload Activity & Storage Logger.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nexura_Upload_Activity_Logger {

	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'log_upload' ) );
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'log_upload_footprint' ), 10, 2 );
		add_action( 'delete_attachment', array( __CLASS__, 'remove_log' ) );
		// Clear storage stats cache when media changes.
		add_action( 'add_attachment', array( __CLASS__, 'clear_stats_cache' ) );
		add_action( 'delete_attachment', array( __CLASS__, 'clear_stats_cache' ) );
		// Background sync event
		add_action( 'nexura_sync_media_event', array( __CLASS__, 'sync_existing_media_event' ) );
	}

	/**
	 * Create custom DB table.
	 */
	public static function create_tables() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nexura_upload_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			attachment_id bigint(20) NOT NULL,
			file_name varchar(255) NOT NULL,
			file_size bigint(20) NOT NULL,
			file_type varchar(100) NOT NULL,
			category varchar(50) NOT NULL,
			upload_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attachment_id (attachment_id),
			KEY category (category)
		) $charset_collate;
		
		CREATE TABLE {$wpdb->prefix}nexura_upload_sessions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			file_name varchar(255) NOT NULL,
			expected_size bigint(20) unsigned NOT NULL,
			received_size bigint(20) unsigned NOT NULL DEFAULT 0,
			total_chunks int(11) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'uploading',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY user_id (user_id)
		) $charset_collate;
		
		CREATE TABLE {$wpdb->prefix}nexura_upload_chunks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			chunk_index int(11) NOT NULL,
			chunk_size int(11) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_chunk (session_id, chunk_index)
		) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$statements = array_filter( array_map( 'trim', explode( ';', $sql ) ) );
		foreach ( $statements as $statement ) {
			dbDelta( $statement . ';' );
		}
	}

	/**
	 * Get file category (Video, Images, Audio, Archives).
	 */
	private static function get_category( $mime_type ) {
		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			return 'Images';
		} elseif ( strpos( $mime_type, 'video/' ) === 0 ) {
			return 'Video';
		} elseif ( strpos( $mime_type, 'audio/' ) === 0 ) {
			return 'Audio';
		} elseif ( in_array( $mime_type, array( 'application/zip', 'application/x-gzip', 'application/x-tar', 'application/x-rar-compressed' ), true ) ) {
			return 'Archives';
		}
		return 'Others';
	}

	/**
	 * Calculate total disk usage for an attachment (original + generated thumbnails).
	 *
	 * @param int $attachment_id
	 * @return int Total bytes.
	 */
	public static function calculate_attachment_disk_usage( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return 0;
		}

		$files = array( $file );
		$dirname = dirname( $file );
		
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_info ) {
				if ( ! empty( $size_info['file'] ) ) {
					$files[] = $dirname . '/' . $size_info['file'];
				}
			}
		}

		// Include scaled or original image if present in metadata
		if ( ! empty( $metadata['original_image'] ) ) {
			$files[] = $dirname . '/' . $metadata['original_image'];
		}
		
		$files = array_unique( $files );
		
		$total_size = 0;
		foreach ( $files as $path ) {
			if ( file_exists( $path ) ) {
				$total_size += filesize( $path );
			}
		}

		return $total_size;
	}

	/**
	 * Update attachment storage log after metadata generation to include thumbnails.
	 *
	 * @param array $metadata
	 * @param int   $attachment_id
	 * @return array
	 */
	public static function log_upload_footprint( $metadata, $attachment_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nexura_upload_logs';
		
		$total_size = self::calculate_attachment_disk_usage( $attachment_id );
		
		if ( $total_size > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( 
				$table_name, 
				array( 'file_size' => $total_size ), 
				array( 'attachment_id' => $attachment_id ), 
				array( '%d' ), 
				array( '%d' ) 
			);
			
			self::clear_stats_cache();
		}
		
		return $metadata;
	}

	/**
	 * Log a new upload.
	 */
	public static function log_upload( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nexura_upload_logs';
		$mime_type  = get_post_mime_type( $attachment_id );
		$attachment = get_post( $attachment_id );
		$user_id    = ( $attachment && $attachment->post_author ) ? (int) $attachment->post_author : get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_name,
			array(
				'user_id'       => $user_id,
				'attachment_id' => $attachment_id,
				'file_name'     => basename( $file ),
				'file_size'     => self::calculate_attachment_disk_usage( $attachment_id ),
				'file_type'     => (string) $mime_type,
				'category'      => self::get_category( $mime_type ),
				'upload_date'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		self::clear_stats_cache();
	}

	/**
	 * Clear the storage stats transient cache for all users.
	 */
	public static function clear_stats_cache() {
		$version = (int) get_option( 'nexura_stats_cache_version', 1 );
		update_option( 'nexura_stats_cache_version', $version + 1 );
	}

	/**
	 * Remove log on attachment deletion.
	 */
	public static function remove_log( $attachment_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nexura_upload_logs';

		$attachment = get_post( $attachment_id );
		$user_id    = $attachment ? $attachment->post_author : 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$table_name,
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);

		self::clear_stats_cache();

		// Check if we need to reset 85% alert
		if ( $user_id && get_user_meta( $user_id, '_nexura_85_alert_sent', true ) ) {
			$current_usage = self::get_user_storage_usage( $user_id );
			$state         = class_exists( 'Nexura_Upload_Manager_Limits' ) ? Nexura_Upload_Manager_Limits::get_user_quota_state( $user_id ) : array( 'enforced' => false, 'bytes' => 0 );

			if ( ! empty( $state['enforced'] ) && $state['bytes'] > 0 ) {
				$percentage = ( $current_usage / $state['bytes'] ) * 100;
				if ( $percentage < 85 ) {
					delete_user_meta( $user_id, '_nexura_85_alert_sent' );
				}
			}
		}
	}

	/**
	 * WP-Cron callback to sync existing media items into the logs table in the background.
	 */
	public static function sync_existing_media_event() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nexura_upload_logs';

		if ( get_option( 'nexura_recalc_mode' ) ) {
			self::recalculate_batch();
			return;
		}
		
		if ( get_option( 'nexura_sync_complete' ) ) {
			update_option( 'nexura_sync_status', 'complete' );
			return;
		}

		update_option( 'nexura_sync_status', 'running' );

		// Calculate total if not set
		if ( ! get_option( 'nexura_sync_total' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
			update_option( 'nexura_sync_total', $total );
		}

		$processed  = (int) get_option( 'nexura_sync_processed', 0 );
		$batch_size = 100; // Smaller batch for background cron to prevent timeout

		// Fetch attachments that are not yet logged. Table names cannot be prepare() placeholders.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$attachments = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID as attachment_id, p.post_author as user_id, p.post_mime_type, p.post_date as upload_date FROM {$wpdb->posts} p LEFT JOIN {$table_name} log ON p.ID = log.attachment_id WHERE p.post_type = 'attachment' AND log.id IS NULL LIMIT %d", $batch_size ), ARRAY_A );
		
		if ( empty( $attachments ) ) {
			update_option( 'nexura_sync_complete', 1 );
			update_option( 'nexura_sync_status', 'complete' );
			return;
		}

		foreach ( $attachments as $att ) {
			$file_size = self::calculate_attachment_disk_usage( (int) $att['attachment_id'] );
			$category  = self::get_category( $att['post_mime_type'] );
			
			// Try to get basename from attached file path
			$file_path = get_attached_file( (int) $att['attachment_id'] );
			$file_name = basename( $file_path ? $file_path : 'unknown' );
			$user_id   = $att['user_id'] ? (int) $att['user_id'] : 1;
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$table_name} (user_id, attachment_id, file_name, file_size, file_type, category, upload_date) VALUES (%d, %d, %s, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE file_size = VALUES(file_size)", $user_id, (int) $att['attachment_id'], $file_name, $file_size, (string) $att['post_mime_type'], $category, $att['upload_date'] ) );
			
			$processed++;
		}

		update_option( 'nexura_sync_processed', $processed );
		
		// Schedule next batch immediately
		wp_schedule_single_event( time(), 'nexura_sync_media_event' );
	}

	/**
	 * Recalculate storage by truncating the log table and resetting sync flags.
	 */
	public static function recalculate_storage() {
		// Keep existing log rows so quotas stay enforced while sizes are refreshed.
		update_option( 'nexura_recalc_mode', 1 );
		update_option( 'nexura_recalc_last_id', 0 );
		delete_option( 'nexura_sync_complete' );
		delete_option( 'nexura_sync_offset' );
		update_option( 'nexura_sync_status', 'pending' );
		delete_option( 'nexura_sync_total' );
		update_option( 'nexura_sync_processed', 0 );

		wp_clear_scheduled_hook( 'nexura_sync_media_event' );
		wp_schedule_single_event( time(), 'nexura_sync_media_event' );

		self::clear_stats_cache();
	}

	/**
	 * Refresh attachment sizes in batches without emptying the quota log first.
	 */
	private static function recalculate_batch() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nexura_upload_logs';
		$last_id    = (int) get_option( 'nexura_recalc_last_id', 0 );
		$batch_size = 100;

		update_option( 'nexura_sync_status', 'running' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID as attachment_id, post_author as user_id, post_mime_type, post_date as upload_date
				 FROM {$wpdb->posts}
				 WHERE post_type = 'attachment' AND ID > %d
				 ORDER BY ID ASC
				 LIMIT %d",
				$last_id,
				$batch_size
			),
			ARRAY_A
		);

		if ( empty( $attachments ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->query( "DELETE log FROM `{$table_name}` log LEFT JOIN {$wpdb->posts} p ON p.ID = log.attachment_id AND p.post_type = 'attachment' WHERE p.ID IS NULL" );
			update_option( 'nexura_sync_complete', 1 );
			update_option( 'nexura_sync_status', 'complete' );
			delete_option( 'nexura_recalc_mode' );
			delete_option( 'nexura_recalc_last_id' );
			self::clear_stats_cache();
			return;
		}

		foreach ( $attachments as $att ) {
			$file_size = self::calculate_attachment_disk_usage( (int) $att['attachment_id'] );
			$category  = self::get_category( $att['post_mime_type'] );
			$file_path = get_attached_file( (int) $att['attachment_id'] );
			$file_name = basename( $file_path ? $file_path : 'unknown' );
			$user_id   = $att['user_id'] ? (int) $att['user_id'] : 1;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$table_name} (user_id, attachment_id, file_name, file_size, file_type, category, upload_date) VALUES (%d, %d, %s, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE file_size = VALUES(file_size), user_id = VALUES(user_id), file_name = VALUES(file_name), file_type = VALUES(file_type), category = VALUES(category)", $user_id, (int) $att['attachment_id'], $file_name, $file_size, (string) $att['post_mime_type'], $category, $att['upload_date'] ) );

			$last_id = (int) $att['attachment_id'];
		}

		update_option( 'nexura_recalc_last_id', $last_id );
		update_option( 'nexura_sync_processed', (int) get_option( 'nexura_sync_processed', 0 ) + count( $attachments ) );
		self::clear_stats_cache();
		wp_schedule_single_event( time() + 1, 'nexura_sync_media_event' );
	}

	/**
	 * Get total storage usage for a specific user.
	 *
	 * @param int $user_id
	 * @return int Total size in bytes.
	 */
	public static function get_user_storage_usage( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nexura_upload_logs';
		$sessions_table = $wpdb->prefix . 'nexura_upload_sessions';

		// Get physical usage
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$usage = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(file_size) FROM `{$table_name}` WHERE user_id = %d", (int) $user_id ) );

		// Get active reserved usage from sessions table
		// Ensure table exists (for existing installs that haven't deactivated/reactivated)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions_table ) ) === $sessions_table ) {
			// Include both uploading and processing sessions to prevent the processing window race condition
			$exclude_session = defined( 'NEXURA_CURRENT_PROCESSING_SESSION' ) ? NEXURA_CURRENT_PROCESSING_SESSION : '';
			
			if ( $exclude_session ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$reserved = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(expected_size) FROM `{$sessions_table}` WHERE user_id = %d AND status IN ('uploading', 'processing') AND session_id != %s", (int) $user_id, $exclude_session ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$reserved = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(expected_size) FROM `{$sessions_table}` WHERE user_id = %d AND status IN ('uploading', 'processing')", (int) $user_id ) );
			}
			$usage += $reserved;
		}

		return $usage;
	}

	/**
	 * Get storage usage stats for the Donut chart (with transient cache).
	 *
	 * @param int|null $user_id Optional user ID to filter stats.
	 * @return array
	 */
	public static function get_storage_stats( $user_id = null ) {
		$version = (int) get_option( 'nexura_stats_cache_version', 1 );
		$cache_key = 'nexura_storage_stats_v2_' . $version . ( $user_id ? '_' . $user_id : '' );
		
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name     = $wpdb->prefix . 'nexura_upload_logs';
		$safe_table     = esc_sql( $table_name );

		// Ensure table exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			self::create_tables();
		}

		$where = '';
		if ( $user_id ) {
			$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$safe_table}` {$where}" );
		
		if ( ! get_option( 'nexura_sync_complete' ) && ! $user_id ) {
			if ( ! wp_next_scheduled( 'nexura_sync_media_event' ) ) {
				update_option( 'nexura_sync_status', 'pending' );
				wp_schedule_single_event( time(), 'nexura_sync_media_event' );
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( "SELECT file_type, SUM(file_size) as total_size, COUNT(id) as total_files FROM `{$safe_table}` {$where} GROUP BY file_type", ARRAY_A );
		
		$stats = array(
			'JPG'    => array( 'size' => 0, 'files' => 0, 'color' => '#36A2EB' ),
			'PNG'    => array( 'size' => 0, 'files' => 0, 'color' => '#FF6384' ),
			'WebP'   => array( 'size' => 0, 'files' => 0, 'color' => '#22C55E' ),
			'AVIF'   => array( 'size' => 0, 'files' => 0, 'color' => '#14B8A6' ),
			'GIF'    => array( 'size' => 0, 'files' => 0, 'color' => '#F59E0B' ),
			'PDF'    => array( 'size' => 0, 'files' => 0, 'color' => '#FF9F40' ),
			'ZIP'    => array( 'size' => 0, 'files' => 0, 'color' => '#4BC0C0' ),
			'SVG'    => array( 'size' => 0, 'files' => 0, 'color' => '#9966FF' ),
			'Video'  => array( 'size' => 0, 'files' => 0, 'color' => '#EF4444' ),
			'Audio'  => array( 'size' => 0, 'files' => 0, 'color' => '#8B5CF6' ),
			'Others' => array( 'size' => 0, 'files' => 0, 'color' => '#C9CBCF' ),
		);

		$total_size  = 0;
		$total_files = 0;

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$mime = strtolower( (string) $row['file_type'] );
				
				if ( strpos( $mime, 'video/' ) === 0 ) {
					$cat = 'Video';
				} elseif ( strpos( $mime, 'audio/' ) === 0 ) {
					$cat = 'Audio';
				} elseif ( $mime === 'image/jpeg' || $mime === 'image/jpg' ) {
					$cat = 'JPG';
				} elseif ( $mime === 'image/png' ) {
					$cat = 'PNG';
				} elseif ( $mime === 'image/webp' ) {
					$cat = 'WebP';
				} elseif ( $mime === 'image/gif' ) {
					$cat = 'GIF';
				} elseif ( $mime === 'image/avif' ) {
					$cat = 'AVIF';
				} elseif ( $mime === 'application/pdf' ) {
					$cat = 'PDF';
				} elseif ( $mime === 'application/zip' || $mime === 'application/x-zip-compressed' ) {
					$cat = 'ZIP';
				} elseif ( $mime === 'image/svg+xml' ) {
					$cat = 'SVG';
				} else {
					$cat = 'Others';
				}

				$stats[ $cat ]['size']  += (int) $row['total_size'];
				$stats[ $cat ]['files'] += (int) $row['total_files'];
				$total_size  += (int) $row['total_size'];
				$total_files += (int) $row['total_files'];
			}
		}

		$result = array(
			'categories'  => $stats,
			'total_size'  => $total_size,
			'total_files' => $total_files,
		);

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}
}

Nexura_Upload_Activity_Logger::init();
