<?php
/**
 * Contact Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$product_id = isset($atts['product_id']) ? intval($atts['product_id']) : 0;
?>

<form class="fpc-contact-form" id="fpc-contact-form-<?php echo $product_id; ?>" data-product-id="<?php echo $product_id; ?>">
    
    <?php wp_nonce_field('fpc_contact_form', 'fpc_nonce'); ?>
    
    <div class="form-messages" role="alert" style="display: none;"></div>
    
    <div class="form-row">
        <div class="form-field">
            <label for="fpc_name"><?php esc_html_e('Name', 'farbest-catalog'); ?> <span class="required">*</span></label>
            <input type="text" 
                   id="fpc_name" 
                   name="name" 
                   required 
                   placeholder="<?php esc_attr_e('Your Name', 'farbest-catalog'); ?>">
        </div>
        
        <div class="form-field">
            <label for="fpc_email"><?php esc_html_e('Email', 'farbest-catalog'); ?> <span class="required">*</span></label>
            <input type="email" 
                   id="fpc_email" 
                   name="email" 
                   required 
                   placeholder="<?php esc_attr_e('your@email.com', 'farbest-catalog'); ?>">
        </div>
    </div>
    
    <div class="form-row">
        <div class="form-field">
            <label for="fpc_company"><?php esc_html_e('Company', 'farbest-catalog'); ?> <span class="required">*</span></label>
            <input type="text" 
                   id="fpc_company" 
                   name="company" 
                   required 
                   placeholder="<?php esc_attr_e('Company Name', 'farbest-catalog'); ?>">
        </div>
        
        <div class="form-field">
            <label for="fpc_phone"><?php esc_html_e('Phone', 'farbest-catalog'); ?></label>
            <input type="tel" 
                   id="fpc_phone" 
                   name="phone" 
                   placeholder="<?php esc_attr_e('(555) 123-4567', 'farbest-catalog'); ?>">
        </div>
    </div>
    
    <div class="form-field">
        <label for="fpc_message"><?php esc_html_e('Message', 'farbest-catalog'); ?> <span class="required">*</span></label>
        <textarea id="fpc_message" 
                  name="message" 
                  rows="5" 
                  required 
                  placeholder="<?php esc_attr_e('Tell us about your needs...', 'farbest-catalog'); ?>"></textarea>
    </div>
    
    <div class="form-field form-checkboxes">
        <label class="checkbox-label">
            <input type="checkbox" name="request_product_sheet" value="1">
            <span><?php esc_html_e('Request Product Sheet', 'farbest-catalog'); ?></span>
        </label>
        
        <label class="checkbox-label">
            <input type="checkbox" name="request_quote" value="1">
            <span><?php esc_html_e('Request Quote', 'farbest-catalog'); ?></span>
        </label>
    </div>
    
    <div class="form-submit">
        <button type="submit" class="button button-primary">
            <?php esc_html_e('Submit Inquiry', 'farbest-catalog'); ?>
        </button>
        <span class="form-spinner" style="display: none;">
            <span class="dashicons dashicons-update-alt spinning"></span>
        </span>
    </div>
    
</form>

<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('fpc-contact-form-<?php echo $product_id; ?>');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = form.querySelector('button[type="submit"]');
            const spinner = form.querySelector('.form-spinner');
            const messages = form.querySelector('.form-messages');
            
            // Disable submit button
            submitButton.disabled = true;
            spinner.style.display = 'inline-block';
            messages.style.display = 'none';
            
            // Gather form data
            const formData = new FormData(form);
            formData.append('action', 'fpc_submit_contact');
            formData.append('product_id', form.dataset.productId);
            formData.append('nonce', form.querySelector('[name="fpc_nonce"]').value);
            
            // Submit via AJAX
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messages.className = 'form-messages success';
                    messages.textContent = data.data.message;
                    messages.style.display = 'block';
                    form.reset();
                } else {
                    messages.className = 'form-messages error';
                    messages.textContent = data.data.message || 'An error occurred. Please try again.';
                    messages.style.display = 'block';
                }
            })
            .catch(error => {
                messages.className = 'form-messages error';
                messages.textContent = 'An error occurred. Please try again.';
                messages.style.display = 'block';
            })
            .finally(() => {
                submitButton.disabled = false;
                spinner.style.display = 'none';
            });
        });
    });
})();
</script>

<style>
.fpc-contact-form {
    max-width: 600px;
    margin: 0 auto;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-field {
    margin-bottom: 20px;
}

.form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-field input[type="text"],
.form-field input[type="email"],
.form-field input[type="tel"],
.form-field textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-checkboxes {
    display: flex;
    gap: 20px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
}

.form-messages {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.form-messages.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.form-messages.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.form-submit {
    display: flex;
    align-items: center;
    gap: 10px;
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>
