<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$nexura_stats = Nexura_Upload_Activity_Logger::get_storage_stats();
$nexura_total_label = size_format( (int) $nexura_stats['total_size'], 2 );
?>

<div class="nexura-upload-card" style="flex: 1; min-width: 250px; max-width: 350px;">
	<div class="nexura-upload-header">
		<h3 class="nexura-upload-title"><?php esc_html_e( 'Storage Usage Analysis', 'nexura-upload-limits-manager' ); ?></h3>
		<?php 
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nexura_current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'global'; 
		?>
		<a href="?page=nexura-upload-manager&tab=<?php echo esc_attr($nexura_current_tab); ?>&action=nexura_refresh_stats&_wpnonce=<?php echo esc_attr(wp_create_nonce('nexura_stats_action')); ?>" class="nexura-upload-btn"><?php esc_html_e( 'Refresh', 'nexura-upload-limits-manager' ); ?></a>
	</div>
	
	<p class="nexura-upload-desc">
		<?php esc_html_e( 'Disk space used by the Media Library, including generated image sizes.', 'nexura-upload-limits-manager' ); ?>
	</p>
	
	<div>
		<?php if ( $nexura_stats['total_files'] > 0 ) : ?>
			<?php foreach ( $nexura_stats['categories'] as $nexura_name => $nexura_data ) : 
				if ( $nexura_data['files'] === 0 ) continue;
				$nexura_cat_percent = $nexura_stats['total_size'] > 0 ? round(($nexura_data['size'] / $nexura_stats['total_size']) * 100) : 0;
				$nexura_size_str = size_format( (int) $nexura_data['size'], 2 );
			?>
			<div>
				<div class="nexura-upload-stat-row">
					<span class="nexura-upload-stat-name"><span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo esc_attr( $nexura_data['color'] ); ?>;"></span> <?php echo esc_html( $nexura_name ); ?> <span style="color: #8c8f94; font-weight: normal; font-size: 11px;">(<?php echo esc_html( $nexura_cat_percent ); ?>%)</span></span>
					<span class="nexura-upload-stat-val"><?php echo esc_html( $nexura_size_str ); ?> / <?php echo esc_html( $nexura_data['files'] ); ?> files</span>
				</div>
				<div class="nexura-upload-bar-bg">
					<div class="nexura-upload-bar-fill" style="background: <?php echo esc_attr($nexura_data['color']); ?>; width: <?php echo esc_attr($nexura_cat_percent); ?>%;"></div>
				</div>
			</div>
			<?php endforeach; ?>
		<?php else : ?>
			<p style="color: #8c8f94; font-style: italic; font-size: 13px; text-align: center; margin: 30px 0;"><?php esc_html_e( 'No media files uploaded yet.', 'nexura-upload-limits-manager' ); ?></p>
		<?php endif; ?>
		
		<div class="nexura-upload-total-box">
			<h4 class="nexura-upload-total-val"><?php echo esc_html( $nexura_total_label ); ?></h4>
			<div class="nexura-upload-info-label"><?php esc_html_e( 'Total Uploaded Media', 'nexura-upload-limits-manager' ); ?></div>
			<p style="color: #8c8f94; font-size: 11px; margin: 8px 0 15px 0;"><?php esc_html_e( 'Updated recently', 'nexura-upload-limits-manager' ); ?></p>
			<a href="?page=nexura-upload-manager&tab=<?php echo esc_attr($nexura_current_tab); ?>&action=nexura_recalculate_storage&_wpnonce=<?php echo esc_attr(wp_create_nonce('nexura_stats_action')); ?>" class="button" style="width: 100%; text-align: center;"><?php esc_html_e( 'Recalculate Storage', 'nexura-upload-limits-manager' ); ?></a>
		</div>
	</div>
</div>

<?php 
$nexura_server_status = Nexura_Upload_Manager_Server_Status::get_server_status();

$nexura_has_ram = false;
$nexura_ram_title = 'System RAM';
$nexura_ram_total = 0; $nexura_ram_used = 0; $nexura_ram_percent = 0; $nexura_ram_color = '#46b450';

