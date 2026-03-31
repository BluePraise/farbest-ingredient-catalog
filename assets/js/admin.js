/**
 * Admin JavaScript for Farbest Product Catalog
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Add column for thumbnail in admin list
        if ($('body.post-type-fpc_ingredient').length) {
            addThumbnailColumn();
        }

        // Representative code validation
        validateRepCodes();

        // Email settings helper
        emailSettingsHelper();
    });

    /**
        * Add thumbnail column to ingredient list
     */
    function addThumbnailColumn() {
        // This would typically be done via PHP filters
        // Placeholder for any JS enhancements
    }

    /**
     * Validate representative codes
     */
    function validateRepCodes() {
        const repCodeFields = $('input[name*="rep_code"]');

        repCodeFields.on('blur', function() {
            const value = $(this).val();
            if (value && !/^\d+$/.test(value)) {
                $(this).css('border-color', '#dc3232');
                alert('Representative code must be numeric');
            } else {
                $(this).css('border-color', '');
            }
        });
    }

    /**
     * Email settings page helper
     */
    function emailSettingsHelper() {
        const emailMappingField = $('#fpc_rep_emails');

        if (!emailMappingField.length) return;

        // Add validation on blur
        emailMappingField.on('blur', function() {
            const lines = $(this).val().split('\n');
            let hasError = false;

            lines.forEach(line => {
                line = line.trim();
                if (!line) return;

                const parts = line.split('|');
                if (parts.length !== 2) {
                    hasError = true;
                    return;
                }

                const email = parts[1].trim();
                if (!isValidEmail(email)) {
                    hasError = true;
                }
            });

            if (hasError) {
                $(this).css('border-color', '#dc3232');
                alert('Please check the email mapping format. Each line should be: code|email@example.com');
            } else {
                $(this).css('border-color', '');
            }
        });
    }

    /**
     * Email validation helper
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

})(jQuery);
