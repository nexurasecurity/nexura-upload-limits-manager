<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$nexura_global_limit = get_option( 'nexura_global_upload_limit', Nexura_Upload_Manager_Limits::DEFAULT_UPLOAD_LIMIT );
$nexura_exec_time    = get_option( 'nexura_max_execution_time', '120' );
$nexura_memory_limit = get_option( 'nexura_memory_limit', '512M' );

$nexura_target_bytes      = ( $nexura_global_limit === 'Unlimited' ) ? wp_convert_hr_to_bytes( '1024G' ) : wp_convert_hr_to_bytes( $nexura_global_limit );
$nexura_real_upload_bytes = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
$nexura_real_post_bytes   = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
$nexura_real_limit        = min( $nexura_real_upload_bytes, $nexura_real_post_bytes );
$nexura_is_blocked        = ( $nexura_real_limit > 0 && $nexura_target_bytes > $nexura_real_limit );


$nexura_size_options = array(
	'10M'   => __( '10 MB', 'nexura-upload-limits-manager' ),
	'20M'   => __( '20 MB', 'nexura-upload-limits-manager' ),
	'40M'   => __( '40 MB', 'nexura-upload-limits-manager' ),
	'64M'   => __( '64 MB', 'nexura-upload-limits-manager' ),
	'128M'  => __( '128 MB', 'nexura-upload-limits-manager' ),
	'256M'  => __( '256 MB', 'nexura-upload-limits-manager' ),
	'512M'  => __( '512 MB', 'nexura-upload-limits-manager' ),
	'1G'    => __( '1 GB', 'nexura-upload-limits-manager' ),
	'2G'    => __( '2 GB', 'nexura-upload-limits-manager' ),
	'5G'    => __( '5 GB', 'nexura-upload-limits-manager' ),
	'10G'   => __( '10 GB', 'nexura-upload-limits-manager' ),
	'Unlimited' => __( 'Unlimited', 'nexura-upload-limits-manager' ),
);

$nexura_memory_options = array(
	'128M' => __( '128 MB', 'nexura-upload-limits-manager' ),
	'256M' => __( '256 MB', 'nexura-upload-limits-manager' ),
	'512M' => __( '512 MB', 'nexura-upload-limits-manager' ),
	'1024M'=> __( '1 GB', 'nexura-upload-limits-manager' ),
	'2048M'=> __( '2 GB', 'nexura-upload-limits-manager' ),
	'4096M'=> __( '4 GB', 'nexura-upload-limits-manager' ),
	'-1'   => __( 'Unlimited', 'nexura-upload-limits-manager' ),
);
?>
<h2 style="margin-top:0;"><?php esc_html_e( 'Apply Upload Limit for All Users', 'nexura-upload-limits-manager' ); ?></h2>

<?php if ( $nexura_is_blocked ) : ?>
<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 15px; margin-bottom: 20px; border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
	<h4 style="margin: 0 0 8px; font-size: 14px; color: #1d2327; display: flex; align-items: center; gap: 6px;">
		<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Host limit stays low. Uploads still go through.', 'nexura-upload-limits-manager' ); ?>
	</h4>
	<p style="margin: 0 0 8px; font-size: 13px; color: #3c434a;">
		<?php printf(
			/* translators: 1: plugin upload limit, 2: actual server limit */
			wp_kses_post( __( 'The plugin limit is <strong>%1$s</strong>. This server still reports a PHP limit of <strong>%2$s</strong>.', 'nexura-upload-limits-manager' ) ),
			esc_html( $nexura_global_limit ),
			esc_html( ini_get( 'upload_max_filesize' ) )
		); ?>
	</p>
	<p style="margin: 0; font-size: 13px; color: #3c434a;">
		<?php esc_html_e( 'Images, videos, other media files, theme ZIPs, and plugin ZIPs are sent in small parts, so that PHP limit does not block the upload. The finished file still has to fit on disk.', 'nexura-upload-limits-manager' ); ?>
	</p>
</div>
<?php endif; ?>