if ( ! empty( $nexura_server_status['system_ram_usage']['raw_total'] ) ) {
	$nexura_ram_total = $nexura_server_status['system_ram_usage']['raw_total'];
	$nexura_ram_used = $nexura_server_status['system_ram_usage']['raw_used'];
	$nexura_has_ram = true;
} elseif ( ! empty( $nexura_server_status['php_memory_usage']['raw_total'] ) ) {
	$nexura_ram_title = 'PHP Memory';
	$nexura_ram_total = $nexura_server_status['php_memory_usage']['raw_total'];
	$nexura_ram_used = $nexura_server_status['php_memory_usage']['raw_used'];
	$nexura_has_ram = true;
}

if ( $nexura_has_ram ) {
	$nexura_ram_percent = round( ( $nexura_ram_used / $nexura_ram_total ) * 100, 2 );
	if ( $nexura_ram_percent > 70 ) $nexura_ram_color = '#ffb900';
	if ( $nexura_ram_percent > 90 ) $nexura_ram_color = '#dc3232';
}

$nexura_has_disk = false;
$nexura_disk_total = 0; $nexura_disk_used = 0; $nexura_disk_percent = 0; $nexura_disk_color = '#2271b1';
if ( ! empty( $nexura_server_status['system_disk_usage']['raw_total'] ) ) {
	$nexura_disk_total = $nexura_server_status['system_disk_usage']['raw_total'];
	$nexura_disk_used = $nexura_server_status['system_disk_usage']['raw_used'];
	$nexura_disk_percent = round( ( $nexura_disk_used / $nexura_disk_total ) * 100, 2 );
	if ( $nexura_disk_percent > 70 ) $nexura_disk_color = '#ffb900';
	if ( $nexura_disk_percent > 90 ) $nexura_disk_color = '#dc3232';
	$nexura_has_disk = true;
}
?>

<!-- Break to a new row below the main settings form -->
<div style="flex-basis: 100%; height: 0;"></div>

<!-- Full-width Grid Container for System Resources and Role Storages -->
<div style="flex-basis: 100%; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; align-items: start; margin-bottom: 24px;">

