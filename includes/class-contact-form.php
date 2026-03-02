<?php
/**
 * Contact Form Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_Contact_Form {
    
    /**
     * Initialize contact form
     */
    public static function init() {
        add_action('wp_ajax_fpc_submit_contact', array(__CLASS__, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_fpc_submit_contact', array(__CLASS__, 'handle_ajax_submission'));
        add_shortcode('fpc_contact_form', array(__CLASS__, 'render_form'));
    }
    
    /**
     * Render contact form shortcode
     */
    public static function render_form($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID(),
        ), $atts);
        
        ob_start();
        include FPC_PLUGIN_DIR . 'templates/contact-form.php';
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX form submission
     */
    public static function handle_ajax_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpc_contact_form')) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        
        // Sanitize and validate input
        $data = self::sanitize_form_data($_POST);
        $errors = self::validate_form_data($data);
        
        if (!empty($errors)) {
            wp_send_json_error(array('message' => 'Validation failed', 'errors' => $errors), 400);
        }
        
        // Save to database
        $submission_id = self::save_submission($data);
        
        if (!$submission_id) {
            wp_send_json_error(array('message' => 'Failed to save submission'), 500);
        }
        
        // Send email
        $email_sent = FPC_Email_Routing::send_email($data, $submission_id);
        
        if (!$email_sent) {
            error_log('FPC: Email failed for submission #' . $submission_id);
        }
        
        wp_send_json_success(array(
            'message' => 'Thank you for your inquiry. We will contact you soon.',
            'submission_id' => $submission_id,
        ));
    }
    
    /**
     * Handle REST API submission
     */
    public static function handle_submission($request) {
        $params = $request->get_params();
        
        // Sanitize and validate
        $data = self::sanitize_form_data($params);
        $errors = self::validate_form_data($data);
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', 'Validation failed', array('status' => 400, 'errors' => $errors));
        }
        
        // Save to database
        $submission_id = self::save_submission($data);
        
        if (!$submission_id) {
            return new WP_Error('save_failed', 'Failed to save submission', array('status' => 500));
        }
        
        // Send email
        FPC_Email_Routing::send_email($data, $submission_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Thank you for your inquiry. We will contact you soon.',
            'submission_id' => $submission_id,
        ), 200);
    }
    
    /**
     * Sanitize form data
     */
    private static function sanitize_form_data($data) {
        return array(
            'product_id' => isset($data['product_id']) ? absint($data['product_id']) : 0,
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'email' => isset($data['email']) ? sanitize_email($data['email']) : '',
            'company' => isset($data['company']) ? sanitize_text_field($data['company']) : '',
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            'message' => isset($data['message']) ? sanitize_textarea_field($data['message']) : '',
            'request_product_sheet' => isset($data['request_product_sheet']) ? (bool) $data['request_product_sheet'] : false,
            'request_quote' => isset($data['request_quote']) ? (bool) $data['request_quote'] : false,
        );
    }
    
    /**
     * Validate form data
     */
    private static function validate_form_data($data) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Invalid email address';
        }
        
        if (empty($data['company'])) {
            $errors['company'] = 'Company name is required';
        }
        
        if (empty($data['message'])) {
            $errors['message'] = 'Message is required';
        }
        
        return $errors;
    }
    
    /**
     * Save submission to database
     */
    private static function save_submission($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fpc_submissions';
        
        // Get representative code from product
        $rep_code = '';
        if ($data['product_id']) {
            $rep_code = get_field('rep_code_primary', $data['product_id']);
        }
        
        // Determine request type
        $request_type = array();
        if ($data['request_product_sheet']) {
            $request_type[] = 'product_sheet';
        }
        if ($data['request_quote']) {
            $request_type[] = 'quote';
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'product_id' => $data['product_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'company' => $data['company'],
                'message' => $data['message'],
                'request_type' => implode(',', $request_type),
                'representative_code' => $rep_code,
                'submitted_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('FPC: Database insert failed - ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
}
