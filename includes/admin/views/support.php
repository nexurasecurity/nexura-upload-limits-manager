<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get site domain for WhatsApp message
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$NEXURA_site_domain = wp_parse_url( site_url(), PHP_URL_HOST );
$NEXURA_wa_message = urlencode( "Hello, I need support for my site: " . $NEXURA_site_domain );
$NEXURA_wa_url = "https://wa.me/8801732593040?text=" . $NEXURA_wa_message;
// phpcs:enable
?>
<div style="max-width: 900px; margin: 20px auto;">
    <!-- Header Section -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 50px 40px; border-radius: 16px; text-align: center; color: white; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
        <h2 style="font-size: 32px; margin-top: 0; color: #fff; margin-bottom: 15px; font-weight: 700;"><?php esc_html_e( 'Features & Usage Guide', 'nexura-upload-limits-manager' ); ?></h2>
        <p style="color: #94a3b8; font-size: 17px; line-height: 1.6; max-width: 650px; margin: 0 auto;"><?php esc_html_e( 'Nexura Upload Limits Manager - Maximum Upload File Size gives you complete control over media uploads. Easily increase upload limits, manage role-based restrictions, and analyze your storage usage.', 'nexura-upload-limits-manager' ); ?></p>
    </div>
    
    <!-- Grid Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 40px;">
        <!-- Card 1 -->
        <div style="background: #fff; padding: 35px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px -5px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div style="background: #eff6ff; width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 25px; color: #3b82f6;">🚀</div>
            <h3 style="color: #0f172a; font-size: 22px; margin-top: 0; margin-bottom: 8px;"><?php esc_html_e( 'Bypass Server Limits', 'nexura-upload-limits-manager' ); ?></h3>
            <h4 style="color: #3b82f6; font-size: 13px; font-weight: 600; margin-top: 0; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;"><?php esc_html_e( 'Increase Max Upload Size', 'nexura-upload-limits-manager' ); ?></h4>
            <p style="color: #64748b; font-size: 15px; line-height: 1.7; margin-bottom: 0;"><?php echo wp_kses_post( __( 'Go to the <strong>Global Limit</strong> tab to set the upload size, memory, and execution time. When the server allows it, the plugin writes those values to <strong>.htaccess</strong> or <strong>.user.ini</strong>.', 'nexura-upload-limits-manager' ) ); ?></p>
        </div>
        
        <!-- Card 2 -->
        <div style="background: #fff; padding: 35px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px -5px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div style="background: #fdf4ff; width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 25px; color: #d946ef;">👥</div>
            <h3 style="color: #0f172a; font-size: 22px; margin-top: 0; margin-bottom: 8px;"><?php esc_html_e( 'Role-Based Controls', 'nexura-upload-limits-manager' ); ?></h3>
            <h4 style="color: #d946ef; font-size: 13px; font-weight: 600; margin-top: 0; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;"><?php esc_html_e( 'Manage specific user limits', 'nexura-upload-limits-manager' ); ?></h4>
            <p style="color: #64748b; font-size: 15px; line-height: 1.7; margin-bottom: 0;"><?php echo wp_kses_post( __( 'Want Admins to upload 1GB files but restrict Authors to 10MB? Head over to the <strong>Role-Based Limit</strong> tab to configure specific upload constraints for every user role.', 'nexura-upload-limits-manager' ) ); ?></p>
        </div>

        <!-- Card 3 -->
        <div style="background: #fff; padding: 35px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px -5px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div style="background: #f0fdf4; width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 25px; color: #22c55e;">📊</div>
            <h3 style="color: #0f172a; font-size: 22px; margin-top: 0; margin-bottom: 8px;"><?php esc_html_e( 'Storage Analytics', 'nexura-upload-limits-manager' ); ?></h3>
            <h4 style="color: #22c55e; font-size: 13px; font-weight: 600; margin-top: 0; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;"><?php esc_html_e( 'Track your media usage', 'nexura-upload-limits-manager' ); ?></h4>
            <p style="color: #64748b; font-size: 15px; line-height: 1.7; margin-bottom: 0;"><?php esc_html_e( 'Check out the beautiful pie chart on the right sidebar to analyze your disk usage. It automatically scans your database and sorts files into Images, Videos, Audio, and Archives.', 'nexura-upload-limits-manager' ); ?></p>
        </div>
        
        <!-- Card 4 -->
        <div style="background: #fff; padding: 35px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px -5px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div style="background: #fef2f2; width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 25px; color: #ef4444;">
                <img src="<?php echo esc_url( NEXURA_UPLOAD_MANAGER_URL . 'assets/img/icon.png' ); ?>" alt="Nexura Shield" style="width: 32px; height: 32px;">
            </div>
            <h3 style="color: #0f172a; font-size: 22px; margin-top: 0; margin-bottom: 8px;"><?php esc_html_e( 'Safe & Secure', 'nexura-upload-limits-manager' ); ?></h3>
            <h4 style="color: #ef4444; font-size: 13px; font-weight: 600; margin-top: 0; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;"><?php esc_html_e( 'Protect from exploits', 'nexura-upload-limits-manager' ); ?></h4>
            <p style="color: #64748b; font-size: 15px; line-height: 1.7; margin-bottom: 0;"><?php echo wp_kses_post( __( 'Our plugin modifies core limits securely. Combine it with <strong>Nexura Security</strong> for total protection against malware injection disguised as media uploads.', 'nexura-upload-limits-manager' ) ); ?></p>
        </div>
    </div>

    <!-- Support Section -->
    <div style="max-width: 800px; margin: 40px auto; text-align: center; background: #fff; padding: 50px 40px; border-radius: 12px; border: 1px solid #ccd0d4; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <h2 style="font-size: 26px; margin-top: 0; margin-bottom: 15px; color: #1d2327;"><?php esc_html_e( 'Support the Development', 'nexura-upload-limits-manager' ); ?></h2>
        <p style="color: #50575e; font-size: 16px; max-width: 650px; margin: 0 auto 40px auto; line-height: 1.6;">
            <?php esc_html_e( 'Nexura Upload Limits Manager is an open-source project dedicated to giving you complete control over your WordPress site. If this plugin has saved you time, please consider supporting the author!', 'nexura-upload-limits-manager' ); ?>
        </p>
        
        <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
            <!-- bKash Donate -->
            <div style="display: inline-flex; flex-direction: column; justify-content: center; background: linear-gradient(135deg, #e2136e 0%, #b80d56 100%); color: white; padding: 15px 35px; border-radius: 8px; box-shadow: 0 4px 10px rgba(226, 19, 110, 0.2);">
                <div style="font-size: 14px; font-weight: 600; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( '☕ Donate via bKash', 'nexura-upload-limits-manager' ); ?></div>
                <div style="font-size: 22px; font-weight: 700; letter-spacing: 2px;">01732593040</div>
            </div>

            <!-- WhatsApp -->
            <a href="<?php echo esc_url( $NEXURA_wa_url ); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; text-decoration: none; padding: 15px 35px; border-radius: 8px; font-size: 18px; font-weight: 600; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <?php esc_html_e( 'WhatsApp Support', 'nexura-upload-limits-manager' ); ?>
            </a>
        </div>
    </div>
</div>