<?php if ( $nexura_has_ram || $nexura_has_disk ) : ?>
	<div class="nexura-upload-card" style="margin: 0;">
		<div class="nexura-upload-header" style="margin-bottom: 20px;">
			<h3 class="nexura-upload-title"><?php esc_html_e( 'System Resources', 'nexura-upload-limits-manager' ); ?></h3>
		</div>
		
		<div>
			<?php if ( $nexura_has_ram ) : ?>
			<div style="margin-bottom: 20px;">
				<div class="nexura-upload-stat-row">
					<span class="nexura-upload-stat-name"><span class="dashicons dashicons-dashboard" style="color: #8c8f94;"></span> <?php echo esc_html( $nexura_ram_title ); ?></span>
					<span class="nexura-upload-stat-val"><?php echo esc_html( size_format( $nexura_ram_used ) ); ?> / <?php echo esc_html( size_format( $nexura_ram_total ) ); ?></span>
				</div>
				<div class="nexura-upload-bar-bg">
					<div class="nexura-upload-bar-fill" style="background: <?php echo esc_attr($nexura_ram_color); ?>; width: <?php echo esc_attr($nexura_ram_percent); ?>%;"></div>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $nexura_has_disk ) : ?>
			<div>
				<div class="nexura-upload-stat-row">
					<span class="nexura-upload-stat-name"><span class="dashicons dashicons-database" style="color: #8c8f94;"></span> <?php esc_html_e( 'Disk Usage', 'nexura-upload-limits-manager' ); ?></span>
					<span class="nexura-upload-stat-val"><?php echo esc_html( size_format( $nexura_disk_used ) ); ?> / <?php echo esc_html( size_format( $nexura_disk_total ) ); ?></span>
				</div>
				<div class="nexura-upload-bar-bg" style="margin-bottom: 0;">
					<div class="nexura-upload-bar-fill" style="background: <?php echo esc_attr($nexura_disk_color); ?>; width: <?php echo esc_attr($nexura_disk_percent); ?>%;"></div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<?php
	$nexura_role_quotas = get_option( 'nexura_role_storage_quotas', array() );
	$nexura_role_limits = get_option( 'nexura_role_upload_limits', array() );
	$nexura_wp_roles = wp_roles()->roles;

	foreach ( $nexura_role_quotas as $nexura_role_key => $nexura_quota_val ) {
		$nexura_is_unlimited = ( $nexura_quota_val === 'Unlimited' || empty($nexura_quota_val) || $nexura_quota_val === 'Default' );
		
		// If unlimited, only show if it's administrator, so admin can see their own usage
		if ( $nexura_is_unlimited && $nexura_role_key !== 'administrator' ) {
			continue;
		}

		$nexura_role_name = isset( $nexura_wp_roles[ $nexura_role_key ] ) ? $nexura_wp_roles[ $nexura_role_key ]['name'] : ucfirst( $nexura_role_key );
		$nexura_upload_limit_val = isset( $nexura_role_limits[ $nexura_role_key ] ) ? $nexura_role_limits[ $nexura_role_key ] : 'Default';
		
		$nexura_users = get_users( array( 'role' => $nexura_role_key, 'fields' => 'ID' ) );
		$nexura_total_users = count( $nexura_users );
		
		$nexura_used_bytes = 0;
		if ( $nexura_total_users > 0 ) {
			global $wpdb;
			$nexura_table_name = $wpdb->prefix . 'nexura_upload_logs';
			$nexura_user_ids = implode( ',', array_map( 'intval', $nexura_users ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$nexura_used_bytes = (int) $wpdb->get_var( "SELECT SUM(file_size) FROM `{$nexura_table_name}` WHERE user_id IN ($nexura_user_ids)" );
		}
		
		if ( $nexura_is_unlimited ) {
			$nexura_quota_bytes = 0;
			$nexura_total_quota_bytes = 0;
			$nexura_percent = 0;
			$nexura_quota_display = 'Unlimited';
			$nexura_total_display = 'Unlimited';
			$nexura_color = '#2271b1';
		} else {
			$nexura_quota_bytes = wp_convert_hr_to_bytes( $nexura_quota_val );
			$nexura_total_quota_bytes = $nexura_quota_bytes * $nexura_total_users;
			$nexura_percent = $nexura_total_quota_bytes > 0 ? min(100, round(($nexura_used_bytes / $nexura_total_quota_bytes) * 100, 1)) : 0;
			$nexura_quota_display = $nexura_quota_val;
			$nexura_total_display = size_format( $nexura_total_quota_bytes );
			$nexura_color = '#2271b1';
			if ($nexura_percent > 75) $nexura_color = '#ffb900';
			if ($nexura_percent > 90) $nexura_color = '#dc3232';
		}
	?>
	<div class="nexura-upload-card" style="margin: 0;">
		<div class="nexura-upload-header" style="margin-bottom: 20px;">
			<h3 class="nexura-upload-title">
				<?php
				/* translators: %s: User role name */
				echo esc_html( sprintf( __('%s Role Storage', 'nexura-upload-limits-manager'), $nexura_role_name ) );
				?>
			</h3>
		</div>
		
		<div>
			<div class="nexura-upload-stat-row">
				<span class="nexura-upload-stat-name"><span class="dashicons dashicons-groups" style="color: #8c8f94;"></span> <?php esc_html_e( 'Quota Used', 'nexura-upload-limits-manager' ); ?></span>
				<span class="nexura-upload-stat-val"><?php echo esc_html( size_format( $nexura_used_bytes ) ); ?> / <?php echo esc_html( $nexura_total_display ); ?></span>
			</div>
			<div class="nexura-upload-bar-bg" style="margin-bottom: 0;">
				<div class="nexura-upload-bar-fill" style="background: <?php echo esc_attr($nexura_color); ?>; width: <?php echo esc_attr($nexura_percent); ?>%;"></div>
			</div>
			
			<div class="nexura-upload-info-grid">
				<div class="nexura-upload-info-item">
					<span class="nexura-upload-info-label">Upload Limit</span>
					<span class="nexura-upload-info-value"><?php echo esc_html( $nexura_upload_limit_val ); ?></span>
				</div>
				<div class="nexura-upload-info-item">
					<span class="nexura-upload-info-label">Quota per User</span>
					<span class="nexura-upload-info-value"><?php echo esc_html( $nexura_quota_display ); ?></span>
				</div>
				<div class="nexura-upload-info-item">
					<span class="nexura-upload-info-label">Total Users</span>
					<span class="nexura-upload-info-value"><?php echo (int) $nexura_total_users; ?></span>
				</div>
				<div class="nexura-upload-info-item">
					<span class="nexura-upload-info-label">Total Storage</span>
					<span class="nexura-upload-info-value"><?php echo esc_html( $nexura_total_display ); ?></span>
				</div>
			</div>
		</div>
	</div>
	<?php } ?>

</div> <!-- End Grid Container -->

<!-- Restore the Top Storage Users block here but inside the grid or below it -->
<div style="flex-basis: 100%;">
	<div class="nexura-upload-card">
		<div class="nexura-upload-header" style="margin-bottom: 24px;">
			<h3 class="nexura-upload-title"><?php esc_html_e( 'Top Storage Users', 'nexura-upload-limits-manager' ); ?></h3>
		</div>
		<div>
			<?php
			global $wpdb;
			$nexura_table_name = $wpdb->prefix . 'nexura_upload_logs';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$nexura_top_users = $wpdb->get_results( "SELECT user_id, SUM(file_size) as used_bytes FROM `{$nexura_table_name}` GROUP BY user_id ORDER BY used_bytes DESC LIMIT 5" );

			if ( ! empty( $nexura_top_users ) ) :
				foreach ( $nexura_top_users as $nexura_t_user ) :
					$nexura_u_id = (int) $nexura_t_user->user_id;
					$nexura_u_used = Nexura_Upload_Activity_Logger::get_user_storage_usage( $nexura_u_id );
					$nexura_user_info = get_userdata( $nexura_u_id );
					if ( ! $nexura_user_info ) continue;

					$nexura_u_quota = Nexura_Upload_Manager_Limits::get_user_quota( $nexura_u_id );
					$nexura_is_u_unlimited = ( $nexura_u_quota === 0 );
					$nexura_u_percent = ( ! $nexura_is_u_unlimited && $nexura_u_quota > 0 ) ? min(100, round(($nexura_u_used / $nexura_u_quota) * 100)) : 0;
					$nexura_u_color = '#2271b1';
					if ( $nexura_u_percent > 75 ) $nexura_u_color = '#ffb900';
					if ( $nexura_u_percent > 90 ) $nexura_u_color = '#dc3232';

					$nexura_avatar = get_avatar_url( $nexura_u_id, array( 'size' => 40 ) );
			?>
			<div class="nexura-upload-user-row">
				<img src="<?php echo esc_url($nexura_avatar); ?>" class="nexura-upload-avatar" alt="">
				<div style="flex: 1;">
					<div class="nexura-upload-stat-row" style="margin-bottom: 6px;">
						<span class="nexura-upload-stat-name"><?php echo esc_html( $nexura_user_info->display_name ); ?></span>
						<span class="nexura-upload-stat-val">
							<?php echo esc_html( size_format( $nexura_u_used ) ); ?> / <?php echo $nexura_is_u_unlimited ? 'Unlimited' : esc_html( size_format( $nexura_u_quota ) ); ?>
						</span>
					</div>
					<?php if ( ! $nexura_is_u_unlimited ) : ?>
					<div class="nexura-upload-bar-bg" style="margin-bottom: 0; height: 6px;">
						<div class="nexura-upload-bar-fill" style="background: <?php echo esc_attr($nexura_u_color); ?>; width: <?php echo esc_attr($nexura_u_percent); ?>%;"></div>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<?php 
				endforeach; 
			else:
			?>
				<p style="color: #8c8f94; font-size: 13px; text-align: center; font-style: italic; margin: 0;"><?php esc_html_e('No user data available.', 'nexura-upload-limits-manager'); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
