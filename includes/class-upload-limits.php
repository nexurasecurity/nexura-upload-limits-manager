<?php
/**
 * Upload Limits Class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nexura_Upload_Manager_Limits {

	/**
	 * Per-file limit used before an admin saves a custom value.
	 * Chunked uploads follow this, so a host locked at 2 MB does not block the file.
	 */
	const DEFAULT_UPLOAD_LIMIT = '512M';

	public static function init() {
		add_filter( 'upload_size_limit', array( __CLASS__, 'filter_upload_size_limit' ), 999 );
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'check_storage_quota' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( __CLASS__, 'check_storage_quota' ) );
		add_filter( 'image_memory_limit', array( __CLASS__, 'filter_memory_limit' ) );
		add_filter( 'admin_memory_limit', array( __CLASS__, 'filter_memory_limit' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );
		self::apply_runtime_limits();
	}

	/**
	 * Memory value WordPress should request for image work and admin screens.
	 *
	 * Uses the image_memory_limit and admin_memory_limit filters from core.
	 * A host that locks memory_limit will ignore the request.
	 *
	 * @param int|string $limit Current limit.
	 * @return int|string
	 */
	public static function filter_memory_limit( $limit ) {
		$configured = get_option( 'nexura_memory_limit', '512M' );
		if ( ! is_string( $configured ) || '' === $configured ) {
			return $limit;
		}
		if ( '-1' === $configured ) {
			return '-1';
		}

		$configured_bytes = wp_convert_hr_to_bytes( $configured );
		$current_bytes    = wp_convert_hr_to_bytes( $limit );
		if ( $configured_bytes > $current_bytes ) {
			return $configured;
		}

		return $limit;
	}

	/**
	 * Raise memory and time limits for admin uploads.
	 *
	 * upload_max_filesize cannot be changed mid-request. Chunking covers that limit.
	 */
	public static function apply_runtime_limits() {
		if ( ! is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$exec_time    = (int) get_option( 'nexura_max_execution_time', 120 );
		$memory_limit = get_option( 'nexura_memory_limit', '512M' );

		if ( $exec_time < 30 ) {
			$exec_time = 30;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Extends this admin request so a large upload can finish.
			@set_time_limit( $exec_time );
		}

		if ( function_exists( 'ini_set' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Same request-time extension as set_time_limit().
			@ini_set( 'max_execution_time', (string) $exec_time );
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Lets a slow upload body finish arriving.
			@ini_set( 'max_input_time', (string) $exec_time );
			if ( is_string( $memory_limit ) && '' !== $memory_limit ) {
				// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Raises memory for image processing when the host allows it.
				@ini_set( 'memory_limit', $memory_limit );
			}
		}
	}

	/**
	 * Save limits settings.
	 */
	public static function save_settings() {
		// Security: Verify user has permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['nexura_upload_save_global'] ) && check_admin_referer( 'nexura_upload_global_nonce' ) ) {
			
			$global_limit = isset( $_POST['global_upload_limit'] ) ? sanitize_text_field( wp_unslash( $_POST['global_upload_limit'] ) ) : '40M';
			$exec_time    = isset( $_POST['max_execution_time'] ) ? intval( wp_unslash( $_POST['max_execution_time'] ) ) : 120;
			$memory_limit = isset( $_POST['memory_limit'] ) ? sanitize_text_field( wp_unslash( $_POST['memory_limit'] ) ) : '512M';

			$allowed_global = array( '10M', '20M', '40M', '64M', '128M', '256M', '512M', '1G', '2G', '5G', '10G', 'Unlimited' );
			$allowed_memory = array( '128M', '256M', '512M', '1024M', '2048M', '4096M', '-1' );

			if ( ! in_array( $global_limit, $allowed_global, true ) || ! in_array( $memory_limit, $allowed_memory, true ) ) {
				add_settings_error( 'nexura_upload_messages', 'nexura_upload_message_error', __( 'Invalid upload or memory limit.', 'nexura-upload-limits-manager' ), 'error' );
				return;
			}

			if ( $exec_time < 30 ) {
				$exec_time = 30;
			}
			if ( $exec_time > 86400 ) {
				$exec_time = 86400;
			}

			update_option( 'nexura_global_upload_limit', $global_limit );
			update_option( 'nexura_max_execution_time', $exec_time );
			update_option( 'nexura_memory_limit', $memory_limit );

			if ( is_multisite() && ! is_super_admin() ) {
				add_settings_error( 'nexura_upload_messages', 'nexura_upload_message', __( 'Global settings saved for this site. Only a network super admin can change server PHP files.', 'nexura-upload-limits-manager' ), 'updated' );
				return;
			}

			$config_success = self::write_server_config( $global_limit, $exec_time, $memory_limit );

			if ( $config_success ) {
				add_settings_error( 'nexura_upload_messages', 'nexura_upload_message', __( 'Global settings saved and server configuration updated.', 'nexura-upload-limits-manager' ), 'updated' );
			} else {
				add_settings_error( 'nexura_upload_messages', 'nexura_upload_message_error', __( 'Global settings saved, but server configuration files (.htaccess / .user.ini) are not writable. Please update them manually.', 'nexura-upload-limits-manager' ), 'error' );
			}
		}

		if ( isset( $_POST['nexura_upload_save_roles'] ) && check_admin_referer( 'nexura_upload_roles_nonce' ) ) {
			$roles = get_editable_roles();
			$role_limits = array();
			$role_quotas = array();

			foreach ( $roles as $role_key => $role_info ) {
				if ( isset( $_POST[ 'role_limit_' . $role_key ] ) ) {
					$clean_limit = self::sanitize_size_value( sanitize_text_field( wp_unslash( $_POST[ 'role_limit_' . $role_key ] ) ), true );
					if ( '' === $clean_limit ) {
						add_settings_error( 'nexura_upload_messages', 'nexura_upload_message_error', __( 'Invalid role upload limit.', 'nexura-upload-limits-manager' ), 'error' );
						return;
					}
					$role_limits[ $role_key ] = $clean_limit;
				}
				if ( isset( $_POST[ 'role_quota_' . $role_key ] ) ) {
					$clean_quota = self::sanitize_size_value( sanitize_text_field( wp_unslash( $_POST[ 'role_quota_' . $role_key ] ) ), true );
					if ( '' === $clean_quota ) {
						add_settings_error( 'nexura_upload_messages', 'nexura_upload_message_error', __( 'Invalid role storage quota.', 'nexura-upload-limits-manager' ), 'error' );
						return;
					}
					$role_quotas[ $role_key ] = $clean_quota;
				}
			}

			$disk_total = @disk_total_space( ABSPATH );
			if ( $disk_total !== false && $disk_total > 0 ) {
				$exceeds_disk = false;
				foreach ( $role_quotas as $role_key => $quota_val ) {
					if ( $quota_val !== 'Unlimited' && $quota_val !== 'Default' && ! empty( $quota_val ) ) {
						$quota_bytes = wp_convert_hr_to_bytes( $quota_val );
						if ( $quota_bytes > $disk_total ) {
							$exceeds_disk = true;
							break;
						}
					}
				}
				
				if ( $exceeds_disk ) {
					add_settings_error( 'nexura_upload_messages', 'nexura_upload_message_error', __( 'Error: A Role Quota cannot exceed your total hosting storage space.', 'nexura-upload-limits-manager' ), 'error' );
					return;
				}
			}

			update_option( 'nexura_role_upload_limits', $role_limits );
			update_option( 'nexura_role_storage_quotas', $role_quotas );
			add_settings_error( 'nexura_upload_messages', 'nexura_upload_message', __( 'Role-based limits and quotas saved successfully.', 'nexura-upload-limits-manager' ), 'updated' );
		}
	}

	/**
	 * Get the effective per-file upload limit for a given user.
	 * 
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return int Max file size in bytes. 0 if no custom limit is set (use WP default).
	 */
	public static function get_effective_per_file_limit( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id ) {
			$user = get_userdata( $user_id );
			$role_limits = get_option( 'nexura_role_upload_limits', array() );
			$max_limit_bytes = 0;
			$has_role_limit = false;
			$has_unlimited = false;
			
			if ( $user ) {
				foreach ( (array) $user->roles as $role ) {
					if ( ! empty( $role_limits[ $role ] ) && $role_limits[ $role ] !== 'Default' ) {
						if ( $role_limits[ $role ] === 'Unlimited' ) {
							$has_unlimited = true;
						} else {
							$limit_bytes = wp_convert_hr_to_bytes( $role_limits[ $role ] );
							if ( $limit_bytes > $max_limit_bytes ) {
								$max_limit_bytes = $limit_bytes;
							}
						}
						$has_role_limit = true;
					}
				}
			}

			if ( $has_unlimited ) {
				return PHP_INT_MAX;
			}

			if ( $has_role_limit ) {
				return $max_limit_bytes;
			}
		}

		// Fallback to the saved global limit. Until an admin saves one, use the plugin default
		// so chunked uploads are not stuck on the host's 2 MB PHP limit.
		$global_limit = get_option( 'nexura_global_upload_limit', self::DEFAULT_UPLOAD_LIMIT );
		if ( ! empty( $global_limit ) && $global_limit !== 'Default' ) {
			if ( $global_limit === 'Unlimited' ) {
				return PHP_INT_MAX;
			}
			return wp_convert_hr_to_bytes( $global_limit );
		}

		return 0;
	}

	/**
	 * Per-file cap used by the chunk uploader.
	 *
	 * When no plugin limit is saved, this falls back to WordPress's current max so chunking cannot skip it.
	 *
	 * @param int $user_id Optional user ID.
	 * @return int
	 */
	public static function get_enforced_per_file_limit( $user_id = 0 ) {
		$limit = self::get_effective_per_file_limit( $user_id );
		if ( $limit <= 0 ) {
			$limit = (int) wp_max_upload_size();
		}
		return $limit;
	}

	/**
	 * Filter the upload size limit for WordPress.
	 */
	public static function filter_upload_size_limit( $size ) {
		$limit = self::get_effective_per_file_limit();
		if ( $limit > 0 ) {
			return $limit;
		}
		return $size;
	}

	/**
	 * Check system disk space (runs via cron).
	 */
	public static function check_disk_space() {
		$disk_total = @disk_total_space( ABSPATH );
		$disk_free  = @disk_free_space( ABSPATH );
		if ( $disk_total !== false && $disk_free !== false && $disk_total > 0 ) {
			$disk_used    = $disk_total - $disk_free;
			$disk_percent = ( $disk_used / $disk_total ) * 100;
			
			if ( $disk_percent >= 90 ) {
				$last_sent = get_option( 'nexura_90_alert_sent', 0 );
				// Only send once every 7 days (604800 seconds)
				if ( time() - $last_sent > 604800 ) {
					$admin_email = get_option( 'admin_email' );
					$subject     = 'Server Storage Alert: 90% Full';
					$message     = sprintf(
						"Hello,\n\nYour server's total disk space is running low (%.1f%% used).\nUsed: %s / %s\n\nPlease upgrade your hosting plan to avoid downtime.",
						$disk_percent,
						size_format( $disk_used ),
						size_format( $disk_total )
					);
					wp_mail( $admin_email, $subject, $message );
					update_option( 'nexura_90_alert_sent', time() );
				}
			}
		}
	}

	/**
	 * Normalize a size string from settings or the user-quota field.
	 *
	 * Accepts 500M, 5G, 5GB, Unlimited, and Default.
	 *
	 * @param string $value         Raw value.
	 * @param bool   $allow_default Whether Default is valid.
	 * @return string Clean value, or an empty string when invalid.
	 */
	public static function sanitize_size_value( $value, $allow_default = false ) {
		$value = strtoupper( preg_replace( '/\s+/', '', trim( (string) $value ) ) );

		if ( $allow_default && ( '' === $value || 'DEFAULT' === $value ) ) {
			return 'Default';
		}
		if ( 'UNLIMITED' === $value ) {
			return 'Unlimited';
		}
		if ( ! preg_match( '/^(\d+)(KB|MB|GB|TB|K|M|G|T)$/', $value, $matches ) ) {
			return '';
		}

		$number = (int) $matches[1];
		if ( $number <= 0 || $number > 1048576 ) {
			return '';
		}

		$unit = $matches[2];
		$map  = array(
			'KB' => 'K',
			'MB' => 'M',
			'GB' => 'G',
			'TB' => 'T',
		);
		if ( isset( $map[ $unit ] ) ) {
			$unit = $map[ $unit ];
		}

		return $number . $unit;
	}

	/**
	 * Resolve the storage quota that applies to a user.
	 *
	 * A custom quota overrides roles. If any role is Unlimited, that wins over numeric role quotas.
	 *
	 * @param int $user_id User ID.
	 * @return array{bytes:int,label:string,enforced:bool}
	 */
	public static function get_user_quota_state( $user_id ) {
		$unlimited = array(
			'bytes'    => 0,
			'label'    => 'Unlimited',
			'enforced' => false,
		);

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $unlimited;
		}

		$custom_quota = get_user_meta( $user_id, 'nexura_custom_storage_quota', true );
		if ( ! empty( $custom_quota ) && 'Default' !== $custom_quota ) {
			if ( 'Unlimited' === $custom_quota ) {
				return $unlimited;
			}
			$custom_bytes = wp_convert_hr_to_bytes( $custom_quota );
			if ( $custom_bytes > 0 ) {
				return array(
					'bytes'    => $custom_bytes,
					'label'    => $custom_quota,
					'enforced' => true,
				);
			}
		}

		$role_quotas = get_option( 'nexura_role_storage_quotas', array() );
		$max_bytes   = 0;
		$max_label   = '';
		$saw_quota   = false;

		foreach ( (array) $user->roles as $role ) {
			if ( empty( $role_quotas[ $role ] ) || 'Default' === $role_quotas[ $role ] ) {
				continue;
			}
			if ( 'Unlimited' === $role_quotas[ $role ] ) {
				return $unlimited;
			}
			$quota_bytes = wp_convert_hr_to_bytes( $role_quotas[ $role ] );
			if ( $quota_bytes > $max_bytes ) {
				$max_bytes = $quota_bytes;
				$max_label = $role_quotas[ $role ];
			}
			$saw_quota = true;
		}

		if ( $saw_quota && $max_bytes > 0 ) {
			return array(
				'bytes'    => $max_bytes,
				'label'    => $max_label,
				'enforced' => true,
			);
		}

		return $unlimited;
	}

	/**
	 * Get the storage quota for a specific user in bytes.
	 *
	 * @param int $user_id User ID.
	 * @return int 0 if unlimited or no quota, otherwise quota in bytes.
	 */
	public static function get_user_quota( $user_id ) {
		$state = self::get_user_quota_state( $user_id );
		return $state['enforced'] ? (int) $state['bytes'] : 0;
	}

	/**
	 * Check storage quota before upload.
	 */
	public static function check_storage_quota( $file ) {
		if ( ! is_user_logged_in() ) {
			return $file;
		}

		// $_FILES always includes an error key. 0 means the upload itself succeeded.
		if ( ! empty( $file['error'] ) ) {
			return $file;
		}

		// Check physical hosting storage first
		$disk_total = @disk_total_space( ABSPATH );
		$disk_free  = @disk_free_space( ABSPATH );
		if ( $disk_total !== false && $disk_free !== false && $disk_total > 0 ) {
			$disk_used = $disk_total - $disk_free;
			$disk_percent = ( $disk_used / $disk_total ) * 100;
			
			// Block uploads if server storage is 99% full or less than 50MB free
			if ( $disk_percent >= 99 || $disk_free < 52428800 ) {
				$file['error'] = __( 'Admin hosting storage is full. Please contact the administrator.', 'nexura-upload-limits-manager' );
				return $file;
			}
		}

		$user = wp_get_current_user();

		$per_file = self::get_effective_per_file_limit( $user->ID );
		$incoming_size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $incoming_size <= 0 && ! empty( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) && file_exists( $file['tmp_name'] ) ) {
			$incoming_size = (int) filesize( $file['tmp_name'] );
		}
		if ( $per_file > 0 && $per_file < PHP_INT_MAX && $incoming_size > $per_file ) {
			$file['error'] = sprintf(
				/* translators: %s: maximum file size. */
				__( 'This file is larger than your upload limit (%s).', 'nexura-upload-limits-manager' ),
				size_format( $per_file )
			);
			return $file;
		}

		$state = self::get_user_quota_state( $user->ID );

		if ( $state['enforced'] && $state['bytes'] > 0 ) {
			$max_quota_bytes = (int) $state['bytes'];
			require_once NEXURA_UPLOAD_MANAGER_DIR . 'includes/class-activity-logger.php';
			$current_usage = Nexura_Upload_Activity_Logger::get_user_storage_usage( $user->ID );
			$total_projected = $current_usage + $incoming_size;

			if ( $total_projected > $max_quota_bytes ) {
				$file['error'] = __( 'You have exceeded your storage quota. Please upgrade or delete old files.', 'nexura-upload-limits-manager' );
				return $file;
			}

			// Check 85% for email alert
			$percentage = min( 100, ( $total_projected / $max_quota_bytes ) * 100 );
			if ( $percentage >= 85 ) {
				$alert_sent = get_user_meta( $user->ID, '_nexura_85_alert_sent', true );
				if ( empty( $alert_sent ) ) {
					$admin_email = get_option( 'admin_email' );
					$user_email  = $user->user_email;
					$subject     = 'Storage Quota Alert: 85% Reached';
					$message     = sprintf(
						"Hello,\n\nUser %s (%s) has reached %d%% of their storage quota.\nUsed: %s / %s",
						$user->user_login,
						$user_email,
						$percentage,
						size_format( $total_projected ),
						size_format( $max_quota_bytes )
					);
					wp_mail( $admin_email, $subject, $message );
					wp_mail( $user_email, $subject, $message );

					update_user_meta( $user->ID, '_nexura_85_alert_sent', current_time('mysql') );
				}
			}
		}

		return $file;
	}

	/**
	 * Write rules to .htaccess or .user.ini.
	 */
	private static function write_server_config( $upload_limit, $exec_time, $memory_limit ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return false;
		}

		if ( is_multisite() && ! is_super_admin() ) {
			return false;
		}

		$home_path = get_home_path();

		// Only write values that match the settings allowlist.
		$is_valid_upload = ( 'Unlimited' === $upload_limit || (bool) preg_match( '/^\d+(K|M|G|T)$/', (string) $upload_limit ) );
		$is_valid_memory = ( '-1' === (string) $memory_limit || (bool) preg_match( '/^\d+(K|M|G)$/', (string) $memory_limit ) );
		$exec_time       = (int) $exec_time;

		// Calculate post_max_size
		$post_max_size = $upload_limit;
		if ( $is_valid_upload ) {
			if ( $upload_limit === 'Unlimited' ) {
				$upload_limit = '1024G';
				$post_max_size = '1280G';
			} else {
				$num = (int) $upload_limit;
				$unit = strtoupper( substr( $upload_limit, -1 ) );
				if ( $num > 0 ) {
					$post_num = ceil( $num * 1.25 );
					$post_max_size = $post_num . $unit;
				}
			}
		}

		// Check if PHP is running as an Apache module (otherwise php_value in .htaccess causes 500 Error)
		$is_apache_mod  = ( strpos( php_sapi_name(), 'apache' ) !== false );
		$htaccess_ok    = ! $is_apache_mod;
		$user_ini_ok    = false;

		if ( $is_apache_mod ) {
			// .htaccess (Apache)
			$htaccess_file = $home_path . '.htaccess';
			$htaccess_backup = $htaccess_file . '_nexura_backup';
			
			// Backup original .htaccess if it exists and backup doesn't exist yet
			if ( $wp_filesystem->exists( $htaccess_file ) && ! $wp_filesystem->exists( $htaccess_backup ) ) {
				$wp_filesystem->copy( $htaccess_file, $htaccess_backup );
			}
			$htaccess_rules = "\n# BEGIN Nexura Upload Manager\n";
			if ( $is_valid_upload ) {
				$htaccess_rules .= "php_value upload_max_filesize {$upload_limit}\n";
				$htaccess_rules .= "php_value post_max_size {$post_max_size}\n";
			}
			if ( $exec_time > 0 ) {
				$htaccess_rules .= "php_value max_execution_time {$exec_time}\n";
				$htaccess_rules .= "php_value max_input_time {$exec_time}\n";
			}
			if ( $is_valid_memory ) {
				$htaccess_rules .= "php_value memory_limit {$memory_limit}\n";
			}
			$htaccess_rules .= "# END Nexura Upload Manager\n";

			$content = '';
			if ( $wp_filesystem->exists( $htaccess_file ) ) {
				$content = $wp_filesystem->get_contents( $htaccess_file );
				$content = preg_replace( '/\n?# BEGIN Nexura Upload Manager.*?# END Nexura Upload Manager\n?/s', '', $content );
			}
			
			if ( $wp_filesystem->is_writable( $htaccess_file ) || ( ! $wp_filesystem->exists( $htaccess_file ) && $wp_filesystem->is_writable( $home_path ) ) ) {
				$htaccess_ok = (bool) $wp_filesystem->put_contents( $htaccess_file, rtrim( $content ) . $htaccess_rules );
			} else {
				$htaccess_ok = false;
			}
		}

		// .user.ini (Nginx/LiteSpeed/CGI)
		$user_ini_file = $home_path . '.user.ini';
		$user_ini_backup = $user_ini_file . '_nexura_backup';
		
		// Backup original .user.ini if it exists and backup doesn't exist yet
		if ( $wp_filesystem->exists( $user_ini_file ) && ! $wp_filesystem->exists( $user_ini_backup ) ) {
			$wp_filesystem->copy( $user_ini_file, $user_ini_backup );
		}

		$user_ini_rules = "\n; BEGIN Nexura Upload Manager\n";
		if ( $is_valid_upload ) {
			$user_ini_rules .= "upload_max_filesize = {$upload_limit}\n";
			$user_ini_rules .= "post_max_size = {$post_max_size}\n";
		}
		if ( $exec_time > 0 ) {
			$user_ini_rules .= "max_execution_time = {$exec_time}\n";
			$user_ini_rules .= "max_input_time = {$exec_time}\n";
		}
		if ( $is_valid_memory ) {
			$user_ini_rules .= "memory_limit = {$memory_limit}\n";
		}
		$user_ini_rules .= "; END Nexura Upload Manager\n";

		$content_ini = '';
		if ( $wp_filesystem->exists( $user_ini_file ) ) {
			$content_ini = $wp_filesystem->get_contents( $user_ini_file );
			$content_ini = preg_replace( '/\n?; BEGIN Nexura Upload Manager.*?; END Nexura Upload Manager\n?/s', '', $content_ini );
		}
		
		if ( $wp_filesystem->is_writable( $user_ini_file ) || ( ! $wp_filesystem->exists( $user_ini_file ) && $wp_filesystem->is_writable( $home_path ) ) ) {
			$user_ini_ok = (bool) $wp_filesystem->put_contents( $user_ini_file, rtrim( $content_ini ) . $user_ini_rules );
		}

		// Apache module: .htaccess is what PHP actually reads. .user.ini is best-effort.
		if ( $is_apache_mod ) {
			return $htaccess_ok;
		}

		return $user_ini_ok;
	}

	/**
	 * Remove rules from .htaccess and .user.ini on deactivation/uninstall.
	 */
	public static function remove_server_config() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return;
		}

		$home_path = get_home_path();

		// Clean .htaccess (Apache)
		$htaccess_file = $home_path . '.htaccess';
		if ( $wp_filesystem->exists( $htaccess_file ) && $wp_filesystem->is_writable( $htaccess_file ) ) {
			$content = $wp_filesystem->get_contents( $htaccess_file );
			$content = preg_replace( '/\n?# BEGIN Nexura Upload Manager.*?# END Nexura Upload Manager\n?/s', '', $content );
			$wp_filesystem->put_contents( $htaccess_file, rtrim( $content ) . "\n" );
		}

		// Clean .user.ini (Nginx/LiteSpeed/CGI)
		$user_ini_file = $home_path . '.user.ini';
		if ( $wp_filesystem->exists( $user_ini_file ) && $wp_filesystem->is_writable( $user_ini_file ) ) {
			$content = $wp_filesystem->get_contents( $user_ini_file );
			$content = preg_replace( '/\n?; BEGIN Nexura Upload Manager.*?; END Nexura Upload Manager\n?/s', '', $content );
			if ( empty( trim( $content ) ) ) {
				$wp_filesystem->delete( $user_ini_file );
			} else {
				$wp_filesystem->put_contents( $user_ini_file, rtrim( $content ) . "\n" );
			}
		}
	}
}

Nexura_Upload_Manager_Limits::init();
