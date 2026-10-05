<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$nexura_roles = get_editable_roles();
$nexura_role_limits = get_option( 'nexura_role_upload_limits', array() );
$nexura_role_quotas = get_option( 'nexura_role_storage_quotas', array() );

$nexura_size_options = array(
	'Default' => __( 'Default', 'nexura-upload-limits-manager' ),
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

$nexura_quota_options = array(
	'Unlimited' => __( 'Unlimited', 'nexura-upload-limits-manager' ),
	'50M'   => __( '50 MB', 'nexura-upload-limits-manager' ),
	'100M'  => __( '100 MB', 'nexura-upload-limits-manager' ),
	'250M'  => __( '250 MB', 'nexura-upload-limits-manager' ),
	'500M'  => __( '500 MB', 'nexura-upload-limits-manager' ),
	'1G'    => __( '1 GB', 'nexura-upload-limits-manager' ),
	'2G'    => __( '2 GB', 'nexura-upload-limits-manager' ),
	'5G'    => __( '5 GB', 'nexura-upload-limits-manager' ),
	'10G'   => __( '10 GB', 'nexura-upload-limits-manager' ),
);

$nexura_global_raw = get_option( 'nexura_global_upload_limit', Nexura_Upload_Manager_Limits::DEFAULT_UPLOAD_LIMIT );
if ( 'Unlimited' === $nexura_global_raw ) {
	$nexura_global_label = __( 'Unlimited', 'nexura-upload-limits-manager' );
} else {
	$nexura_global_bytes = wp_convert_hr_to_bytes( (string) $nexura_global_raw );
	$nexura_global_label = $nexura_global_bytes > 0 ? size_format( $nexura_global_bytes ) : (string) $nexura_global_raw;
}
$nexura_size_options['Default'] = sprintf(
	/* translators: %s: current global upload limit. */
	__( 'Default (%s)', 'nexura-upload-limits-manager' ),
	$nexura_global_label
);
?>
<h2 style="margin-top:0;"><?php esc_html_e( 'Role-Based Upload Limits', 'nexura-upload-limits-manager' ); ?></h2>
<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: current global upload limit. */
			__( 'Upload Limit is the largest single file this role can upload. Default uses the site global limit, currently %s. Total Storage Quota is how much Media Library space that role can keep. Unlimited means no storage cap.', 'nexura-upload-limits-manager' ),
			$nexura_global_label
		)
	);
	?>
</p>

<form method="post" action="">
	<?php wp_nonce_field( 'nexura_upload_roles_nonce' ); ?>
	<div style="overflow-x:auto;">
	<table class="widefat striped" style="margin-top: 12px; min-width: 640px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Role', 'nexura-upload-limits-manager' ); ?></th>
				<th><?php esc_html_e( 'Display Name', 'nexura-upload-limits-manager' ); ?></th>
				<th style="width: 200px;"><?php esc_html_e( 'Upload Limit', 'nexura-upload-limits-manager' ); ?></th>
				<th style="width: 200px;"><?php esc_html_e( 'Total Storage Quota', 'nexura-upload-limits-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $nexura_roles as $nexura_role_key => $nexura_role_info ) : 
				$nexura_current_limit = isset( $nexura_role_limits[ $nexura_role_key ] ) ? $nexura_role_limits[ $nexura_role_key ] : 'Default';
				$nexura_current_quota = isset( $nexura_role_quotas[ $nexura_role_key ] ) ? $nexura_role_quotas[ $nexura_role_key ] : 'Unlimited';
			?>
			<tr>
				<td><?php echo esc_html( $nexura_role_key ); ?></td>
				<td><?php echo esc_html( translate_user_role( $nexura_role_info['name'] ) ); ?></td>
				<td>
					<select name="role_limit_<?php echo esc_attr( $nexura_role_key ); ?>" style="width: 100%; max-width: 220px;">
						<?php foreach ( $nexura_size_options as $nexura_val => $nexura_label ) : ?>
							<option value="<?php echo esc_attr( $nexura_val ); ?>" <?php selected( $nexura_current_limit, $nexura_val ); ?>><?php echo esc_html( $nexura_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<select name="role_quota_<?php echo esc_attr( $nexura_role_key ); ?>" style="width: 100%; max-width: 220px;">
						<?php foreach ( $nexura_quota_options as $nexura_val => $nexura_label ) : ?>
							<option value="<?php echo esc_attr( $nexura_val ); ?>" <?php selected( $nexura_current_quota, $nexura_val ); ?>><?php echo esc_html( $nexura_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="submit">
		<button type="submit" name="nexura_upload_save_roles" class="button button-primary" style="background: #3551ed; border-color: #3551ed; padding: 5px 20px;"><?php esc_html_e( 'Save Changes', 'nexura-upload-limits-manager' ); ?></button>
	</p>
</form>
