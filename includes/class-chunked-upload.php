<?php
/**
 * Handles Large File Engine via Plupload chunking.
 *
 * @package Nexura_Upload_Limits_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Nexura_Chunked_Upload Class
 */
class Nexura_Chunked_Upload {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Intercept async-upload for chunking before default WordPress handler
		add_action( 'wp_ajax_async-upload', array( __CLASS__, 'intercept_chunk' ), 1 );
		
		// Modify plupload default settings to enable chunking
		add_filter( 'plupload_default_settings', array( __CLASS__, 'enable_chunking' ) );

		// Theme ZIP, plugin ZIP, and block-editor uploads that do not use Plupload.
		add_action( 'wp_ajax_nexura_package_chunk', array( __CLASS__, 'handle_package_chunk' ) );
		add_action( 'wp_ajax_nexura_rest_media_chunk', array( __CLASS__, 'handle_rest_media_chunk' ) );
		
		// Enqueue JS for session_id and total_size injection.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_media', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_scripts' ) );
		
		// Garbage collection event
		add_action( 'nexura_chunk_cleanup_event', array( __CLASS__, 'cleanup_temp_chunks' ) );
	}

	/**
	 * Enqueue admin scripts for Plupload modification.
	 */
	public static function enqueue_scripts( $hook = '' ) {
		wp_enqueue_script(
			'nexura-chunk-init',
			NEXURA_UPLOAD_MANAGER_URL . 'assets/js/nexura-chunk-init.js',
			array( 'jquery', 'wp-plupload' ),
			NEXURA_UPLOAD_MANAGER_VERSION,
			true
		);

		$shared = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'chunkSize'   => self::get_chunk_size_bytes(),
			'singleLimit' => self::get_single_request_limit(),
		);

		wp_enqueue_script(
			'nexura-rest-chunk',
			NEXURA_UPLOAD_MANAGER_URL . 'assets/js/nexura-rest-chunk.js',
			array( 'wp-api-fetch' ),
			NEXURA_UPLOAD_MANAGER_VERSION,
			true
		);
		wp_localize_script(
			'nexura-rest-chunk',
			'nexuraRestChunk',
			array_merge(
				$shared,
				array(
					'nonce' => wp_create_nonce( 'nexura_rest_media_chunk' ),
				)
			)
		);

		if ( in_array( $hook, array( 'plugin-install.php', 'theme-install.php' ), true ) ) {
			wp_enqueue_script(
				'nexura-package-upload',
				NEXURA_UPLOAD_MANAGER_URL . 'assets/js/nexura-package-upload.js',
				array(),
				NEXURA_UPLOAD_MANAGER_VERSION,
				true
			);
			wp_localize_script(
				'nexura-package-upload',
				'nexuraPackage',
				array_merge(
					$shared,
					array(
						'nonce'   => wp_create_nonce( 'nexura_package_upload' ),
						'working' => __( 'Uploading…', 'nexura-upload-limits-manager' ),
						'failed'  => __( 'Upload failed. Please try again.', 'nexura-upload-limits-manager' ),
					)
				)
			);
		}
	}

	/**
	 * Enable chunking in Plupload settings.
	 *
	 * @param array $settings Plupload settings.
	 * @return array
	 */
	public static function enable_chunking( $settings ) {
		$settings['chunk_size']  = self::get_chunk_size_bytes() . 'b';
		$settings['max_retries'] = 3;

		if ( class_exists( 'Nexura_Upload_Manager_Limits' ) ) {
			$limit = Nexura_Upload_Manager_Limits::get_enforced_per_file_limit();
			if ( PHP_INT_MAX === $limit ) {
				$settings['max_file_size'] = '1024gb';
			} elseif ( $limit > 0 ) {
				$settings['max_file_size'] = $limit . 'b';
			}
		}

		return $settings;
	}

	/**
	 * Chunk size that stays under PHP and under a 1 MB proxy body cap.
	 *
	 * Nginx defaults to client_max_body_size 1m. A plugin cannot change that file.
	 * Every part stays at or below 512 KB so a 413 from Nginx or a CDN is avoided
	 * even when PHP itself allows a much larger upload.
	 *
	 * @return int
	 */
	public static function get_chunk_size_bytes() {
		$proxy_safe = 512 * 1024;
		$limits     = self::php_request_limits();
		if ( ! $limits ) {
			return $proxy_safe;
		}

		$under_php = (int) floor( min( $limits ) * 0.45 );
		if ( $under_php < 1024 ) {
			return $proxy_safe;
		}

		return (int) min( $proxy_safe, $under_php );
	}

	/**
	 * Largest body that is safe to send in one request.
	 *
	 * Matches the chunk size. A single request at 85% of post_max_size still
	 * fails when Nginx or Cloudflare cuts the body off at 1 MB.
	 *
	 * @return int
	 */
	public static function get_single_request_limit() {
		return self::get_chunk_size_bytes();
	}

	/**
	 * Positive upload_max_filesize and post_max_size values, in bytes.
	 *
	 * @return int[]
	 */
	private static function php_request_limits() {
		$limits = array();
		foreach ( array( 'post_max_size', 'upload_max_filesize' ) as $key ) {
			$bytes = wp_convert_hr_to_bytes( ini_get( $key ) );
			if ( $bytes > 0 ) {
				$limits[] = $bytes;
			}
		}
		return $limits;
	}

	/**
	 * Intercept chunked upload.
	 */
	public static function intercept_chunk() {
		// Check if this is a chunked upload
		if ( ! isset( $_POST['chunk'] ) || ! isset( $_POST['chunks'] ) || ! isset( $_POST['name'] ) || ! isset( $_FILES['async-upload'] ) ) {
			return; // Not a chunked upload, let WordPress handle it normally
		}

		// Plupload chunk variables
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$chunk  = isset( $_POST['chunk'] ) ? (int) $_POST['chunk'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$chunks = isset( $_POST['chunks'] ) ? (int) $_POST['chunks'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$name   = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$total_size = isset( $_POST['nexura_total_size'] ) ? (int) $_POST['nexura_total_size'] : 0;
		
		$user_id = get_current_user_id();

		// Use the deterministic resume string from JS to generate a persistent session ID
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['nexura_resume_id'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$resume_string = wp_unslash( $_POST['nexura_resume_id'] );
			$session_id    = 'nexura_' . md5( $user_id . '_' . $resume_string );
		} else {
			$session_id    = 'nexura_' . md5( $user_id . '_' . $name . '_' . $total_size );
		}

		// Verify nonce
		check_ajax_referer( 'media-form' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}
		
		// --------------------------------------------------------
		// 1. Strict Validation
		// --------------------------------------------------------
		if ( $chunks < 1 || $chunks > 10000 ) {
			wp_send_json_error( array( 'message' => 'Invalid chunk count.' ) );
		}
		if ( ! preg_match( '/^nexura_[a-z0-9]+$/', $session_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid session ID.' ) );
		}
		if ( $total_size <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid total size.' ) );
		}

		// Enforce per-file upload limit (Global/Role based)
		if ( ! class_exists( 'Nexura_Upload_Manager_Limits' ) ) {
			require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-upload-limits.php';
		}
		$effective_limit = Nexura_Upload_Manager_Limits::get_enforced_per_file_limit( $user_id );
		if ( $effective_limit > 0 && $total_size > $effective_limit ) {
			wp_send_json_error( array( 'message' => sprintf( 'File size exceeds your per-file upload limit (%s).', size_format( $effective_limit ) ) ) );
		}
		
		// File data
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$file_data = $_FILES['async-upload'];
		if ( ! isset( $file_data['tmp_name'] ) || ! file_exists( $file_data['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'File data missing.' ) );
		}
		$chunk_size = filesize( $file_data['tmp_name'] );

		// Validate chunk index
		if ( $chunk < 0 || $chunk >= $chunks ) {
			wp_send_json_error( array( 'message' => 'Invalid chunk index.' ) );
		}
		
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'nexura_upload_sessions';
		$user_id = get_current_user_id();

		// --------------------------------------------------------
		// 2. Quota Reservation & Session Management
		// --------------------------------------------------------
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$session = $wpdb->get_row( $wpdb->prepare( "SELECT id, expected_size, received_size FROM {$sessions_table} WHERE session_id = %s AND user_id = %d AND status = 'uploading'", $session_id, $user_id ) );

		if ( ! $session ) {
			$lock_name = 'nexura_quota_' . $user_id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$got_lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );
			if ( '1' !== (string) $got_lock ) {
				wp_send_json_error( array( 'message' => 'Upload is busy. Please retry.' ) );
			}

			// Re-check session inside lock to prevent race condition
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$session = $wpdb->get_row( $wpdb->prepare( "SELECT id, expected_size, received_size FROM {$sessions_table} WHERE session_id = %s AND user_id = %d AND status = 'uploading'", $session_id, $user_id ) );

			if ( ! $session ) {
				require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-upload-limits.php';
				
				$file_type_check = wp_check_filetype( $name );
				if ( empty( $file_type_check['ext'] ) || empty( $file_type_check['type'] ) ) {
					// Release lock before error
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
					wp_send_json_error( array( 'message' => 'Sorry, you are not allowed to upload this file type.' ) );
				}
				
				// Mock a file array with the total size to pass to our quota checker
				$mock_file = array( 'size' => $total_size, 'name' => $name );
				$quota_check = Nexura_Upload_Manager_Limits::check_storage_quota( $mock_file );
				
				if ( isset( $quota_check['error'] ) ) {
					// Release lock before error
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
					wp_send_json_error( array( 'message' => $quota_check['error'] ) );
				}
				
				// Insert session to reserve quota persistently. 
				// If two out-of-order chunks arrive simultaneously, the UNIQUE(session_id) constraint will fail the second one.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$inserted = $wpdb->insert(
					$sessions_table,
					array(
						'session_id'    => $session_id,
						'user_id'       => $user_id,
						'file_name'     => $name,
						'expected_size' => $total_size,
						'received_size' => 0,
						'total_chunks'  => $chunks,
						'status'        => 'uploading',
						'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + 86400 ) // expires in 1 day
					),
					array( '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
				);

				if ( ! $inserted ) {
					// Another concurrent chunk created the session exactly at the same time. Just fetch it and proceed!
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$session = $wpdb->get_row( $wpdb->prepare( "SELECT id, expected_size, received_size FROM {$sessions_table} WHERE session_id = %s AND user_id = %d AND status = 'uploading'", $session_id, $user_id ) );
					if ( ! $session ) {
						// Release lock before error
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
						wp_send_json_error( array( 'message' => 'Upload session invalid or expired.' ) );
					}
				} else {
					// Atomic quota check: Re-verify usage AFTER insert to prevent race condition bypass.
					$post_insert_usage = Nexura_Upload_Activity_Logger::get_user_storage_usage( $user_id );
					$quota_state       = Nexura_Upload_Manager_Limits::get_user_quota_state( $user_id );
					$max_quota_bytes   = ( ! empty( $quota_state['enforced'] ) ) ? (int) $quota_state['bytes'] : 0;
					if ( $max_quota_bytes > 0 && $post_insert_usage > $max_quota_bytes ) {
						// Rollback
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$sessions_table} WHERE session_id = %s", $session_id ) );
						// Release lock before error
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
						wp_send_json_error( array( 'message' => 'Quota bypassed via concurrent upload. Request blocked.' ) );
					}
					
					// Manually construct the session object for the rest of the script
					$session = (object) array(
						'expected_size' => $total_size,
						'received_size' => 0,
					);
				}
			}

			// Release lock after everything is done
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
		}

		// --------------------------------------------------------
		// 3. Setup Session Directory
		// --------------------------------------------------------
		$temp_dir = self::get_temp_base_dir();
		if ( ! $temp_dir ) {
			wp_send_json_error( array( 'message' => 'Temporary upload directory is not writable.' ) );
		}
		$session_dir = $temp_dir . '/' . $session_id;
		
		if ( ! file_exists( $session_dir ) ) {
			wp_mkdir_p( $session_dir );
			self::protect_dir( $session_dir );
		}

		$chunk_file = $session_dir . '/' . $chunk . '.part';

		// If chunk already exists (retry duplicate), skip DB update and rename
		if ( file_exists( $chunk_file ) ) {
			wp_send_json_success( array( 'message' => 'Chunk already received.' ) );
		}

		// --------------------------------------------------------
		// 4. Save Chunk (Out-of-order safe, Atomic DB Lock)
		// --------------------------------------------------------
		$chunks_table = $wpdb->prefix . 'nexura_upload_chunks';
		
		// Attempt to atomically reserve this specific chunk index for this session.
		// If two requests for the same chunk arrive simultaneously, the UNIQUE(session_id, chunk_index)
		// constraint will cause the second insert to fail, preventing double-counting.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$chunks_table,
			array(
				'session_id'  => $session_id,
				'chunk_index' => $chunk,
				'chunk_size'  => $chunk_size,
			),
			array( '%s', '%d', '%d' )
		);

		if ( ! $inserted ) {
			// A DB lock exists for this chunk. But does the file actually exist?
			if ( file_exists( $chunk_file ) ) {
				// Perfect, the concurrent request successfully saved the file.
				wp_send_json_success( array( 'message' => 'Chunk already received concurrently.' ) );
			}
			
			// The file does NOT exist. This means either:
			// 1. A concurrent request is actively saving it right now.
			// 2. A previous request crashed while saving, leaving a stale lock.
			
			// Fetch the lock to check how old it is.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$lock = $wpdb->get_row( $wpdb->prepare( "SELECT created_at FROM {$chunks_table} WHERE session_id = %s AND chunk_index = %d", $session_id, $chunk ) );
			
			// If lock is older than 15 minutes, it's definitely a stale crash lock.
			if ( $lock && ( time() - strtotime( $lock->created_at ) > 900 ) ) {
				// Delete the stale lock
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $chunks_table, array( 'session_id' => $session_id, 'chunk_index' => $chunk ), array( '%s', '%d' ) );
			}
			
			// Return an error so Plupload instantly retries this chunk. 
			// If it was a concurrent request, the file will exist on the next retry.
			// If it was a stale lock, it was just deleted and the lock will succeed on the next retry.
			wp_send_json_error( array( 'message' => 'Chunk lock conflict or stale lock. Retrying...' ) );
		}

		// We use native PHP stream functions instead of WP_Filesystem to prevent memory exhaustion on large files.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( rename( $file_data['tmp_name'], $chunk_file ) ) {
			// Count this chunk only while the running total stays within the reserved size.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$update_result = $wpdb->query( $wpdb->prepare( "UPDATE {$sessions_table} SET received_size = received_size + %d WHERE session_id = %s AND status = 'uploading' AND received_size + %d <= expected_size", $chunk_size, $session_id, $chunk_size ) );

			if ( ! $update_result ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $chunks_table, array( 'session_id' => $session_id, 'chunk_index' => $chunk ), array( '%s', '%d' ) );
				wp_delete_file( $chunk_file );
				wp_send_json_error( array( 'message' => 'File size exceeds the reserved upload size.' ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$session->received_size = (int) $wpdb->get_var( $wpdb->prepare( "SELECT received_size FROM {$sessions_table} WHERE session_id = %s", $session_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$session->expected_size = (int) $wpdb->get_var( $wpdb->prepare( "SELECT expected_size FROM {$sessions_table} WHERE session_id = %s", $session_id ) );
		} else {
			// Release the atomic lock so the chunk can be retried
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $chunks_table, array( 'session_id' => $session_id, 'chunk_index' => $chunk ), array( '%s', '%d' ) );
			wp_send_json_error( array( 'message' => 'Failed to move chunk to temporary directory.' ) );
		}

		// --------------------------------------------------------
		// 5. Check if all chunks have arrived
		// --------------------------------------------------------
		if ( (int) $session->received_size === (int) $session->expected_size ) {
			// SECURITY CHECK: Verify DB chunk count matches total chunks
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$db_chunk_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$chunks_table} WHERE session_id = %s", $session_id ) );
			if ( $db_chunk_count !== $chunks ) {
				wp_send_json_error( array( 'message' => 'Chunk count mismatch in database. Upload failed.' ) );
			}

			// SECURITY CHECK: Verify all chunk files actually exist on disk
			for ( $i = 0; $i < $chunks; $i++ ) {
				if ( ! file_exists( $session_dir . '/' . $i . '.part' ) ) {
					wp_send_json_error( array( 'message' => 'Missing chunk file. Merge aborted.' ) );
				}
			}

			$final_temp_file = $session_dir . '/merged_' . $name;

			// Only one request may merge. Others are told the upload is finishing.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$sessions_table} SET status = 'processing' WHERE session_id = %s AND status = 'uploading' AND received_size = expected_size", $session_id ) );
			if ( ! $claimed ) {
				wp_send_json_success( array( 'message' => 'Upload is being finalized.' ) );
			}

			if ( ! defined( 'NEXURA_CURRENT_PROCESSING_SESSION' ) ) {
				define( 'NEXURA_CURRENT_PROCESSING_SESSION', $session_id );
			}

			$merged = self::merge_chunks( $session_dir, $chunks, $final_temp_file );
			if ( is_wp_error( $merged ) ) {
				self::reset_session_uploading( $session_id );
				wp_delete_file( $final_temp_file );
				wp_send_json_error( array( 'message' => $merged->get_error_message() ) );
			}

			$merged_size = file_exists( $final_temp_file ) ? filesize( $final_temp_file ) : 0;
			if ( $merged_size !== (int) $session->expected_size ) {
				self::reset_session_uploading( $session_id );
				wp_delete_file( $final_temp_file );
				wp_send_json_error( array( 'message' => 'Merged file size mismatch. Upload failed.' ) );
			}

			for ( $i = 0; $i < $chunks; $i++ ) {
				wp_delete_file( $session_dir . '/' . $i . '.part' );
			}

			// Trick WordPress into processing this merged file.
			$_FILES['async-upload']['tmp_name'] = $final_temp_file;
			$_FILES['async-upload']['size']     = $merged_size;
			$_FILES['async-upload']['name']     = $name;

			// Unset chunk variables so our interceptor doesn't run again.
			unset( $_POST['chunk'], $_POST['chunks'] );

			add_filter(
				'wp_handle_upload_prefilter',
				function( $file ) {
					$file['test_form'] = false;
					return $file;
				}
			);

			add_filter(
				'wp_handle_upload',
				function( $upload ) use ( $session_dir, $session_id ) {
					if ( isset( $upload['error'] ) ) {
						Nexura_Chunked_Upload::fail_session( $session_id, $session_dir );
					}
					return $upload;
				}
			);

			add_action(
				'add_attachment',
				function() use ( $session_dir, $session_id ) {
					Nexura_Chunked_Upload::delete_dir( $session_dir );

					global $wpdb;
					$sessions_table = $wpdb->prefix . 'nexura_upload_sessions';
					$chunks_table   = $wpdb->prefix . 'nexura_upload_chunks';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->query( $wpdb->prepare( "UPDATE {$sessions_table} SET status = 'complete' WHERE session_id = %s", $session_id ) );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$chunks_table} WHERE session_id = %s", $session_id ) );
				}
			);

			// Let WordPress's default wp_ajax_async-upload handler finish the request.
			return;
		}

		// If not all chunks are here yet, return a success response to Plupload to send the next chunk.
		wp_send_json_success( array(
			'message' => 'Chunk uploaded successfully.',
			'chunk'   => $chunk,
		) );
		exit;
	}

	/**
	 * Garbage collection for temporary chunks.
	 */
	public static function cleanup_temp_chunks() {
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'nexura_upload_sessions';
		$temp_dir       = self::get_temp_base_dir();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$expired_sessions = $wpdb->get_results( "SELECT session_id FROM {$sessions_table} WHERE expires_at < NOW()" );

		if ( $expired_sessions ) {
			foreach ( $expired_sessions as $session ) {
				if ( ! preg_match( '/^nexura_[a-z0-9]+$/', $session->session_id ) ) {
					continue;
				}
				if ( $temp_dir ) {
					$session_dir = $temp_dir . '/' . $session->session_id;
					if ( is_dir( $session_dir ) ) {
						self::delete_dir( $session_dir );
					}
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$sessions_table} WHERE session_id = %s", $session->session_id ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}nexura_upload_chunks WHERE session_id = %s", $session->session_id ) );
			}
		}

		$dirs = array();
		if ( $temp_dir && is_dir( $temp_dir ) ) {
			$dirs[] = $temp_dir;
		}
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['error'] ) ) {
			$legacy = trailingslashit( $upload_dir['basedir'] ) . 'nexura-temp';
			if ( is_dir( $legacy ) ) {
				$dirs[] = $legacy;
			}
		}

		$now = time();
		foreach ( $dirs as $dir ) {
			$session_dirs = glob( trailingslashit( $dir ) . 'nexura_*', GLOB_ONLYDIR );
			if ( ! $session_dirs ) {
				continue;
			}
			foreach ( $session_dirs as $session_dir ) {
				if ( $now - filemtime( $session_dir ) > 48 * 3600 ) {
					self::delete_dir( $session_dir );
				}
			}
		}
	}

	/**
	 * Writable directory for chunk parts, outside the web root when possible.
	 *
	 * @return string
	 */
	public static function get_temp_base_dir() {
		$hash = substr( md5( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'nexura-upload' ), 0, 12 );
		$candidates = array();

		$sys = rtrim( sys_get_temp_dir(), '/\\' );
		if ( $sys && is_dir( $sys ) && self::is_path_writable( $sys ) ) {
			$candidates[] = $sys . '/nexura-chunks-' . $hash;
		}

		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['error'] ) ) {
			$candidates[] = trailingslashit( $upload_dir['basedir'] ) . 'nexura-temp-' . $hash;
		}

		foreach ( $candidates as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			if ( is_dir( $dir ) && self::is_path_writable( $dir ) ) {
				self::protect_dir( $dir );
				return $dir;
			}
		}

		return '';
	}

	/**
	 * Whether a path is writable through the WordPress filesystem API.
	 *
	 * @param string $path File or directory path.
	 * @return bool
	 */
	private static function is_path_writable( $path ) {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return ( $wp_filesystem && $wp_filesystem->is_writable( $path ) );
	}

	/**
	 * Block direct web access to a chunk directory.
	 *
	 * @param string $dir Directory path.
	 */
	private static function protect_dir( $dir ) {
		$dir   = trailingslashit( $dir );
		$index = $dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Require all denied\nOrder allow,deny\nDeny from all\n" );
		}
	}

	/**
	 * Put a failed merge back so the client can retry.
	 *
	 * @param string $session_id Session ID.
	 */
	public static function reset_session_uploading( $session_id ) {
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'nexura_upload_sessions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( $wpdb->prepare( "UPDATE {$sessions_table} SET status = 'uploading' WHERE session_id = %s", $session_id ) );
	}

	/**
	 * Drop a session after WordPress rejects the merged file, freeing the reserved quota.
	 *
	 * @param string $session_id  Session ID.
	 * @param string $session_dir Session directory.
	 */
	public static function fail_session( $session_id, $session_dir ) {
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'nexura_upload_sessions';
		$chunks_table   = $wpdb->prefix . 'nexura_upload_chunks';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $sessions_table, array( 'session_id' => $session_id ), array( '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$chunks_table} WHERE session_id = %s", $session_id ) );
		self::delete_dir( $session_dir );
	}

	/**
	 * Concatenate chunk parts without deleting them.
	 *
	 * @param string $session_dir     Session directory.
	 * @param int    $chunks          Chunk count.
	 * @param string $final_temp_file Destination file.
	 * @return true|WP_Error
	 */
	private static function merge_chunks( $session_dir, $chunks, $final_temp_file ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( $final_temp_file, 'wb' );
		if ( ! $out ) {
			return new WP_Error( 'nexura_merge', 'Failed to create merged file.' );
		}

		for ( $i = 0; $i < $chunks; $i++ ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$in = fopen( $session_dir . '/' . $i . '.part', 'rb' );
			if ( ! $in ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $out );
				return new WP_Error( 'nexura_merge', 'Failed to read chunk during merge.' );
			}
			$copied = stream_copy_to_stream( $in, $out );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $in );
			if ( false === $copied ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $out );
				return new WP_Error( 'nexura_merge', 'Failed to write merged file.' );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out );
		return true;
	}

	/**
	 * Helper function to recursively delete a directory.
	 *
	 * @param string $dirPath Directory path.
	 */
	public static function delete_dir( $dirPath ) {
		if ( ! is_dir( $dirPath ) ) {
			return;
		}
		$items = scandir( $dirPath );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dirPath . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				self::delete_dir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dirPath );
	}

	/**
	 * Receive a theme or plugin ZIP in parts, then hand it to the core installer.
	 */
	public static function handle_package_chunk() {
		check_ajax_referer( 'nexura_package_upload' );

		$type = isset( $_POST['package_type'] ) ? sanitize_key( wp_unslash( $_POST['package_type'] ) ) : '';
		if ( 'plugin' === $type ) {
			if ( ! current_user_can( 'upload_plugins' ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to install plugins.', 'nexura-upload-limits-manager' ) ) );
			}
		} elseif ( 'theme' === $type ) {
			if ( ! current_user_can( 'upload_themes' ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to install themes.', 'nexura-upload-limits-manager' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid package type.', 'nexura-upload-limits-manager' ) ) );
		}

		$name = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Only .zip archives may be uploaded.', 'nexura-upload-limits-manager' ) ) );
		}

		$cached = self::finished_payload( $type );
		if ( $cached ) {
			wp_send_json_success( $cached );
		}

		$result = self::accept_chunk( $type );
		if ( is_wp_error( $result ) ) {
			self::send_chunk_error( $result );
		}
		if ( empty( $result['complete'] ) ) {
			wp_send_json_success( array( 'message' => 'part-received' ) );
		}

		if ( ! self::is_zip_file( $result['path'] ) ) {
			self::delete_dir( $result['dir'] );
			wp_send_json_error( array( 'message' => __( 'This file is not a valid zip archive.', 'nexura-upload-limits-manager' ) ) );
		}

		$structure_error = self::package_structure_error( $result['path'], $type );
		if ( '' !== $structure_error ) {
			self::delete_dir( $result['dir'] );
			wp_send_json_error( array( 'message' => $structure_error ) );
		}

		$redirect = self::stage_package_for_installer( $result['path'], $name, $type );
		self::delete_dir( $result['dir'] );
		if ( is_wp_error( $redirect ) ) {
			wp_send_json_error( array( 'message' => $redirect->get_error_message() ) );
		}

		$payload = array( 'redirect' => $redirect );
		self::remember_payload( $type, $payload );
		wp_send_json_success( $payload );
	}

	/**
	 * Receive a media file in parts for the block editor, which posts once to the REST API.
	 */
	public static function handle_rest_media_chunk() {
		check_ajax_referer( 'nexura_rest_media_chunk' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to upload files.', 'nexura-upload-limits-manager' ) ) );
		}

		$name = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file name.', 'nexura-upload-limits-manager' ) ) );
		}

		$cached = self::finished_payload( 'media' );
		if ( $cached ) {
			wp_send_json_success( $cached );
		}

		$result = self::accept_chunk( 'media' );
		if ( is_wp_error( $result ) ) {
			self::send_chunk_error( $result );
		}
		if ( empty( $result['complete'] ) ) {
			wp_send_json_success( array( 'message' => 'part-received' ) );
		}

		$parent = isset( $_POST['post'] ) ? absint( wp_unslash( $_POST['post'] ) ) : 0;
		$caption = ( isset( $_POST['caption'] ) && is_string( $_POST['caption'] ) ) ? wp_kses_post( wp_unslash( $_POST['caption'] ) ) : '';
		$description = ( isset( $_POST['description'] ) && is_string( $_POST['description'] ) ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$title = ( isset( $_POST['title'] ) && is_string( $_POST['title'] ) ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$alt_text = ( isset( $_POST['alt_text'] ) && is_string( $_POST['alt_text'] ) ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';

		$attachment = self::create_media_attachment(
			$result['path'],
			$name,
			array(
				'parent'      => $parent,
				'caption'     => $caption,
				'description' => $description,
				'title'       => $title,
				'alt_text'    => $alt_text,
			)
		);
		self::delete_dir( $result['dir'] );
		if ( is_wp_error( $attachment ) ) {
			wp_send_json_error( array( 'message' => $attachment->get_error_message() ) );
		}

		$payload = array( 'attachment' => $attachment );
		self::remember_payload( 'media', $payload );
		wp_send_json_success( $payload );
	}

	/**
	 * Chunk fields from the current request.
	 *
	 * Callers verify the AJAX nonce before this runs. The temporary path is a PHP upload path, checked with is_uploaded_file().
	 *
	 * @return array{chunk:int,chunks:int,total_size:int,resume:string,tmp_name:string}
	 */
	private static function posted_chunk() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$chunk      = isset( $_POST['chunk'] ) ? (int) $_POST['chunk'] : -1;
		$chunks     = isset( $_POST['chunks'] ) ? (int) $_POST['chunks'] : 0;
		$total_size = isset( $_POST['nexura_total_size'] ) ? (int) $_POST['nexura_total_size'] : 0;
		$resume     = isset( $_POST['nexura_resume_id'] ) ? sanitize_text_field( wp_unslash( $_POST['nexura_resume_id'] ) ) : '';
		$tmp_name   = '';
		if ( isset( $_FILES['nexura_part']['tmp_name'] ) ) {
			$raw_tmp = wp_unslash( $_FILES['nexura_part']['tmp_name'] );
			if ( is_string( $raw_tmp ) ) {
				$tmp_name = $raw_tmp;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return array(
			'chunk'      => $chunk,
			'chunks'     => $chunks,
			'total_size' => $total_size,
			'resume'     => $resume,
			'tmp_name'   => $tmp_name,
		);
	}

	/**
	 * Store one uploaded part. The last part merges the file.
	 *
	 * @param string $kind Session group: plugin, theme, or media.
	 * @return array|WP_Error
	 */
	private static function accept_chunk( $kind ) {
		$posted     = self::posted_chunk();
		$chunk      = $posted['chunk'];
		$chunks     = $posted['chunks'];
		$total_size = $posted['total_size'];
		$resume     = $posted['resume'];
		$tmp_name   = $posted['tmp_name'];

		if ( '' === $resume || strlen( $resume ) > 200 ) {
			return new WP_Error( 'nexura_chunk', __( 'Invalid upload id.', 'nexura-upload-limits-manager' ) );
		}
		if ( $chunks < 1 || $chunk < 0 || $chunk >= $chunks || $total_size <= 0 ) {
			return new WP_Error( 'nexura_chunk', __( 'Invalid upload part.', 'nexura-upload-limits-manager' ) );
		}

		$max_bytes = self::max_accepted_bytes();
		if ( $total_size > $max_bytes ) {
			return new WP_Error(
				'nexura_chunk',
				sprintf(
					/* translators: %s: maximum file size. */
					__( 'File size exceeds your upload limit (%s).', 'nexura-upload-limits-manager' ),
					size_format( $max_bytes )
				)
			);
		}

		$max_chunks = (int) ceil( $max_bytes / self::get_chunk_size_bytes() ) + 2;
		if ( $chunks > $max_chunks ) {
			return new WP_Error( 'nexura_chunk', __( 'Invalid upload part.', 'nexura-upload-limits-manager' ) );
		}

		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'nexura_chunk', __( 'Upload data missing.', 'nexura-upload-limits-manager' ) );
		}

		$chunk_bytes = (int) filesize( $tmp_name );
		if ( $chunk_bytes <= 0 || $chunk_bytes > ( self::get_chunk_size_bytes() + 65536 ) ) {
			return new WP_Error( 'nexura_chunk', __( 'This part is too large for the server.', 'nexura-upload-limits-manager' ) );
		}

		$free = function_exists( 'disk_free_space' ) ? disk_free_space( ABSPATH ) : false;
		if ( false !== $free && $free < ( $total_size + 52428800 ) ) {
			return new WP_Error( 'nexura_chunk', __( 'Not enough free disk space for this file.', 'nexura-upload-limits-manager' ) );
		}

		$session_id = self::session_id_for( $kind, $resume );
		if ( '' === $session_id ) {
			return new WP_Error( 'nexura_chunk', __( 'Invalid upload id.', 'nexura-upload-limits-manager' ) );
		}
		$temp_dir   = self::get_temp_base_dir();
		if ( ! $temp_dir ) {
			return new WP_Error( 'nexura_chunk', __( 'Temporary upload directory is not writable.', 'nexura-upload-limits-manager' ) );
		}

		$session_dir = $temp_dir . '/' . $session_id;
		if ( ! is_dir( $session_dir ) ) {
			wp_mkdir_p( $session_dir );
			self::protect_dir( $session_dir );
		}

		$lock = fopen( $session_dir . '/lock', 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $lock ) {
			return new WP_Error( 'nexura_chunk', __( 'Could not lock the upload session.', 'nexura-upload-limits-manager' ) );
		}
		flock( $lock, LOCK_EX );

		$meta_path = $session_dir . '/meta.json';
		$meta      = array();
		if ( file_exists( $meta_path ) ) {
			$decoded = json_decode( (string) file_get_contents( $meta_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		if ( $meta ) {
			if ( (int) $meta['total_size'] !== $total_size || (int) $meta['chunks'] !== $chunks ) {
				flock( $lock, LOCK_UN );
				fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return new WP_Error( 'nexura_chunk', __( 'Upload session does not match this file.', 'nexura-upload-limits-manager' ) );
			}
		} else {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$meta_path,
				wp_json_encode(
					array(
						'total_size' => $total_size,
						'chunks'     => $chunks,
					)
				)
			);
		}

		$part = $session_dir . '/' . $chunk . '.part';
		if ( ! file_exists( $part ) ) {
			$moved = rename( $tmp_name, $part ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( ! $moved ) {
				$moved = copy( $tmp_name, $part ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			}
			if ( ! $moved ) {
				flock( $lock, LOCK_UN );
				fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return new WP_Error( 'nexura_chunk', __( 'Could not store this part of the file.', 'nexura-upload-limits-manager' ) );
			}
		}

		$received = 0;
		$complete = true;
		for ( $i = 0; $i < $chunks; $i++ ) {
			$piece = $session_dir . '/' . $i . '.part';
			if ( ! file_exists( $piece ) ) {
				$complete = false;
				continue;
			}
			$received += (int) filesize( $piece );
		}

		if ( $received > $total_size ) {
			wp_delete_file( $part );
			flock( $lock, LOCK_UN );
			fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'nexura_chunk', __( 'Uploaded size does not match the file.', 'nexura-upload-limits-manager' ) );
		}

		if ( ! $complete ) {
			flock( $lock, LOCK_UN );
			fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array( 'complete' => false );
		}

		if ( $received !== $total_size ) {
			flock( $lock, LOCK_UN );
			fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'nexura_chunk', __( 'Uploaded size does not match the file.', 'nexura-upload-limits-manager' ) );
		}

		$claim = $session_dir . '/claimed';
		if ( file_exists( $claim ) ) {
			flock( $lock, LOCK_UN );
			fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'nexura_finalizing', __( 'Upload is being finalized.', 'nexura-upload-limits-manager' ) );
		}
		file_put_contents( $claim, '1' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$merged = $session_dir . '/merged.bin';
		if ( ! file_exists( $merged ) ) {
			$merged_result = self::merge_chunks( $session_dir, $chunks, $merged );
			if ( is_wp_error( $merged_result ) ) {
				wp_delete_file( $claim );
				flock( $lock, LOCK_UN );
				fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return $merged_result;
			}
		}

		flock( $lock, LOCK_UN );
		fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'complete' => true,
			'path'     => $merged,
			'dir'      => $session_dir,
		);
	}

	/**
	 * JSON error for a chunk request. Finalizing is marked so the browser can wait and retry.
	 *
	 * @param WP_Error $error Error from the chunk handler.
	 */
	private static function send_chunk_error( $error ) {
		$data = array( 'message' => $error->get_error_message() );
		if ( 'nexura_finalizing' === $error->get_error_code() ) {
			$data['code'] = 'finalizing';
		}
		wp_send_json_error( $data );
	}

	/**
	 * Stable session id for this user and file.
	 *
	 * @param string $kind plugin, theme, or media.
	 * @return string
	 */
	private static function session_id_from_request( $kind ) {
		$posted = self::posted_chunk();
		return self::session_id_for( $kind, $posted['resume'] );
	}

	/**
	 * Stable session id for this user, kind, and resume token.
	 *
	 * @param string $kind   plugin, theme, or media.
	 * @param string $resume Client resume token.
	 * @return string
	 */
	private static function session_id_for( $kind, $resume ) {
		$resume = sanitize_text_field( (string) $resume );
		if ( '' === $resume || strlen( $resume ) > 200 ) {
			return '';
		}
		$kind = preg_replace( '/[^a-z]/', '', (string) $kind );
		return 'nexura_' . md5( get_current_user_id() . '|' . $kind . '|' . $resume );
	}

	/**
	 * Payload already produced for this file, so a retry does not create it again.
	 *
	 * @param string $kind plugin, theme, or media.
	 * @return array|null
	 */
	private static function finished_payload( $kind ) {
		$session_id = self::session_id_from_request( $kind );
		if ( '' === $session_id ) {
			return null;
		}
		$cached = get_transient( 'nexura_done_' . $session_id );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Remember a finished upload for a short retry window.
	 *
	 * @param string $kind    plugin, theme, or media.
	 * @param array  $payload JSON payload.
	 */
	private static function remember_payload( $kind, $payload ) {
		$session_id = self::session_id_from_request( $kind );
		if ( '' !== $session_id ) {
			set_transient( 'nexura_done_' . $session_id, $payload, 10 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Cap for one assembled file.
	 *
	 * @return int
	 */
	private static function max_accepted_bytes() {
		$limit = PHP_INT_MAX;
		if ( class_exists( 'Nexura_Upload_Manager_Limits' ) ) {
			$limit = (int) Nexura_Upload_Manager_Limits::get_enforced_per_file_limit();
		}
		if ( $limit <= 0 || PHP_INT_MAX === $limit ) {
			$limit = wp_convert_hr_to_bytes( '10G' );
		}
		return $limit;
	}

	/**
	 * Whether the merged file is a ZIP archive.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	private static function is_zip_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( function_exists( 'wp_zip_file_is_valid' ) ) {
			return wp_zip_file_is_valid( $path );
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return false;
		}
		$magic = fread( $handle, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return in_array( $magic, array( "PK\x03\x04", "PK\x05\x06", "PK\x07\x08" ), true );
	}

	/**
	 * Explain a purchase bundle that is a zip but is not an installable plugin or theme.
	 *
	 * Looks only at the zip listing. It does not extract the archive.
	 *
	 * @param string $path Merged ZIP path.
	 * @param string $type plugin or theme.
	 * @return string Empty when the package looks installable, or ZipArchive is unavailable.
	 */
	private static function package_structure_error( $path, $type ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return __( 'This file is not a valid zip archive.', 'nexura-upload-limits-manager' );
		}

		$has_plugin = false;
		$has_theme  = false;
		$checked    = min( (int) $zip->numFiles, 2000 );

		for ( $i = 0; $i < $checked; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( ! is_string( $name ) || '' === $name || false !== strpos( $name, '../' ) || '/' === substr( $name, -1 ) ) {
				continue;
			}

			$depth = substr_count( trim( $name, '/' ), '/' );
			if ( $depth > 1 ) {
				continue;
			}

			$sample = $zip->getFromIndex( $i, 8192 );
			if ( ! is_string( $sample ) ) {
				continue;
			}

			if ( preg_match( '/\.php$/i', $name ) && preg_match( '/Plugin Name\s*:/i', $sample ) ) {
				$has_plugin = true;
			}
			if ( preg_match( '#(^|/)style\.css$#i', $name ) && preg_match( '/Theme Name\s*:/i', $sample ) ) {
				$has_theme = true;
			}
		}

		$zip->close();

		if ( 'plugin' === $type && ! $has_plugin ) {
			return __( 'This zip does not contain a plugin. Upload only the plugin .zip. Do not upload the full purchase package that also contains documentation or extra folders.', 'nexura-upload-limits-manager' );
		}
		if ( 'theme' === $type && ! $has_theme ) {
			return __( 'This zip does not contain a theme. Upload the theme .zip that includes style.css, not the documentation package.', 'nexura-upload-limits-manager' );
		}

		return '';
	}

	/**
	 * Copy a merged ZIP into the media library the same way core's installer expects.
	 *
	 * @param string $path Merged ZIP path.
	 * @param string $name Original file name.
	 * @param string $type plugin or theme.
	 * @return string|WP_Error Installer URL.
	 */
	private static function stage_package_for_installer( $path, $name, $type ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'nexura_package', $upload_dir['error'] );
		}

		$filename = wp_unique_filename( $upload_dir['path'], $name );
		$dest     = trailingslashit( $upload_dir['path'] ) . $filename;
		$moved    = copy( $path, $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! $moved ) {
			return new WP_Error( 'nexura_package', __( 'Could not prepare the package for installation.', 'nexura-upload-limits-manager' ) );
		}

		$url        = trailingslashit( $upload_dir['url'] ) . $filename;
		$attachment = array(
			'post_title'     => sanitize_file_name( $name ),
			'post_content'   => $url,
			'post_mime_type' => 'application/zip',
			'guid'           => $url,
			'context'        => 'upgrader',
			'post_status'    => 'private',
		);
		$id         = wp_insert_attachment( $attachment, $dest );
		if ( ! $id || is_wp_error( $id ) ) {
			wp_delete_file( $dest );
			return new WP_Error( 'nexura_package', __( 'Could not prepare the package for installation.', 'nexura-upload-limits-manager' ) );
		}

		wp_schedule_single_event( time() + 2 * HOUR_IN_SECONDS, 'upgrader_scheduled_cleanup', array( $id ) );

		$action = ( 'plugin' === $type ) ? 'upload-plugin' : 'upload-theme';
		$nonce  = ( 'plugin' === $type ) ? 'plugin-upload' : 'theme-upload';

		return add_query_arg(
			array(
				'action'    => $action,
				'package'   => $id,
				'_wpnonce'  => wp_create_nonce( $nonce ),
			),
			self_admin_url( 'update.php' )
		);
	}

	/**
	 * Turn a merged upload into a media attachment and return its REST payload.
	 *
	 * @param string $path   Merged file path.
	 * @param string $name   Original file name.
	 * @param array  $fields Sanitized parent, caption, description, title, and alt text.
	 * @return array|WP_Error
	 */
	private static function create_media_attachment( $path, $name, $fields ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$check = wp_check_filetype_and_ext( $path, $name );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'nexura_media', __( 'Sorry, you are not allowed to upload this file type.', 'nexura-upload-limits-manager' ) );
		}

		$parent = isset( $fields['parent'] ) ? (int) $fields['parent'] : 0;
		if ( $parent && ! current_user_can( 'edit_post', $parent ) ) {
			$parent = 0;
		}

		$post_data = array();
		$caption   = isset( $fields['caption'] ) ? (string) $fields['caption'] : '';
		if ( '' !== $caption ) {
			$post_data['post_excerpt'] = $caption;
		}
		$description = isset( $fields['description'] ) ? (string) $fields['description'] : '';
		if ( '' !== $description ) {
			$post_data['post_content'] = $description;
		}

		$title = isset( $fields['title'] ) ? (string) $fields['title'] : '';
		if ( '' === $title ) {
			$title = null;
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		$id    = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $path,
				'size'     => filesize( $path ),
				'error'    => 0,
			),
			$parent,
			$title,
			$post_data
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$alt_text = isset( $fields['alt_text'] ) ? (string) $fields['alt_text'] : '';
		if ( '' !== $alt_text ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt_text );
		}

		if ( ! class_exists( 'WP_REST_Attachments_Controller' ) ) {
			require_once ABSPATH . 'wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php';
		}

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$request    = new WP_REST_Request( 'GET', '/wp/v2/media/' . $id );
		$request->set_param( 'id', $id );
		$request->set_param( 'context', 'edit' );
		$response = $controller->get_item( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_get_server()->response_to_data( $response, false );
	}
}
