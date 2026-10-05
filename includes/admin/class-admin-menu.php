<?php
/**
 * Admin Menu & Settings Page Class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nexura_Upload_Manager_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_nexura_install_security', array( $this, 'ajax_install_security' ) );
		add_action( 'wp_ajax_nexura_save_user_quota', array( $this, 'ajax_save_user_quota' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_init', array( $this, 'process_actions' ) );
	}



	/**
	 * Register Admin Menu.
	 */
	public function register_menu() {
		add_menu_page(
			'Upload Manager',
			'Upload Manager',
			'manage_options',
			'nexura-upload-manager',
			array( $this, 'render_page' ),
			'dashicons-upload',
			80
		);
	}

	/**
	 * Enqueue assets — upload-notice.js on all admin pages,
	 * heavy assets (Chart.js, admin.js) only on plugin's own page.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {

		// --- Global: upload badge on admin pages for users who can change the limit ---
		if ( current_user_can( 'manage_options' ) ) {
			wp_enqueue_script(
				'nexura-upload-notice',
				NEXURA_UPLOAD_MANAGER_URL . 'assets/js/upload-notice.js',
				array( 'jquery' ),
				NEXURA_UPLOAD_MANAGER_VERSION,
				true
			);
			wp_localize_script(
				'nexura-upload-notice',
				'nexura_upload_notice',
				array(
					'settings_url'  => admin_url( 'admin.php?page=nexura-upload-manager&tab=global' ),
					'label_btn'     => esc_html__( 'Increase Limit', 'nexura-upload-limits-manager' ),
					'label_tooltip' => esc_html__( 'Click to increase your maximum upload file size', 'nexura-upload-limits-manager' ),
				)
			);
		}

		// --- Plugin page only: Chart.js, CSS, admin.js ---
		if ( 'toplevel_page_nexura-upload-manager' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'chart-js', NEXURA_UPLOAD_MANAGER_URL . 'assets/js/chart.min.js', array(), '4.4.0', true );
		$css_ver = filemtime( NEXURA_UPLOAD_MANAGER_DIR . 'assets/css/admin.css' );
		wp_enqueue_style( 'nexura-upload-admin', NEXURA_UPLOAD_MANAGER_URL . 'assets/css/admin.css', array(), $css_ver );

		$stats = Nexura_Upload_Activity_Logger::get_storage_stats();

		$admin_vars = array(
			'install_nonce'  => wp_create_nonce( 'nexura_install_security_nonce' ),
			'settings_url'   => admin_url( 'admin.php?page=nexura-upload-manager' ),
			'chart_labels'   => array(),
			'chart_data_size' => array(),
			'chart_colors'   => array(),
		);

		foreach ( $stats['categories'] as $name => $data ) {
			if ( empty( $data['files'] ) ) {
				continue;
			}
			$admin_vars['chart_labels'][]     = $name;
			$admin_vars['chart_data_size'][]  = $data['size'];
			$admin_vars['chart_colors'][]     = $data['color'];
		}

		$js_ver = filemtime( NEXURA_UPLOAD_MANAGER_DIR . 'assets/js/admin.js' );
		wp_enqueue_script( 'nexura-upload-admin-js', NEXURA_UPLOAD_MANAGER_URL . 'assets/js/admin.js', array( 'jquery' ), $js_ver, true );
		wp_localize_script( 'nexura-upload-admin-js', 'nexura_admin_vars', $admin_vars );
	}

	/**
	 * Register Dashboard Widget for User Storage.
	 */
	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'nexura_user_storage_widget',
			__( 'My Storage Quota', 'nexura-upload-limits-manager' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render Dashboard Widget.
	 */
	public function render_dashboard_widget() {
		$user  = wp_get_current_user();
		$state = Nexura_Upload_Manager_Limits::get_user_quota_state( $user->ID );
		$max_quota_bytes = (int) $state['bytes'];
		$quota_display   = $state['label'];
		$has_quota       = ! empty( $state['enforced'] );

		if ( ! $has_quota ) {
			echo '<p>' . esc_html__( 'You have unlimited storage quota.', 'nexura-upload-limits-manager' ) . '</p>';
			return;
		}

		$current_usage = Nexura_Upload_Activity_Logger::get_user_storage_usage( $user->ID );
		
		$percentage = 0;
		if ( $max_quota_bytes > 0 ) {
			$percentage = ( $current_usage / $max_quota_bytes ) * 100;
		}

		$progress_color = '#46b450';
		if ( $percentage > 70 ) $progress_color = '#ffb900';
		if ( $percentage > 90 ) $progress_color = '#dc3232';
		?>
		<div style="padding: 10px 0;">
			<p style="margin-top:0;"><strong><?php esc_html_e( 'Storage Used:', 'nexura-upload-limits-manager' ); ?></strong> <?php echo esc_html( size_format( $current_usage ) ); ?> / <?php echo esc_html( $quota_display ); ?></p>
			<div style="width: 100%; background: #e5e5e5; border-radius: 3px; height: 15px; overflow: hidden; margin: 10px 0;">
				<div style="width: <?php echo esc_attr( min( 100, $percentage ) ); ?>%; background: <?php echo esc_attr( $progress_color ); ?>; height: 100%; transition: width 0.5s ease;"></div>
			</div>
			<p style="margin-bottom:0; text-align:right;"><small><?php echo esc_html( round( $percentage, 1 ) ); ?>% Used</small></p>
		</div>
		<?php
	}

	/**
	 * AJAX handler to install Nexura Security in background.
	 */
	public function ajax_install_security() {
		check_ajax_referer( 'nexura_install_security_nonce', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$plugin_file = 'nexura-security/nexura-security.php';

		// If already installed, just activate it and return!
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			$result = activate_plugin( $plugin_file );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			delete_transient( 'fs_plugin_nexura-security_activated' );
			wp_send_json_success( array( 'message' => 'Activated' ) );
		}

		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$plugin_slug = 'nexura-security';
		$api = plugins_api( 'plugin_information', array( 'slug' => $plugin_slug, 'fields' => array( 'sections' => false ) ) );
		
		if ( is_wp_error( $api ) ) {
			wp_send_json_error( array( 'message' => 'Plugin not found' ) );
		}

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$installed = $upgrader->install( $api->download_link );

		if ( true === $installed ) {
			$plugin_file = 'nexura-security/nexura-security.php';
			$result = activate_plugin( $plugin_file );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			// Prevent Freemius from redirecting
			delete_transient( 'fs_plugin_nexura-security_activated' );
			wp_send_json_success( array( 'message' => 'Installed' ) );
		} else {
			$error_msg = is_wp_error( $installed ) ? $installed->get_error_message() : 'Install failed';
			wp_send_json_error( array( 'message' => $error_msg ) );
		}
	}

	/**
	 * AJAX handler to save custom user quota.
	 */
	public function ajax_save_user_quota() {
		check_ajax_referer( 'nexura_upload_users_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$quota   = isset( $_POST['quota'] ) ? sanitize_text_field( wp_unslash( $_POST['quota'] ) ) : '';

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid user ID' ) );
		}

		if ( empty( $quota ) || 'Default' === $quota ) {
			delete_user_meta( $user_id, 'nexura_custom_storage_quota' );
		} else {
			$clean = Nexura_Upload_Manager_Limits::sanitize_size_value( $quota, false );
			if ( '' === $clean ) {
				wp_send_json_error( array( 'message' => 'Enter a size like 500M or 5GB, or Unlimited.' ) );
			}
			if ( 'Unlimited' !== $clean ) {
				$quota_bytes = wp_convert_hr_to_bytes( $clean );
				$disk_total  = function_exists( 'disk_total_space' ) ? @disk_total_space( ABSPATH ) : false;
				if ( $disk_total && $quota_bytes > $disk_total ) {
					wp_send_json_error( array( 'message' => 'Quota cannot exceed total hosting storage.' ) );
				}
			}
			update_user_meta( $user_id, 'nexura_custom_storage_quota', $clean );
		}

		wp_send_json_success( array( 'message' => 'Quota saved successfully' ) );
	}

	/**
	 * Process admin actions before headers are sent.
	 */
	public function process_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'nexura-upload-manager' ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'global';
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) ) {
			$redirect_url = '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $_GET['action'] === 'nexura_refresh_stats' ) {
				check_admin_referer( 'nexura_stats_action' );
				if ( class_exists( 'Nexura_Upload_Activity_Logger' ) ) {
					Nexura_Upload_Activity_Logger::clear_stats_cache();
				}
				$redirect_url = admin_url( 'admin.php?page=nexura-upload-manager&tab=' . $current_tab );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( $_GET['action'] === 'nexura_recalculate_storage' ) {
				check_admin_referer( 'nexura_stats_action' );
				if ( class_exists( 'Nexura_Upload_Activity_Logger' ) ) {
					Nexura_Upload_Activity_Logger::recalculate_storage();
				}
				$redirect_url = admin_url( 'admin.php?page=nexura-upload-manager&tab=' . $current_tab );
			}

			if ( ! empty( $redirect_url ) ) {
				if ( headers_sent() ) {
					echo '<script type="text/javascript">window.location.href="' . esc_url_raw( $redirect_url ) . '";</script>';
				} else {
					wp_safe_redirect( $redirect_url );
				}
				exit;
			}
		}
	}

	/**
	 * Render the Settings Page.
	 */
	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'global';

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Nexura Upload Limits Manager - Maximum Upload File Size', 'nexura-upload-limits-manager' ); ?></h1>
			<hr class="wp-header-end">
			<?php settings_errors( 'nexura_upload_messages' ); ?>
			
			<div id="poststuff">
				<div id="post-body" class="metabox-holder <?php echo esc_attr( $current_tab === 'support' ? 'columns-1' : 'columns-2' ); ?>">
					
					<!-- Main Content -->
					<div id="post-body-content">
						<h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
							<a href="?page=nexura-upload-manager&tab=global" class="nav-tab <?php echo $current_tab === 'global' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Global Limit', 'nexura-upload-limits-manager' ); ?></a>
							<a href="?page=nexura-upload-manager&tab=roles" class="nav-tab <?php echo $current_tab === 'roles' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Role-Based Limit', 'nexura-upload-limits-manager' ); ?></a>
							<a href="?page=nexura-upload-manager&tab=users" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'User Quotas', 'nexura-upload-limits-manager' ); ?></a>
							<a href="?page=nexura-upload-manager&tab=status" class="nav-tab <?php echo $current_tab === 'status' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'System Status', 'nexura-upload-limits-manager' ); ?></a>
							<a href="?page=nexura-upload-manager&tab=support" class="nav-tab <?php echo $current_tab === 'support' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Support', 'nexura-upload-limits-manager' ); ?></a>
						</h2>
						
						<?php if ( $current_tab === 'support' ) : ?>
							<?php include NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/views/support.php'; ?>
						<?php else : ?>
							<div class="postbox" style="padding: 20px;">
							<?php
							switch ( $current_tab ) {
								case 'status':
									include NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/views/status.php';
									break;
								case 'users':
									include NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/views/user-quotas.php';
									break;
								case 'roles':
									include NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/views/role-limit.php';
									break;
								default:
									include NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/views/global-limit.php';
									break;
							}
							?>
						</div>
						<?php endif; ?>
					</div>
					
					<?php if ( $current_tab !== 'support' ) : ?>
					<!-- Sidebar -->
					<div id="postbox-container-1" class="postbox-container">
						<?php include NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/views/sidebar-widget.php'; ?>
					</div>
					<?php endif; ?>
					
				</div>
			</div>
		</div>
		<?php
	}
}

new Nexura_Upload_Manager_Admin();
