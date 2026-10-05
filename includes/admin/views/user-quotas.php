<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$nexura_table_name = $wpdb->prefix . 'nexura_upload_logs';

// Get users who have uploaded files
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$nexura_users_usage_raw = $wpdb->get_results( "SELECT user_id, SUM(file_size) as total_used FROM `{$nexura_table_name}` GROUP BY user_id ORDER BY total_used DESC LIMIT 100" );

$nexura_users_data = array();
if ( $nexura_users_usage_raw ) {
	foreach ( $nexura_users_usage_raw as $nexura_usage ) {
		$nexura_users_data[ $nexura_usage->user_id ] = array(
			'user_id'    => $nexura_usage->user_id,
			'total_used' => $nexura_usage->total_used,
		);
	}
}

// Get users who have custom quotas
// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
$nexura_users_with_quotas = get_users( array( 'meta_key' => 'nexura_custom_storage_quota', 'fields' => 'ID' ) );
foreach ( $nexura_users_with_quotas as $nexura_uid ) {
	if ( ! isset( $nexura_users_data[ $nexura_uid ] ) ) {
		$nexura_users_data[ $nexura_uid ] = array(
			'user_id'    => $nexura_uid,
			'total_used' => 0,
		);
	}
}
?>

<div class="nexura-dashboard-header">
    <div>
        <h2 style="margin: 0 0 5px 0; font-size: 20px; color: #0f172a;"><?php esc_html_e( 'User Storage Quotas', 'nexura-upload-limits-manager' ); ?></h2>
        <p style="margin: 0; color: #64748b; font-size: 14px;"><?php esc_html_e( 'Manage storage quotas for individual users overriding role limits.', 'nexura-upload-limits-manager' ); ?></p>
    </div>
    <div class="nexura-add-quota-wrap">
        <?php
        wp_dropdown_users( array(
            'name'             => 'new_quota_user_id',
            'id'               => 'new_quota_user_id',
            'show_option_none' => '-- Select User --',
            'class'            => 'regular-text',
            'style'            => 'border-radius: 6px;'
        ) );
        ?>
        <input type="text" id="new_quota_input" placeholder="e.g. 5GB" style="width: 100px; border-radius: 6px;" />
        <button type="button" class="button button-primary" id="nexura-add-quota-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nexura_upload_users_nonce' ) ); ?>" style="border-radius: 6px;"><?php esc_html_e( 'Add Quota', 'nexura-upload-limits-manager' ); ?></button>
        <span class="nexura-add-quota-spinner spinner" style="float:none; margin:0;"></span>
    </div>
</div>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div class="nexura-search-box">
        <input type="text" id="nexura-user-search" placeholder="<?php esc_attr_e( 'Search user by name, email or role...', 'nexura-upload-limits-manager' ); ?>" />
    </div>
    <div id="nexura-global-notice" class="nexura-inline-notice"></div>
</div>

