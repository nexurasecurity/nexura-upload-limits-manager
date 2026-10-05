<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$nexura_status = Nexura_Upload_Manager_Server_Status::get_wp_status();
$nexura_server_status = Nexura_Upload_Manager_Server_Status::get_server_status();
?>


<?php 
if ( isset( $nexura_server_status['system_ram_usage']['raw_total'] ) && $nexura_server_status['system_ram_usage']['raw_total'] > 0 ) {
	$nexura_ram_total = $nexura_server_status['system_ram_usage']['raw_total'];
	$nexura_ram_used = $nexura_server_status['system_ram_usage']['raw_used'];
	$nexura_ram_percent = round( ( $nexura_ram_used / $nexura_ram_total ) * 100, 2 );
	$nexura_ram_color = '#46b450';
	if ( $nexura_ram_percent > 70 ) $nexura_ram_color = '#ffb900';
	if ( $nexura_ram_percent > 90 ) $nexura_ram_color = '#dc3232';
	?>
	<div style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px; display: flex; align-items: center; gap: 30px;">
		<div style="position: relative; width: 120px; height: 120px; border-radius: 50%; background: conic-gradient(<?php echo esc_attr($nexura_ram_color); ?> <?php echo esc_attr($nexura_ram_percent); ?>%, #e2e4e7 0); display: flex; align-items: center; justify-content: center;">
			<div style="width: 90px; height: 90px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-direction: column;">
				<strong style="font-size: 18px; color: #3c434a;"><?php echo esc_html( round($nexura_ram_percent) ); ?>%</strong>
				<span style="font-size: 11px; color: #646970; font-weight: 600;">RAM</span>
			</div>
		</div>
		<div>
			<h3 style="margin: 0 0 10px 0; font-size: 18px;"><?php esc_html_e('System RAM Usage', 'nexura-upload-limits-manager'); ?></h3>
			<p style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><strong>Used:</strong> <span style="color: <?php echo esc_attr($nexura_ram_color); ?>"><?php echo esc_html( size_format( $nexura_ram_used ) ); ?></span></p>
			<p style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><strong>Free:</strong> <?php echo esc_html( size_format( $nexura_ram_total - $nexura_ram_used ) ); ?></p>
			<p style="margin: 0; font-size: 14px; color: #50575e;"><strong>Total:</strong> <?php echo esc_html( size_format( $nexura_ram_total ) ); ?></p>
		</div>
	</div>
	<?php
}
?>
<h2 style="margin-top:0;"><?php esc_html_e( 'WordPress Status', 'nexura-upload-limits-manager' ); ?></h2>
<table class="widefat striped" style="margin-bottom: 30px;">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Title', 'nexura-upload-limits-manager' ); ?></th>
			<th style="width: 100px; text-align: center;"><?php esc_html_e( 'Status', 'nexura-upload-limits-manager' ); ?></th>
			<th><?php esc_html_e( 'Message', 'nexura-upload-limits-manager' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $nexura_status as $nexura_key => $nexura_data ) : ?>
		<tr>
			<td><?php echo esc_html( $nexura_data['title'] ); ?></td>
			<td style="text-align: center;">
				<?php if ( $nexura_data['status'] === 'ok' ) : ?>
					<span class="dashicons dashicons-yes" style="color: #00b859;"></span>
				<?php elseif ( $nexura_data['status'] === 'warning' ) : ?>
					<span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
				<?php else : ?>
					<span class="dashicons dashicons-no" style="color: #dc3232;"></span>
				<?php endif; ?>
			</td>
			<td><?php echo wp_kses_post( $nexura_data['message'] ); ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Server Status And PHP Status', 'nexura-upload-limits-manager' ); ?></h2>
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Title', 'nexura-upload-limits-manager' ); ?></th>
			<th style="width: 100px; text-align: center;"><?php esc_html_e( 'Status', 'nexura-upload-limits-manager' ); ?></th>
			<th><?php esc_html_e( 'Message', 'nexura-upload-limits-manager' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $nexura_server_status as $nexura_key => $nexura_data ) : ?>
		<tr>
			<td><?php echo esc_html( $nexura_data['title'] ); ?></td>
			<td style="text-align: center;">
				<?php if ( $nexura_data['status'] === 'ok' ) : ?>
					<span class="dashicons dashicons-yes" style="color: #00b859;"></span>
				<?php elseif ( $nexura_data['status'] === 'warning' ) : ?>
					<span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
				<?php else : ?>
					<span class="dashicons dashicons-no" style="color: #dc3232;"></span>
				<?php endif; ?>
			</td>
			<td><?php echo wp_kses_post( $nexura_data['message'] ); ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
