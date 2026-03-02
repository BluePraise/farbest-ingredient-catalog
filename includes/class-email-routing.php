<?php
/**
 * Email Routing Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_Email_Routing {
    
    /**
     * Initialize email routing
     */
    public static function init() {
        // Add settings page for representative email configuration
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }
    
    /**
     * Add settings page
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=fpc_product',
            'Email Settings',
            'Email Settings',
            'manage_options',
            'fpc-email-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('fpc_email_settings', 'fpc_rep_emails');
        register_setting('fpc_email_settings', 'fpc_default_email');
        register_setting('fpc_email_settings', 'fpc_cc_emails');
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Email Routing Settings', 'farbest-catalog'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('fpc_email_settings'); ?>
                <?php do_settings_sections('fpc_email_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fpc_default_email"><?php echo esc_html__('Default Email Address', 'farbest-catalog'); ?></label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="fpc_default_email" 
                                   name="fpc_default_email" 
                                   value="<?php echo esc_attr(get_option('fpc_default_email', get_option('admin_email'))); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php echo esc_html__('Used when no representative code is assigned', 'farbest-catalog'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fpc_cc_emails"><?php echo esc_html__('CC Email Addresses', 'farbest-catalog'); ?></label>
                        </th>
                        <td>
                            <textarea id="fpc_cc_emails" 
                                      name="fpc_cc_emails" 
                                      rows="3" 
                                      class="large-text"><?php echo esc_textarea(get_option('fpc_cc_emails', '')); ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('One email per line. These addresses will be CC\'d on all submissions.', 'farbest-catalog'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fpc_rep_emails"><?php echo esc_html__('Representative Email Mapping', 'farbest-catalog'); ?></label>
                        </th>
                        <td>
                            <textarea id="fpc_rep_emails" 
                                      name="fpc_rep_emails" 
                                      rows="10" 
                                      class="large-text code"><?php echo esc_textarea(get_option('fpc_rep_emails', '')); ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('Format: code|email@example.com (one per line)', 'farbest-catalog'); ?><br>
                                <?php echo esc_html__('Example:', 'farbest-catalog'); ?><br>
                                <code>101|john@farbest.com</code><br>
                                <code>102|jane@farbest.com</code>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Send email based on routing rules
     */
    public static function send_email($data, $submission_id) {
        // Get recipient email based on representative code
        $to_email = self::get_recipient_email($data);
        
        if (!$to_email) {
            $to_email = get_option('fpc_default_email', get_option('admin_email'));
        }
        
        // Get CC emails
        $cc_emails = self::get_cc_emails();
        
        // Build email subject
        $subject = self::build_subject($data);
        
        // Build email body
        $message = self::build_message($data, $submission_id);
        
        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
        );
        
        // Add CC headers
        foreach ($cc_emails as $cc_email) {
            $headers[] = 'Cc: ' . $cc_email;
        }
        
        // Send email
        $sent = wp_mail($to_email, $subject, $message, $headers);
        
        // Log email attempt
        error_log(sprintf(
            'FPC Email: Submission #%d - To: %s - Subject: %s - Sent: %s',
            $submission_id,
            $to_email,
            $subject,
            $sent ? 'Yes' : 'No'
        ));
        
        return $sent;
    }
    
    /**
     * Get recipient email based on representative code
     */
    private static function get_recipient_email($data) {
        // Get rep code from product
        if (empty($data['product_id'])) {
            return null;
        }
        
        $rep_code = get_field('rep_code_primary', $data['product_id']);
        
        if (empty($rep_code)) {
            return null;
        }
        
        // Get mapping from settings
        $mapping = get_option('fpc_rep_emails', '');
        $lines = explode("\n", $mapping);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $parts = explode('|', $line);
            if (count($parts) !== 2) {
                continue;
            }
            
            $code = trim($parts[0]);
            $email = trim($parts[1]);
            
            if ($code === $rep_code && is_email($email)) {
                return $email;
            }
        }
        
        return null;
    }
    
    /**
     * Get CC email addresses
     */
    private static function get_cc_emails() {
        $cc_emails_raw = get_option('fpc_cc_emails', '');
        $lines = explode("\n", $cc_emails_raw);
        $cc_emails = array();
        
        foreach ($lines as $line) {
            $email = trim($line);
            if (!empty($email) && is_email($email)) {
                $cc_emails[] = $email;
            }
        }
        
        return $cc_emails;
    }
    
    /**
     * Build email subject
     */
    private static function build_subject($data) {
        $product_name = '';
        if (!empty($data['product_id'])) {
            $product_name = get_the_title($data['product_id']);
        }
        
        $request_types = array();
        if ($data['request_product_sheet']) {
            $request_types[] = 'Product Sheet Request';
        }
        if ($data['request_quote']) {
            $request_types[] = 'Quote Request';
        }
        
        if (empty($request_types)) {
            $request_types[] = 'Product Inquiry';
        }
        
        $subject = sprintf(
            '[%s] %s - %s',
            get_bloginfo('name'),
            implode(' & ', $request_types),
            $product_name ? $product_name : 'General Inquiry'
        );
        
        return $subject;
    }
    
    /**
     * Build email message
     */
    private static function build_message($data, $submission_id) {
        $product_info = '';
        if (!empty($data['product_id'])) {
            $product = get_post($data['product_id']);
            $product_url = get_permalink($data['product_id']);
            $product_info = sprintf(
                '<p><strong>Product:</strong> <a href="%s">%s</a></p>',
                esc_url($product_url),
                esc_html($product->post_title)
            );
            
            // Add product sheet link if available
            $product_sheet = get_field('product_sheet', $data['product_id']);
            if ($product_sheet && isset($product_sheet['url'])) {
                $product_info .= sprintf(
                    '<p><strong>Product Sheet:</strong> <a href="%s">Download PDF</a></p>',
                    esc_url($product_sheet['url'])
                );
            }
        }
        
        $request_info = '';
        if ($data['request_product_sheet']) {
            $request_info .= '<li>Product Sheet Requested</li>';
        }
        if ($data['request_quote']) {
            $request_info .= '<li>Quote Requested</li>';
        }
        
        $message = sprintf(
            '<html><body style="font-family: Arial, sans-serif; line-height: 1.6;">
                <h2 style="color: #333;">New Product Inquiry</h2>
                <p><strong>Submission ID:</strong> #%d</p>
                <hr style="border: 1px solid #ddd;">
                
                %s
                
                <h3 style="color: #555;">Customer Information</h3>
                <p><strong>Name:</strong> %s</p>
                <p><strong>Email:</strong> <a href="mailto:%s">%s</a></p>
                <p><strong>Company:</strong> %s</p>
                <p><strong>Phone:</strong> %s</p>
                
                <h3 style="color: #555;">Request Details</h3>
                <ul>%s</ul>
                
                <h3 style="color: #555;">Message</h3>
                <p style="background: #f9f9f9; padding: 15px; border-left: 4px solid #007cba;">%s</p>
                
                <hr style="border: 1px solid #ddd;">
                <p style="color: #666; font-size: 12px;">
                    This email was sent from the Farbest Product Catalog on %s
                </p>
            </body></html>',
            $submission_id,
            $product_info,
            esc_html($data['name']),
            esc_attr($data['email']),
            esc_html($data['email']),
            esc_html($data['company']),
            esc_html($data['phone']),
            $request_info ?: '<li>General Inquiry</li>',
            nl2br(esc_html($data['message'])),
            current_time('F j, Y g:i a')
        );
        
        return $message;
    }
}