<div class="nexura-user-list">
    <?php if ( empty( $nexura_users_data ) ) : ?>
        <div class="nexura-user-card" style="justify-content: center; color: #64748b; padding: 40px;">
            <?php esc_html_e( 'No users found with uploads or custom quotas.', 'nexura-upload-limits-manager' ); ?>
        </div>
    <?php else : ?>
        <?php foreach ( $nexura_users_data as $nexura_usage ) : 
            $nexura_user = get_userdata( $nexura_usage['user_id'] );
            if ( ! $nexura_user ) continue;

            $nexura_state           = Nexura_Upload_Manager_Limits::get_user_quota_state( $nexura_user->ID );
            $nexura_usage['total_used'] = Nexura_Upload_Activity_Logger::get_user_storage_usage( $nexura_user->ID );
            $nexura_max_quota_bytes = (int) $nexura_state['bytes'];
            $nexura_quota_display   = $nexura_state['label'];
            $nexura_has_quota       = ! empty( $nexura_state['enforced'] );
            $nexura_role_names      = implode( ', ', $nexura_user->roles );

            $nexura_custom_quota = get_user_meta( $nexura_user->ID, 'nexura_custom_storage_quota', true );
            $nexura_input_val    = '';
            $nexura_is_custom    = false;

            if ( ! empty( $nexura_custom_quota ) && $nexura_custom_quota !== 'Default' ) {
                $nexura_input_val = $nexura_custom_quota;
                $nexura_is_custom = true;
            }

            $nexura_percentage = 0;
            $nexura_remaining_bytes = 0;
            if ( $nexura_has_quota && $nexura_max_quota_bytes > 0 ) {
                $nexura_percentage = ( $nexura_usage['total_used'] / $nexura_max_quota_bytes ) * 100;
                $nexura_remaining_bytes = max( 0, $nexura_max_quota_bytes - $nexura_usage['total_used'] );
            }

            $nexura_progress_color = '#10b981'; // Green
            if ( $nexura_percentage > 70 ) $nexura_progress_color = '#f59e0b'; // Yellow
            if ( $nexura_percentage > 90 ) $nexura_progress_color = '#ef4444'; // Red
            
            $nexura_search_data = strtolower( $nexura_user->display_name . ' ' . $nexura_user->user_email . ' ' . $nexura_role_names );
        ?>
        <div class="nexura-user-card nexura-search-item" data-search="<?php echo esc_attr( $nexura_search_data ); ?>">
            <div class="nexura-user-info">
                <div class="nexura-user-avatar">
                    <?php echo get_avatar( $nexura_user->ID, 48 ); ?>
                </div>
                <div class="nexura-user-details">
                    <h4><?php echo esc_html( $nexura_user->display_name ); ?></h4>
                    <span><?php echo esc_html( ucfirst( $nexura_role_names ) ); ?> <?php echo $nexura_is_custom ? '<b style="color:#3b82f6;">• Custom</b>' : ''; ?></span>
                </div>
            </div>

            <div class="nexura-progress-wrapper">
                <?php if ( $nexura_has_quota ) : ?>
                    <div class="nexura-progress-stats">
                        <span><?php echo esc_html( size_format( $nexura_usage['total_used'] ) ); ?> / <?php echo esc_html( $nexura_quota_display ); ?></span>
                        <span><?php echo esc_html( round( $nexura_percentage, 1 ) ); ?>% Used (<?php echo esc_html( size_format( $nexura_remaining_bytes ) ); ?> remaining)</span>
                    </div>
                    <div class="nexura-progress-bar-bg">
                        <div class="nexura-progress-fill" style="width: <?php echo esc_attr( min( 100, $nexura_percentage ) ); ?>%; background: <?php echo esc_attr( $nexura_progress_color ); ?>;"></div>
                    </div>
                <?php else : ?>
                    <div class="nexura-progress-stats" style="justify-content: center;">
                        <span style="color: #10b981; font-weight: 600;"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Unlimited Quota', 'nexura-upload-limits-manager' ); ?></span>
                    </div>
                    <div class="nexura-progress-stats" style="justify-content: center; margin-top: 5px;">
                        <span><?php echo esc_html( size_format( $nexura_usage['total_used'] ) ); ?> Used</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="nexura-actions">
                <div class="nexura-quota-view-<?php echo esc_attr( $nexura_user->ID ); ?>">
                    <button type="button" class="button nexura-edit-quota-btn" data-user="<?php echo esc_attr( $nexura_user->ID ); ?>" style="border-radius: 6px;"><?php esc_html_e( 'Manage Quota', 'nexura-upload-limits-manager' ); ?></button>
                </div>
                <div class="nexura-quota-edit-<?php echo esc_attr( $nexura_user->ID ); ?>" style="display:none; text-align: right;">
                    <input type="text" class="nexura-quota-input-<?php echo esc_attr( $nexura_user->ID ); ?>" value="<?php echo esc_attr( $nexura_input_val ); ?>" placeholder="e.g. 5GB" style="width: 80px; border-radius: 4px; padding: 2px 8px; font-size: 13px;" />
                    <button type="button" class="button button-primary button-small nexura-save-quota-btn" data-user="<?php echo esc_attr( $nexura_user->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nexura_upload_users_nonce' ) ); ?>" style="border-radius: 4px;"><?php esc_html_e( 'Save', 'nexura-upload-limits-manager' ); ?></button>
                    <button type="button" class="button button-small nexura-cancel-quota-btn" data-user="<?php echo esc_attr( $nexura_user->ID ); ?>" style="border-radius: 4px;"><?php esc_html_e( 'Cancel', 'nexura-upload-limits-manager' ); ?></button>
                    <div class="nexura-quota-spinner-<?php echo esc_attr( $nexura_user->ID ); ?> spinner" style="float:none; margin: 4px 0 0 0;"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
