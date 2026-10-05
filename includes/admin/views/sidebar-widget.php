<?php
if ( ! defined( 'ABSPATH' ) ) exit;
include_once ABSPATH . 'wp-admin/includes/plugin.php';
$nexura_plugin_path = 'nexura-security/nexura-security.php';
$nexura_is_active   = is_plugin_active( $nexura_plugin_path );
$nexura_is_installed = file_exists( WP_PLUGIN_DIR . '/' . $nexura_plugin_path );
?>
<div class="postbox" style="margin-top: 50px; padding: 24px; border-radius: 8px; border: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); <?php echo $nexura_is_active ? 'border-top: 4px solid #00b859;' : 'border-top: 4px solid #2271b1;'; ?>">
	<h3 style="margin: 0 0 20px 0; display: flex; align-items: center; color: #1d2327; font-size: 16px; border: none; padding: 0;">
		<span class="dashicons dashicons-shield" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px;"></span>
		<span style="font-weight: 600;"><?php echo $nexura_is_active ? esc_html__( 'Website Protected!', 'nexura-upload-limits-manager' ) : esc_html__( 'Protect Your Website', 'nexura-upload-limits-manager' ); ?></span>
	</h3>
	
	<?php if ( $nexura_is_active ) : ?>
		<div style="background: linear-gradient(145deg, #f0fdf4, #dcfce7); padding: 24px 20px; border-radius: 12px; margin-bottom: 24px; text-align: center; border: 1px solid #bbf7d0; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5);">
			<div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: #fff; border-radius: 50%; box-shadow: 0 4px 10px rgba(0, 184, 89, 0.15); margin-bottom: 16px;">
				<span class="dashicons dashicons-shield" style="font-size: 36px; width: 36px; height: 36px; color: #00b859;"></span>
			</div>
			<h4 style="margin: 0 0 8px; color: #166534; font-size: 18px; font-weight: 700;"><?php esc_html_e( 'Congratulations!', 'nexura-upload-limits-manager' ); ?></h4>
			<p style="margin: 0; color: #14532d; font-size: 14px; line-height: 1.6; font-weight: 500;"><?php echo wp_kses_post( __( 'Nexura Security is actively running. Your website is now <strong>100% safe</strong> from malware and attacks.', 'nexura-upload-limits-manager' ) ); ?></p>
		</div>
		<button disabled style="width: 100%; background: #00b859; color: #fff; border: none; padding: 12px; font-size: 15px; font-weight: 600; border-radius: 6px; cursor: default; box-shadow: 0 4px 6px rgba(0, 184, 89, 0.25); display: flex; align-items: center; justify-content: center; gap: 8px;">
			<span style="display: inline-block; width: 8px; height: 8px; background: #fff; border-radius: 50%; box-shadow: 0 0 8px rgba(255,255,255,0.8); animation: nexuraPulse 2s infinite;"></span>
			<?php esc_html_e( 'Monitoring Active', 'nexura-upload-limits-manager' ); ?>
		</button>

	<?php else : ?>
		<p style="color: #3c434a; line-height: 1.6; font-size: 14px; margin-bottom: 15px;">
			<?php esc_html_e( 'Nexura Security is the ultimate Web Application Firewall (WAF) and malware scanner for WordPress.', 'nexura-upload-limits-manager' ); ?>
		</p>
		<ul style="color: #3c434a; font-size: 14px; list-style-type: none; margin-left: 0; margin-bottom: 24px; padding: 0;">
			<li style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-yes" style="color: #00b859;"></span> <?php esc_html_e( 'Block Brute Force Attacks', 'nexura-upload-limits-manager' ); ?></li>
			<li style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-yes" style="color: #00b859;"></span> <?php esc_html_e( 'Real-time Malware Scanning', 'nexura-upload-limits-manager' ); ?></li>
			<li style="display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-yes" style="color: #00b859;"></span> <?php esc_html_e( 'Login Protection & 2FA', 'nexura-upload-limits-manager' ); ?></li>
		</ul>
		<button id="install-nexura-security" data-installed="<?php echo $nexura_is_installed ? 'true' : 'false'; ?>" style="width: 100%; background: #2271b1; color: #fff; border: none; padding: 12px; font-size: 15px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.2s, transform 0.1s; box-shadow: 0 4px 6px rgba(34, 113, 177, 0.2);">
			<?php echo $nexura_is_installed ? esc_html__( 'Activate Nexura Security', 'nexura-upload-limits-manager' ) : esc_html__( 'Install Nexura Security - Free', 'nexura-upload-limits-manager' ); ?>
		</button>
	<?php endif; ?>
</div>

