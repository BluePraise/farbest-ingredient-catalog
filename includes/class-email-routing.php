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
        // Settings UI lives in Ingredients → Main Settings (ACF options page).
    }

    /**
     * Send email based on routing rules
     */
    public static function send_email($data, $submission_id) {
        // Get recipient email based on representative code
        $to_email = self::get_recipient_email($data);

        if (!$to_email) {
            $acf_default = function_exists('get_field') ? (string) get_field('default_email', 'option') : '';
            $to_email    = $acf_default ?: get_option('admin_email');
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

        // Get mapping from Main Settings (ACF options page)
        $mapping = function_exists('get_field') ? (string) get_field('rep_email_mapping', 'option') : '';
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
        $cc_emails_raw = function_exists('get_field') ? (string) get_field('cc_emails', 'option') : '';
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