<div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
	<div style="flex: 1.5; min-width: 350px;">
		<form method="post" action="">
			<?php wp_nonce_field( 'nexura_upload_global_nonce' ); ?>
			<table class="form-table" style="margin-top: 0;">
				<tr>
					<th scope="row" style="padding-top: 0;"><label for="global_upload_limit"><?php esc_html_e( 'Site Global Limit', 'nexura-upload-limits-manager' ); ?></label></th>
					<td style="padding-top: 0;">
						<select name="global_upload_limit" id="global_upload_limit" style="width: 100%; max-width: 300px;">
							<?php foreach ( $nexura_size_options as $nexura_val => $nexura_label ) : ?>
								<option value="<?php echo esc_attr( trim($nexura_val) ); ?>" <?php selected( trim($nexura_global_limit), trim($nexura_val) ); ?>><?php echo esc_html( $nexura_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="max_execution_time"><?php esc_html_e( 'Execution Time', 'nexura-upload-limits-manager' ); ?></label></th>
					<td>
						<input type="number" name="max_execution_time" id="max_execution_time" value="<?php echo esc_attr( $nexura_exec_time ); ?>" style="width: 100%; max-width: 300px;">
						<p class="description"><?php esc_html_e( 'Example: 300, 600, 1800, 3600', 'nexura-upload-limits-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="memory_limit"><?php esc_html_e( 'Memory Limit', 'nexura-upload-limits-manager' ); ?></label></th>
					<td>
						<select name="memory_limit" id="memory_limit" style="width: 100%; max-width: 300px;">
							<?php foreach ( $nexura_memory_options as $nexura_val => $nexura_label ) : ?>
								<option value="<?php echo esc_attr( $nexura_val ); ?>" <?php selected( $nexura_memory_limit, $nexura_val ); ?>><?php echo esc_html( $nexura_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="nexura_upload_save_global" class="button button-primary"><?php esc_html_e( 'Save Changes', 'nexura-upload-limits-manager' ); ?></button>
			</p>
		</form>
	</div>
	<?php include NEXURA_UPLOAD_MANAGER_DIR . 'includes/admin/views/storage-analysis.php'; ?>
	<div class="nexura-faq-box" style="grid-column: 1 / -1;">
		<h4><?php esc_html_e( 'Q: I need advanced security for my uploaded files, what should I use?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php echo wp_kses_post( __( 'A: We highly recommend installing our <strong>Nexura Security</strong> WAF from the right sidebar. It automatically scans uploads for malware and protects your site.', 'nexura-upload-limits-manager' ) ); ?></p>
	</div>
</div>
<hr style="margin: 30px 0 20px; border: 0; border-top: 1px solid #dcdcde;">

<h3 style="margin-bottom: 15px;"><?php esc_html_e( 'Frequently Asked Questions', 'nexura-upload-limits-manager' ); ?></h3>
<div class="nexura-faq-grid">
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: Does Nexura Upload Limits Manager change my server settings?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php esc_html_e( 'A: Yes! Our plugin automatically attempts to update your .htaccess and .user.ini files to bypass standard PHP limits.', 'nexura-upload-limits-manager' ); ?></p>
	</div>
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: Why is my limit not increasing on WP Engine, Kinsta, or AWS?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php esc_html_e( "A: Some Managed WordPress Hosts (like WP Engine, Kinsta) strictly block plugins from changing PHP limits via .htaccess or .user.ini for security reasons. If your limit doesn't change here, you must contact your hosting support to increase it from their server dashboard.", 'nexura-upload-limits-manager' ); ?></p>
	</div>
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: What is the recommended maximum execution time?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php esc_html_e( 'A: If you are uploading large files (e.g. 5GB or 10GB), we recommend setting the execution time between 600 to 1800 seconds so the upload doesn\'t timeout.', 'nexura-upload-limits-manager' ); ?></p>
	</div>
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: Why don’t changes take effect immediately?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php echo wp_kses_post( __( 'A: Server caching or PHP-FPM might delay the new limits. You can clear your cache or check the <strong>System Status</strong> tab to see the live limits.', 'nexura-upload-limits-manager' ) ); ?></p>
	</div>
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: Can I truly upload Unlimited file sizes?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php esc_html_e( 'A: The plugin removes WordPress limitations, but your physical server disk space and hosting provider\'s absolute hard limits (like Nginx client_max_body_size) may still apply.', 'nexura-upload-limits-manager' ); ?></p>
	</div>
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: How do Role-based Storage Quotas work?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php esc_html_e( "A: You can assign specific upload file size limits and maximum total storage quotas for each user role (e.g., Authors can upload a max of 10MB per file and are limited to 500MB total storage). The plugin tracks each user's uploaded media size and prevents further uploads if they exceed their quota.", 'nexura-upload-limits-manager' ); ?></p>
	</div>
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: Can I set storage limits for individual users?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php esc_html_e( "A: Yes! You can override role-based limits by assigning custom quotas to specific users. Go to Upload Manager → User Quotas, select a user from the dropdown, and define their unique storage limit.", 'nexura-upload-limits-manager' ); ?></p>
	</div>
	<div class="nexura-faq-box">
		<h4><?php esc_html_e( 'Q: What does the "Recalculate Storage" button do?', 'nexura-upload-limits-manager' ); ?></h4>
		<p><?php esc_html_e( "A: If you manually delete files directly from your server via FTP, or if the storage analytics ever become out of sync, clicking this button will perform a deep scan of your media library and update the database to reflect your exact physical disk usage.", 'nexura-upload-limits-manager' ); ?></p>
	</div>
</div>
